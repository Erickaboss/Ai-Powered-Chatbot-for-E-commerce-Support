"""
Advanced product recommendation engine.

Queries MySQL using extracted entities with:
- Stock-weighted ordering (in-stock first)
- Price relevance scoring (closest to target budget ranks higher)
- Exact product name matching support
- "Around" budget support (price within ±10% of target)
- Cross-category budget search
- Full product descriptions included
"""

import re
from typing import Any, Optional

import mysql.connector

from chatbot.memory import get_default_db_config

# Intent tags that should trigger product recommendations
PRODUCT_INTENTS = {
    "product_search", "product_recommendation", "product_inquiry",
    "price_inquiry", "budget_query", "category_browse", "budget_search",
    "show_products", "search_products", "find_products",
    "phones", "laptops", "electronics", "fashion", "groceries",
    "appliances", "beauty", "sports", "baby", "furniture",
}

# Intents that are purely informational (no DB lookup needed)
INFO_INTENTS = {
    "greeting", "goodbye", "thanks", "help", "about",
    "shipping_info", "return_policy", "payment_methods",
    "contact_support", "faq", "hours",
}


def _format_rwf(amount) -> str:
    try:
        return f"RWF {int(amount):,}"
    except (TypeError, ValueError):
        return str(amount)


def _get_db(db_config: Optional[dict] = None):
    cfg = db_config or get_default_db_config()
    return mysql.connector.connect(**cfg)


def _score_product(
    product: dict,
    target_price: Optional[float] = None,
    qualifier: str = "none",
) -> float:
    """
    Score a product for relevance ranking.
    Higher score = more relevant.

    Factors:
    - In stock: +50
    - Price within 10% of target budget: +30
    - Price exactly at target (for 'exact' qualifier): +40
    - Lower price (budget-conscious): + up to 20
    """
    score = 0.0
    stock = int(product.get("stock", 0))
    price = float(product.get("price", 0))

    if stock > 0:
        score += 50.0

    # ── Best-value bonus: rating / price ratio ──
    rating = float(product.get("avg_rating") or 0)
    if rating > 0 and price > 0:
        value_ratio = rating / (price / 1000)  # rating per thousand RWF
        score += min(30.0, value_ratio * 10)

    if target_price and target_price > 0:
        ratio = price / target_price
        if qualifier == "exact" and abs(ratio - 1.0) < 0.05:
            score += 40.0
        elif qualifier == "around":
            if 0.9 <= ratio <= 1.1:
                score += 30.0
            elif ratio < 0.9:
                score += 20.0 * (ratio / 0.9)
        elif qualifier == "over":
            if ratio >= 1.0:
                score += 20.0
            elif ratio >= 0.8:
                score += 10.0
        else:  # under / none
            if ratio <= 1.0:
                score += 15.0
            if ratio <= 1.0:
                score += 10.0 * (1.0 - ratio)

    return score


