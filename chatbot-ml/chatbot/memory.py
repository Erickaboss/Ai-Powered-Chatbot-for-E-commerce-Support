"""
Conversation memory manager for the e-commerce chatbot.
Provides session-based persistence of conversation context
so the chatbot remembers category, budget, brand etc. across turns.
"""

import json
import os
import re
import time
from threading import Lock
from typing import Any, Optional

import mysql.connector


def get_default_db_config() -> dict:
    return {
        "host": os.environ.get("CHATBOT_DB_HOST", "localhost"),
        "user": os.environ.get("CHATBOT_DB_USER", "root"),
        "password": os.environ.get("CHATBOT_DB_PASS", ""),
        "database": os.environ.get("CHATBOT_DB_NAME", "ecommerce_chatbot"),
        "connection_timeout": 5,
    }


class ChatMemory:
    """
    Session-aware conversation memory backed by MySQL.

    Each session_id gets a JSON blob of conversation context
    (last category, budget, brand, language, etc.) that persists
    across requests so the bot appears to "remember" the conversation.

    Falls back to in-process memory if MySQL is unavailable.
    """

    _local_store: dict[str, dict] = {}
    _local_lock: Lock = Lock()

    def __init__(self, db_config: Optional[dict] = None):
        self.db_config = db_config or get_default_db_config()
        self._ensure_table()

    def _get_db(self):
        return mysql.connector.connect(**self.db_config)

    def _ensure_table(self):
        try:
            conn = self._get_db()
            conn.cursor().execute("""
                CREATE TABLE IF NOT EXISTS chatbot_memory (
                    owner_key VARCHAR(96) PRIMARY KEY,
                    user_id INT DEFAULT NULL,
                    session_id VARCHAR(64) DEFAULT NULL,
                    memory_json LONGTEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            """)
            conn.commit()
            conn.close()
        except Exception:
            pass  # fallback to local dict
        # Clean up sessions older than 24 hours
        self._cleanup_expired()

    def _cleanup_expired(self, max_age_hours: int = 24):
        try:
            conn = self._get_db()
            conn.cursor().execute(
                "DELETE FROM chatbot_memory WHERE updated_at < NOW() - INTERVAL %s HOUR",
                (max_age_hours,),
            )
            conn.commit()
            conn.close()
        except Exception:
            pass

    def _owner_key(self, session_id: str, user_id: Optional[int] = None) -> str:
        if user_id:
            return f"u{user_id}"
        return f"s{session_id}" if session_id else "anon"

    def get(self, session_id: str, user_id: Optional[int] = None) -> dict:
        key = self._owner_key(session_id, user_id)
        try:
            conn = self._get_db()
            cur = conn.cursor(dictionary=True)
            cur.execute(
                "SELECT memory_json FROM chatbot_memory WHERE owner_key = %s",
                (key,),
            )
            row = cur.fetchone()
            conn.close()
            if row:
                data = json.loads(row["memory_json"])
                data.pop("_key", None)
                return data
        except Exception:
            pass
        with self._local_lock:
            return self._local_store.get(key, {})

    def set(
        self,
        session_id: str,
        user_id: Optional[int],
        data: dict,
    ) -> None:
        key = self._owner_key(session_id, user_id)
        serialized = json.dumps({**data, "_key": key})
        try:
            conn = self._get_db()
            cur = conn.cursor()
            uid_val = user_id
            sid_val = session_id if session_id else None
            cur.execute(
                "REPLACE INTO chatbot_memory (owner_key, user_id, session_id, memory_json) "
                "VALUES (%s, %s, %s, %s)",
                (key, uid_val, sid_val, serialized),
            )
            conn.commit()
            conn.close()
        except Exception:
            with self._local_lock:
                self._local_store[key] = data

    def update(
        self,
        session_id: str,
        user_id: Optional[int],
        updates: dict,
    ) -> dict:
        current = self.get(session_id, user_id)
        current.update(updates)
        self.set(session_id, user_id, current)
        return current

    def clear(self, session_id: str, user_id: Optional[int] = None) -> None:
        key = self._owner_key(session_id, user_id)
        try:
            conn = self._get_db()
            conn.cursor().execute(
                "DELETE FROM chatbot_memory WHERE owner_key = %s", (key,)
            )
            conn.commit()
            conn.close()
        except Exception:
            pass
        with self._local_lock:
            self._local_store.pop(key, None)

    def merge_context_into_entities(
        self,
        entities: dict,
        session_id: str,
        user_id: Optional[int] = None,
    ) -> dict:
        """
        Fill in missing entity slots from previous conversation turns.
        e.g. user said "phones" in turn 1, then "under 50k" in turn 2:
        the budget is new but category comes from memory.
        """
        ctx = self.get(session_id, user_id)
        enriched = {**entities}

        if not enriched.get("category_id") and ctx.get("category_id"):
            enriched["category_id"] = ctx["category_id"]
            enriched["category_name"] = ctx.get("category_name")
        if not enriched.get("brands") and ctx.get("brands"):
            enriched["brands"] = ctx["brands"]
        if not enriched.get("budget_max") and ctx.get("budget_max"):
            enriched["budget_max"] = ctx["budget_max"]
        if not enriched.get("budget_min") and ctx.get("budget_min"):
            enriched["budget_min"] = ctx["budget_min"]
        if not enriched.get("product_keywords") and ctx.get("product_keywords"):
            enriched["product_keywords"] = ctx["product_keywords"]
        elif enriched.get("product_keywords") and ctx.get("product_keywords"):
            # Check if new keywords are just size/measurement modifiers (e.g. "2l", "500ml")
            # If so, prepend context product keywords so search becomes "inyange milk 2l" instead of just "2l"
            new_kws = enriched["product_keywords"]
            all_size_modifiers = all(
                bool(re.match(r'^\d+(?:\.\d+)?\s*[a-z]{1,3}$', kw))
                for kw in new_kws
            )
            if all_size_modifiers:
                combined = ctx["product_keywords"] + new_kws
                enriched["product_keywords"] = combined[:5]
        if not enriched.get("size") and ctx.get("size"):
            enriched["size"] = ctx["size"]

        return enriched

    def track_product_view(
        self,
        session_id: str,
        user_id: Optional[int],
        product_id: int,
        product_name: str,
        category: str = "",
    ) -> None:
        """Record a product view in session memory for personalization."""
        ctx = self.get(session_id, user_id)
        viewed: list = ctx.get("recently_viewed", [])
        # Avoid duplicates – remove existing entry for same product_id
        viewed = [v for v in viewed if v.get("product_id") != product_id]
        viewed.insert(0, {
            "product_id": product_id,
            "product_name": product_name,
            "category": category,
            "timestamp": time.time(),
        })
        ctx["recently_viewed"] = viewed[:15]  # keep last 15
        self.set(session_id, user_id, ctx)

    def get_recently_viewed(
        self,
        session_id: str,
        user_id: Optional[int] = None,
        limit: int = 5,
    ) -> list[dict]:
        """Return the user's recently viewed products from memory."""
        ctx = self.get(session_id, user_id)
        return (ctx.get("recently_viewed") or [])[:limit]

    def track_category_browse(
        self,
        session_id: str,
        user_id: Optional[int],
        category_name: str,
    ) -> None:
        """Record a category the user browsed."""
        ctx = self.get(session_id, user_id)
        cats: list = ctx.get("browsed_categories", [])
        if category_name not in cats:
            cats.append(category_name)
        ctx["browsed_categories"] = cats[-8:]  # keep last 8 categories
        self.set(session_id, user_id, ctx)

    def get_browsed_categories(
        self,
        session_id: str,
        user_id: Optional[int] = None,
    ) -> list[str]:
        """Return categories the user has browsed."""
        ctx = self.get(session_id, user_id)
        return (ctx.get("browsed_categories") or [])

    def save_context_from_entities(
        self,
        entities: dict,
        session_id: str,
        user_id: Optional[int] = None,
    ) -> None:
        """Persist extracted entities for next turn."""
        ctx_keys = {
            "category_id", "category_name", "brands", "budget_max",
            "budget_min", "price_range", "product_keywords", "language",
            "size",
        }
        to_save = {k: v for k, v in entities.items() if k in ctx_keys and v}
        if to_save:
            self.update(session_id, user_id, to_save)
