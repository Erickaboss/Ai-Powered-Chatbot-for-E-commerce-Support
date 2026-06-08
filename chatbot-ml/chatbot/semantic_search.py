"""
Semantic Product Search using Sentence Transformers + FAISS.

Instead of keyword matching (LIKE '%gaming%'), this module embeds both the
user query and product names/descriptions into dense vectors and uses FAISS
for fast nearest-neighbour search.

Example:
  "cheap gaming laptop" → matches "Affordable ASUS TUF Gaming Laptop"
  even though no exact words overlap.

Index is auto-detected as stale when the product count in the DB changes.
"""

from __future__ import annotations

import json
import os
import pickle
import re
import time
from typing import Any, Optional

import numpy as np

try:
    from sentence_transformers import SentenceTransformer
    _ST_AVAILABLE = True
except ImportError:
    _ST_AVAILABLE = False
    SentenceTransformer = None  # type: ignore[assignment]

try:
    import faiss
    _FAISS_AVAILABLE = True
except ImportError:
    _FAISS_AVAILABLE = False
    faiss = None  # type: ignore[assignment]

# ── Config ───────────────────────────────────────────────────────────────────
_MODEL_NAME = "all-MiniLM-L6-v2"

_BASE_DIR = os.path.join(os.path.dirname(__file__), "..", "models")
_FAISS_PATH = os.path.join(_BASE_DIR, "product_embeddings.faiss")
_META_PATH  = os.path.join(_BASE_DIR, "product_embeddings_meta.pkl")
_CACHE_PATH = os.path.join(_BASE_DIR, "product_embeddings.pkl")   # legacy fallback

_STALE_CHECK_INTERVAL = 300  # seconds between DB product-count checks

_model: Optional[object] = None
_index: Optional[Any] = None       # FAISS IndexIDMap
_metadata: dict[int, dict] = {}    # product_id → {name, brand, price, …}
_cached_product_count: int = 0
_last_stale_check: float = 0


# ── helpers ──────────────────────────────────────────────────────────────────

def _get_model():
    global _model
    if _model is None and _ST_AVAILABLE:
        try:
            _model = SentenceTransformer(_MODEL_NAME)
        except Exception as exc:
            print(f"[semantic_search] Failed to load model: {exc}")
            _model = None
    return _model


def _normalize(vectors: np.ndarray) -> np.ndarray:
    """L2-normalize rows in-place and return."""
    faiss.normalize_L2(vectors)
    return vectors


# ── index build / save / load ────────────────────────────────────────────────

def build_product_index(products: list[dict]) -> tuple[Any, dict[int, dict]]:
    """
    Build a FAISS index (IDMap, FlatIP) from a list of product dicts.

    Returns (faiss.Index, metadata_dict).
    Returns (None, {}) if sentence-transformers is unavailable.
    """
    assert _FAISS_AVAILABLE, "faiss is required to build an index"
    model = _get_model()
    if model is None:
        return None, {}

    texts: list[str] = []
    meta: dict[int, dict] = {}

    for p in products:
        parts = [p.get("name", "")]
        if p.get("brand"):
            parts.append(p["brand"])
        if p.get("description"):
            desc = re.sub(r"<[^>]+>", "", str(p["description"]))[:200]
            parts.append(desc)
        if p.get("category"):
            parts.append(p["category"])
        texts.append(" ".join(parts))
        meta[p["id"]] = {
            "id": p["id"],
            "name": p.get("name"),
            "brand": p.get("brand"),
            "price": p.get("price"),
            "stock": p.get("stock"),
            "category": p.get("category"),
            "category_id": p.get("category_id"),
            "description": p.get("description"),
        }

    try:
        emb = model.encode(texts, batch_size=64, show_progress_bar=False)  # (N, D)
        emb = np.asarray(emb, dtype=np.float32)
        _normalize(emb)

        dim = emb.shape[1]
        index = faiss.IndexIDMap(faiss.IndexFlatIP(dim))
        ids = np.array(list(meta.keys()), dtype=np.int64)
        index.add_with_ids(emb, ids)
        return index, meta
    except Exception as exc:
        print(f"[semantic_search] Build error: {exc}")
        return None, {}