def query_products_by_name(
    search_terms: list[str],
    db_config: Optional[dict] = None,
    limit: int = 8,
) -> list[dict]:
    if not search_terms:
        return []
    conn = None
    try:
        conn = _get_db(db_config)
        cursor = conn.cursor(dictionary=True)
        conditions = []
        params = []
        for term in search_terms[:5]:
            conditions.append("LOWER(p.name) LIKE %s")
            params.append(f"%{term}%")
        where = " AND ".join(conditions)
        cursor.execute(
            f"""
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.avg_rating
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {where}
            ORDER BY p.stock DESC, p.avg_rating DESC, p.price ASC
            LIMIT %s
            """,
            [*params, limit],
        )
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except Exception as exc:
        print(f"[recommender] name query error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def fetch_recommendations(
    entities: dict,
    intent: str,
    limit: int = 8,
    db_config: Optional[dict] = None,
) -> list[dict]:
    conn = None
    try:
        conn = _get_db(db_config)
        cursor = conn.cursor(dictionary=True)

        conditions: list[str] = []
        params: list[Any] = []

        if entities.get("category_id"):
            conditions.append("p.category_id = %s")
            params.append(entities["category_id"])
        elif entities.get("category_name"):
            conditions.append("c.name LIKE %s")
            params.append(f"%{entities['category_name']}%")

        if entities.get("brands"):
            if entities["brands"]:
                brand_conditions = " OR ".join(
                    [f"(LOWER(p.brand) LIKE %s OR LOWER(p.name) LIKE %s)" for _ in entities["brands"]]
                )
                conditions.append(f"({brand_conditions})")
                for b in entities["brands"]:
                    params.extend([f"%{b}%", f"%{b}%"])

        if entities.get("budget_max"):
            conditions.append("p.price <= %s")
            params.append(entities["budget_max"])
        if entities.get("budget_min"):
            conditions.append("p.price >= %s")
            params.append(entities["budget_min"])
        if entities.get("rating_min"):
            conditions.append("p.avg_rating >= %s")
            params.append(entities["rating_min"])

        has_category = bool(entities.get("category_id") or entities.get("category_name"))
        has_brand = bool(entities.get("brands"))
        use_keyword_filter = bool(entities.get("product_keywords")) and (has_category or has_brand)

        keyword_conditions = []
        keyword_params = []
        if use_keyword_filter:
            kparts = []
            for kw in entities["product_keywords"][:3]:
                kparts.append(
                    "(LOWER(p.name) LIKE %s OR LOWER(p.description) LIKE %s)"
                )
                keyword_params.extend([f"%{kw}%", f"%{kw}%"])
            if kparts:
                keyword_conditions.append(f"({' OR '.join(kparts)})")

        def _run_query(extra_conditions, extra_params):
            all_conditions = conditions + extra_conditions
            all_params = params + extra_params
            where_clause = "WHERE " + " AND ".join(all_conditions) if all_conditions else ""
            sql = f"""
                SELECT p.id, p.name, p.brand, p.price, p.stock,
                       p.description, c.name AS category, p.avg_rating
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                {where_clause}
                ORDER BY p.stock DESC, p.avg_rating DESC, p.price ASC
                LIMIT %s
            """
            c = conn.cursor(dictionary=True)
            c.execute(sql, [*all_params, limit * 2])
            r = c.fetchall()
            c.close()
            return r

        rows = _run_query(keyword_conditions, keyword_params)

        if not rows and use_keyword_filter:
            rows = _run_query([], [])

        cursor.close()

        if not rows:
            return []

        qualifier = entities.get("price_qualifier", "none")
        target = entities.get("budget_max") or entities.get("budget_min")
        target_price = float(target) if target else None
        scored = [
            (p, _score_product(p, target_price=target_price, qualifier=qualifier))
            for p in rows
        ]
        scored.sort(key=lambda x: -x[1])
        return [p for p, s in scored[:limit]]

    except Exception as exc:
        print(f"[recommender] DB error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def fetch_popular_products(
    category_id: Optional[int] = None,
    budget_max: Optional[float] = None,
    limit: int = 8,
    db_config: Optional[dict] = None,
) -> list[dict]:
    """
    Return products ranked by order popularity (most ordered first).
    Optionally filtered by category and budget.
    Falls back to stock-ordered results if order_items table is empty.
    """
    conn = None
    try:
        conn = _get_db(db_config)
        cursor = conn.cursor(dictionary=True)

        conditions = ["p.stock > 0"]
        params: list[Any] = []

        if category_id:
            conditions.append("p.category_id = %s")
            params.append(category_id)
        if budget_max:
            conditions.append("p.price <= %s")
            params.append(budget_max)

        where = "WHERE " + " AND ".join(conditions)

        sql = f"""
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.avg_rating,
                   COALESCE(oi.order_count, 0) AS order_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, COUNT(*) AS order_count
                FROM order_items
                GROUP BY product_id
            ) oi ON oi.product_id = p.id
            {where}
            ORDER BY order_count DESC, p.stock DESC, p.avg_rating DESC, p.price ASC
            LIMIT %s
        """
        params.append(limit)
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except Exception as exc:
        print(f"[recommender] popular products error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def fetch_customers_also_bought(
    product_id: int,
    limit: int = 5,
    db_config: Optional[dict] = None,
) -> list[dict]:
    conn = None
    try:
        conn = _get_db(db_config)
        cursor = conn.cursor(dictionary=True)

        sql = """
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.avg_rating,
                   COUNT(*) AS co_count
            FROM order_items oi_other
            JOIN products p ON p.id = oi_other.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE oi_other.order_id IN (
                SELECT DISTINCT order_id
                FROM order_items
                WHERE product_id = %s
            )
            AND oi_other.product_id != %s
            AND p.stock > 0
            GROUP BY p.id, p.name, p.brand, p.price, p.stock, p.description, c.name, p.avg_rating
            ORDER BY co_count DESC, p.stock DESC, p.avg_rating DESC
            LIMIT %s
        """
        cursor.execute(sql, [product_id, product_id, limit])
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except Exception as exc:
        print(f"[recommender] also bought error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def fetch_recommendations_by_history(
    history_categories: list[str],
    limit: int = 5,
    db_config: Optional[dict] = None,
) -> list[dict]:
    if not history_categories:
        return []
    conn = None
    try:
        conn = _get_db(db_config)
        cursor = conn.cursor(dictionary=True)
        placeholders = ", ".join(["%s"] * len(history_categories))
        cursor.execute(
            f"""
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.avg_rating
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE c.name IN ({placeholders}) AND p.stock > 0
            ORDER BY p.avg_rating DESC, RAND()
            LIMIT %s
            """,
            [*history_categories, limit],
        )
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except Exception as exc:
        print(f"[recommender] history DB error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def build_product_context(products: list[dict]) -> str:
    """Serialize product list into a full detail text block for Gemini."""
    if not products:
        return "No matching products found in the database."
    lines = []
    for i, p in enumerate(products, 1):
        stock_label = (
            f"In Stock ({p['stock']})" if p.get("stock", 0) > 0 else "Out of Stock"
        )
        desc = ""
        if p.get("description"):
            desc = re.sub(r"<[^>]+>", "", str(p["description"])).strip()
        line = (
            f"#{i} {p['name']}"
            + (f" (Brand: {p['brand']})" if p.get("brand") else "")
            + f" | Price: {_format_rwf(p['price'])}"
            + f" | {stock_label}"
            + (f" | Category: {p.get('category', '')}" if p.get("category") else "")
            + (f" | Description: {desc}" if desc else "")
        )
        lines.append(line)
    return "\n".join(lines)


def get_product_count(db_config: Optional[dict] = None) -> int:
    """Return total number of products in the database."""
    try:
        conn = _get_db(db_config)
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM products")
        count = cur.fetchone()[0]
        cur.close()
        conn.close()
        return count
    except Exception:
        return 0


def get_category_summary(db_config: Optional[dict] = None) -> list[dict]:
    """Return count of products per category."""
    try:
        conn = _get_db(db_config)
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT c.id, c.name, COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY c.id
        """)
        rows = cur.fetchall()
        cur.close()
        conn.close()
        return rows
    except Exception:
        return []


# ── Cart & cross-sell helpers ────────────────────────────────────────────────

def fetch_user_cart(
    user_id: int,
    db_config: Optional[dict] = None,
) -> list[dict]:
    conn = None
    try:
        conn = _get_db(db_config)
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT ci.product_id, ci.quantity,
                   p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category, p.category_id
            FROM cart_items ci
            JOIN carts ca ON ca.id = ci.cart_id
            JOIN products p ON p.id = ci.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ca.user_id = %s AND ca.status = 'active'
        """, (user_id,))
        rows = cur.fetchall()
        cur.close()
        return rows
    except Exception as exc:
        print(f"[recommender] cart fetch error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


def fetch_accessories_for_category(
    category_id: int,
    exclude_product_id: Optional[int] = None,
    limit: int = 4,
    db_config: Optional[dict] = None,
) -> list[dict]:
    ACCESSORY_KWS = {
        1: ["charger", "case", "cover", "earphone", "headphone", "screen protector",
            "cable", "power bank", "holder", "stand", "phone case", "tempered glass",
            "bluetooth", "speaker", "adapter", "earbud", "airpod"],
        2: ["charger", "case", "bag", "laptop bag", "sleeve", "mouse", "keyboard",
            "cooling pad", "stand", "adapter", "hub", "webcam"],
        3: ["hdmi cable", "remote", "mount", "stand", "wall mount", "soundbar",
            "speaker", "antenna"],
        4: ["warranty", "cleaning", "filter", "part"],
        11: ["cushion", "cover", "cleaner", "polish", "lamp"],
    }
    kws = ACCESSORY_KWS.get(category_id, [])
    if not kws:
        return []
    conn = None
    try:
        conn = _get_db(db_config)
        cur = conn.cursor(dictionary=True)
        likes = " OR ".join(["LOWER(p.name) LIKE %s" for _ in kws])
        params = [f"%{kw}%" for kw in kws]
        exclude = ""
        if exclude_product_id:
            exclude = "AND p.id != %s"
            params.append(exclude_product_id)
        cur.execute(f"""
            SELECT p.id, p.name, p.brand, p.price, p.stock,
                   p.description, c.name AS category
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ({likes}) AND p.stock > 0 {exclude}
            ORDER BY p.stock DESC, p.price ASC
            LIMIT %s
        """, [*params, limit])
        rows = cur.fetchall()
        cur.close()
        return rows
    except Exception as exc:
        print(f"[recommender] accessories error: {exc}")
        return []
    finally:
        if conn:
            try: conn.close()
            except Exception: pass