def _meta_path() -> str:
    """Return metadata path; prefer JSON over legacy pickle format."""
    json_path = _META_PATH.replace(".pkl", ".json")
    if os.path.exists(json_path):
        return json_path
    return _META_PATH


def save_product_index(index: Any, meta: dict[int, dict]) -> None:
    """Write FAISS index + metadata to disk (JSON, safe)."""
    os.makedirs(_BASE_DIR, exist_ok=True)
    if index is not None:
        faiss.write_index(index, _FAISS_PATH)
    json_path = _META_PATH.replace(".pkl", ".json")
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump({
            "metadata": {str(k): v for k, v in meta.items()},
            "product_count": len(meta),
            "last_rebuilt": time.time(),
        }, f, ensure_ascii=False)
    # Remove legacy pickle metadata if exists
    if os.path.exists(_META_PATH) and _META_PATH != json_path:
        try:
            os.remove(_META_PATH)
        except OSError:
            pass


def load_product_index() -> bool:
    """
    Load FAISS index + metadata from disk into globals.
    Returns True if the index was loaded (or already cached).
    """
    global _index, _metadata, _cached_product_count
    if _index is not None:
        return True

    if os.path.exists(_FAISS_PATH):
        meta_file = _meta_path()
        if os.path.exists(meta_file):
            try:
                _index = faiss.read_index(_FAISS_PATH)
                if meta_file.endswith(".json"):
                    with open(meta_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    raw = data.get("metadata", {})
                    _metadata = {int(k): v for k, v in raw.items()}
                else:
                    with open(meta_file, "rb") as f:
                        data = pickle.load(f)
                    _metadata = data.get("metadata", {})
                _cached_product_count = data.get("product_count", 0)
                return True
            except Exception as exc:
                print(f"[semantic_search] Load error: {exc}")

    # Legacy fallback – try old monolithic .pkl and migrate
    if os.path.exists(_CACHE_PATH):
        return _migrate_legacy_index()

    return False


def _migrate_legacy_index() -> bool:
    """Convert the old pickle-only index to FAISS format."""
    print("[semantic_search] Migrating legacy index → FAISS …")
    try:
        with open(_CACHE_PATH, "rb") as f:
            old = pickle.load(f)  # {pid: {embedding, name, …}}
        products = []
        for pid, data in old.items():
            products.append({
                "id": pid,
                "name": data.get("name"),
                "brand": data.get("brand"),
                "price": data.get("price"),
                "stock": data.get("stock"),
                "category": data.get("category"),
                "category_id": data.get("category_id"),
                "description": data.get("description"),
            })
        index, meta = build_product_index(products)
        if index is not None:
            save_product_index(index, meta)
            load_product_index()
            print(f"[semantic_search] Migrated {len(meta)} products to FAISS")
            return True
    except Exception as exc:
        print(f"[semantic_search] Migration failed: {exc}")
    return False


# ── staleness detection ──────────────────────────────────────────────────────

def _product_count_from_db(db_config: dict) -> int:
    """Return count of in-stock products from MySQL."""
    try:
        import mysql.connector
        conn = mysql.connector.connect(**db_config)
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM products WHERE stock > 0")
        row = cur.fetchone()
        cur.close()
        conn.close()
        return row[0] if row else 0
    except Exception:
        return 0


def is_index_stale(db_config: dict, force: bool = False) -> bool:
    """
    Compare the product count from the DB against the cached index count.
    Only performs the DB query every *STALE_CHECK_INTERVAL* seconds unless forced.
    """
    global _last_stale_check, _cached_product_count
    now = time.time()
    if not force and (now - _last_stale_check) < _STALE_CHECK_INTERVAL:
        return False
    _last_stale_check = now
    db_count = _product_count_from_db(db_config)
    return db_count != _cached_product_count


# ── public search API ────────────────────────────────────────────────────────

def semantic_search(
    query: str,
    top_k: int = 8,
    min_similarity: float = 0.25,
    db_config: Optional[dict] = None,
    category_id: Optional[int] = None,
    budget_max: Optional[float] = None,
) -> list[dict]:
    """
    Find products semantically similar to the query using FAISS.

    Args:
        query: User's natural language query
        top_k: Max results to return
        min_similarity: Minimum cosine similarity threshold (0–1)
        db_config: MySQL config (for index building / staleness check)
        category_id: Optional category filter (applied post-FAISS)
        budget_max: Optional price ceiling (applied post-FAISS)

    Returns:
        List of product dicts sorted by similarity (highest first).
        Empty list if sentence-transformers / FAISS is unavailable.
    """
    global _index, _metadata, _cached_product_count

    if not _ST_AVAILABLE or not _FAISS_AVAILABLE:
        return []

    model = _get_model()
    if model is None:
        return []

    # ── ensure index is loaded (build from DB if missing) ──
    if not load_product_index() and db_config:
        products = _fetch_all_products(db_config)
        if products:
            idx, meta = build_product_index(products)
            if idx is not None:
                save_product_index(idx, meta)
                _index, _metadata = idx, meta
                _cached_product_count = len(meta)

    if _index is None or not _metadata:
        return []

    # ── auto-rebuild if stale ──
    if db_config and is_index_stale(db_config):
        print("[semantic_search] Index stale — rebuilding …")
        idx, meta = build_product_index(_fetch_all_products(db_config))
        if idx is not None:
            save_product_index(idx, meta)
            _index, _metadata = idx, meta
            _cached_product_count = len(meta)

    # ── embed query ──
    try:
        query_vec = model.encode([query], show_progress_bar=False)
        query_vec = np.asarray(query_vec, dtype=np.float32)
        _normalize(query_vec)
    except Exception as exc:
        print(f"[semantic_search] Query embedding error: {exc}")
        return []

    # ── FAISS search (oversample for post-filtering) ──
    n_total = _index.ntotal
    probe_k = min(n_total, max(top_k * 3, 30))

    try:
        sims, ids = _index.search(query_vec, probe_k)
    except Exception as exc:
        print(f"[semantic_search] FAISS search error: {exc}")
        return []

    # ── apply category / budget filters ──
    scored: list[tuple[float, dict]] = []
    for sim, pid in zip(sims[0], ids[0]):
        if pid == -1:
            continue
        data = _metadata.get(int(pid))
        if data is None:
            continue
        if category_id is not None and data.get("category_id") != category_id:
            continue
        if budget_max is not None and data.get("price") and float(data["price"]) > budget_max:
            continue
        if float(sim) < min_similarity:
            continue
        scored.append((float(sim), data))

    scored.sort(key=lambda x: (-x[0], -(x[1].get("stock") or 0)))

    return [
        {
            "id": d["id"],
            "name": d["name"],
            "brand": d["brand"],
            "price": d["price"],
            "stock": d["stock"],
            "category": d["category"],
            "description": d["description"],
            "similarity_score": round(sim, 4),
        }
        for sim, d in scored[:top_k]
    ]


# ── DB helpers ───────────────────────────────────────────────────────────────

def _fetch_all_products(db_config: dict) -> list[dict]:
    """Fetch all in-stock products from MySQL."""
    try:
        import mysql.connector
        conn = mysql.connector.connect(**db_config)
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.category_id
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock > 0
        """)
        rows = cur.fetchall()
        cur.close()
        conn.close()
        return rows
    except Exception as exc:
        print(f"[semantic_search] DB fetch error: {exc}")
        return []


# ── management ───────────────────────────────────────────────────────────────

def rebuild_index(db_config: dict) -> int:
    """
    Rebuild the FAISS index from scratch and persist to disk.
    Returns the number of products indexed (0 on failure).
    """
    global _index, _metadata, _cached_product_count

    if not _ST_AVAILABLE or not _FAISS_AVAILABLE:
        return 0

    products = _fetch_all_products(db_config)
    if not products:
        return 0

    idx, meta = build_product_index(products)
    if idx is None:
        return 0

    save_product_index(idx, meta)
    _index = idx
    _metadata = meta
    _cached_product_count = len(meta)
    return len(meta)


def is_available() -> bool:
    """Return True if the full pipeline (sentence-transformers + FAISS) is ready."""
    return _ST_AVAILABLE and _FAISS_AVAILABLE and _get_model() is not None
