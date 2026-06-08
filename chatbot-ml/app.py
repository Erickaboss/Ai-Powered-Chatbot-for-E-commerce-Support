"""
Flask API for the production chatbot ML backend.

Endpoints
---------
GET  /health                  – liveness + model stats
POST /predict                 – SVM intent + language detection
POST /predict/all             – compatibility alias for /predict
GET  /models/performance      – full model metrics
GET  /intents                 – intent tag list
POST /chat                    – full pipeline:
                                  entity extraction → recommendation engine
                                  → Gemini formatter → structured response
                                  (with session memory across turns)

MySQL: host=localhost, user=root, password='', db=ecommerce_chatbot
Gemini: os.environ.get('GEMINI_API_KEY')
"""

import json
import logging
import os
import pickle
import re
import warnings
from typing import Any

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s %(message)s")
logger = logging.getLogger("app")

import numpy as np
from flask import Flask, jsonify, request
from flask_cors import CORS

from dataset_utils import load_merged_intents
from entity_extractor import BRANDS, CATEGORY_MAP, extract_entities_combined

extract_entities = extract_entities_combined
from language_detector import detect_language

from chatbot.memory import ChatMemory
from chatbot.recommender import (
    INFO_INTENTS,
    PRODUCT_INTENTS,
    _format_rwf,
    fetch_recommendations,
    fetch_popular_products,
    fetch_customers_also_bought,
    fetch_recommendations_by_history,
    fetch_user_cart,
    fetch_accessories_for_category,
)
from chatbot.semantic_search import semantic_search, is_available as semantic_available, rebuild_index as rebuild_semantic_index
from chatbot.bert_classifier import predict_bert, is_available as bert_available
from chatbot.spell_corrector import correct_message
from chatbot.response_generator import (
    GEMINI_API_KEY as _GEMINI_KEY,
    build_price_label,
    detect_price_qualifier,
    format_response_with_gemini,
    get_quick_replies,
    get_response_for_tag,
)

warnings.filterwarnings("ignore", category=UserWarning, module="sklearn")

app = Flask(__name__)
app.secret_key = os.environ.get("FLASK_SECRET_KEY", "ecommerce-chatbot-secret")
CORS(app)

ML_API_PORT = int(os.environ.get("CHATBOT_ML_PORT", "5000"))

# ── Confidence thresholds ─────────────────────────────────────────
LOW_CONFIDENCE_THRESHOLD  = 0.35   # below → clarification
MID_CONFIDENCE_THRESHOLD  = 0.55   # below → route to Gemini

# ── DB config ───────────────────────────────────────────────────
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "ecommerce_chatbot",
    "connection_timeout": 5,
}


# ── Model loading ───────────────────────────────────────────────

logger.info("Loading SVM chatbot artifacts...")
intents_data = load_merged_intents(("dataset/intents.json", "dataset/intents_part2.json"))

try:
    with open("models/model_results.json", "r", encoding="utf-8") as f:
        _raw = json.load(f)
    model_results = _raw
except Exception as exc:
    logger.warning(f"model_results.json unavailable: {exc}")
    model_results = {
        "model_name": "SVM (Linear)",
        "accuracy": 0,
        "num_classes": len(intents_data.get("intents", [])),
        "production_note": "SVM Linear classifier deployed; metrics file not found.",
    }

label_encoder = pickle.load(open("models/label_encoder.pkl", "rb"))
tfidf = pickle.load(open("models/tfidf_vectorizer.pkl", "rb"))
svm_model = pickle.load(open("models/svm_linear.pkl", "rb"))
logger.info("Loaded production model: SVM (Linear)")

# ── Chat memory (persists context across turns) ─────────────────
chat_memory = ChatMemory()


# ── User context fetcher (for personalization) ──────────────────

def fetch_user_context(user_id, db_config=None):
    """Fetch user profile and purchase history for Gemini personalization."""
    if not user_id:
        return None
    cfg = db_config or DB_CONFIG
    conn = None
    try:
        import mysql.connector
        conn = mysql.connector.connect(**cfg)
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT name, email, phone, created_at FROM users WHERE id = %s", (user_id,))
        user = cur.fetchone()
        if not user:
            return None
        cur.execute("SELECT COUNT(*) as cnt, COALESCE(SUM(total_price),0) as spent FROM orders WHERE user_id = %s AND status != 'cancelled'", (user_id,))
        orders = cur.fetchone()
        cur.execute("""SELECT p.name FROM order_items oi
                       JOIN orders o ON oi.order_id = o.id
                       JOIN products p ON oi.product_id = p.id
                       WHERE o.user_id = %s
                       ORDER BY o.created_at DESC LIMIT 5""", (user_id,))
        past = [r['name'] for r in cur.fetchall()]

        # ── Customer segment (VIP / Regular / New) ──
        segment = "New"
        total_spent = float(orders["spent"]) if orders and orders["spent"] else 0
        if total_spent >= 500000:
            segment = "VIP"
        elif total_spent >= 200000:
            segment = "Regular"

        # ── Recently viewed products from DB ──
        recently_viewed = []
        cur.execute("""
            SELECT p.id, p.name, p.price, c.name AS category
            FROM product_views pv
            JOIN products p ON p.id = pv.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE pv.user_id = %s
            ORDER BY pv.viewed_at DESC
            LIMIT 5
        """, (user_id,))
        for rv in cur.fetchall():
            recently_viewed.append({
                "product_id": rv["id"],
                "product_name": rv["name"],
                "price": float(rv["price"]) if rv["price"] else 0,
                "category": rv["category"] or "",
            })

        # ── Wishlist/favorite categories ──
        fav_categories = []
        cur.execute("""
            SELECT c.name, COUNT(*) as cnt
            FROM wishlists w
            JOIN products p ON p.id = w.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE w.user_id = %s
            GROUP BY c.name
            ORDER BY cnt DESC
            LIMIT 3
        """, (user_id,))
        for fc in cur.fetchall():
            fav_categories.append(fc["name"])

        cur.close()

        return {
            "name": user.get("name", "").split()[0] if user.get("name") else None,
            "orders_count": orders["cnt"] if orders else 0,
            "total_spent": total_spent,
            "past_purchases": past,
            "customer_segment": segment,
            "recently_viewed": recently_viewed,
            "favorite_categories": fav_categories,
            "total_orders": orders["cnt"] if orders else 0,
        }
    except Exception as exc:
        logger.error(f"user context error: {exc}")
        return None
    finally:
        if conn:
            try: conn.close()
            except Exception: pass


# ── SVM helpers ─────────────────────────────────────────────────

def predict_svm(text: str):
    vec = tfidf.transform([text.lower()])
    pred = svm_model.predict(vec)[0]
    if hasattr(svm_model, "predict_proba"):
        confidence = float(np.max(svm_model.predict_proba(vec)[0]))
    else:
        scores = svm_model.decision_function(vec)[0]
        exp_scores = np.exp(scores - np.max(scores))
        probs = exp_scores / exp_scores.sum()
        confidence = float(np.max(probs))
    intent = label_encoder.inverse_transform([pred])[0]
    return intent, confidence


# ── /chat pipeline ──────────────────────────────────────────────

@app.route("/chat", methods=["POST"])
def chat():
    """
    Full pipeline endpoint with session memory:
      1. Validate input
      2. Detect language
      3. Load / enrich context from previous turns (ChatMemory)
      4. SVM intent classification
      5. Entity extraction (regex + spaCy if available)
      6. Merge context into entities
      7. Recommendation engine (MySQL)
      8. Save context for next turn
      9. Gemini formatter
     10. Return structured JSON response
    """
    body = request.get_json(force=True, silent=True) or {}
    message = (body.get("message") or "").strip()
    session_id = (body.get("session_id") or "").strip()
    user_id = body.get("user_id")

    if not message:
        return jsonify({"error": "No message provided"}), 400

    # Step 0 – Spelling correction (correct typos before any processing)
    all_cat_kws = list({kw for kws in CATEGORY_MAP.values() for kw in kws})
    corrected = correct_message(
        message,
        brands=BRANDS,
        category_keywords=all_cat_kws,
        db_config=DB_CONFIG,
    )
    if corrected != message:
        logger.info(f"spell-corrected: %s -> %s", message, corrected)
        message = corrected

    # Step 1 – Language detection
    language = detect_language(message)

    # Step 2 – SVM intent classification
    intent, confidence = predict_svm(message)

    # Step 2b – BERT fallback: if SVM confidence is very low, try DistilBERT
    model_used = "SVM (Linear)"
    if confidence < LOW_CONFIDENCE_THRESHOLD and bert_available():
        bert_intent, bert_conf = predict_bert(message)
        if bert_conf > confidence and bert_intent != "unknown":
            intent, confidence = bert_intent, bert_conf
            model_used = "DistilBERT (fallback)"
            logger.info("BERT fallback: SVM=%.3f -> BERT=%.3f (%s)", confidence, bert_conf, bert_intent)

    # Step 2c – Pre-process message for SVM: replace budget numbers with "under [AMT]"
    preprocessed = re.sub(r'\b(\d{2,}k?)\b', r'under \1', message)
    if preprocessed != message:
        intent2, conf2 = predict_svm(preprocessed)
        if intent2 == "budget_search" and intent != "budget_search":
            intent, confidence = intent2, conf2

    # Step 3 – Entity extraction
    entities = extract_entities(message)

    # Step 3b – Override intent if entity extractor found a budget but SVM didn't detect it
    BUDGET_INTENTS = {"budget_search", "budget_query", "category_browse", "product_search"}
    if entities.get("budget_max") and intent not in BUDGET_INTENTS and confidence < 0.6:
        intent = "budget_search"
        confidence = max(confidence, 0.55)

    low_confidence_response = None

    if confidence < LOW_CONFIDENCE_THRESHOLD:
        # Very low confidence — ask clarification instead of guessing
        lang = detect_language(message)
        if lang == "french":
            clarification = (
                "Je ne suis pas sûr de comprendre votre demande. "
                "Pourriez-vous préciser ? Par exemple : "
                "\"Je cherche un téléphone Samsung sous 200k\" ou "
                "\"Montrez-moi des laptops abordables\"."
            )
        elif lang == "kinyarwanda":
            clarification = (
                "Sinumva neza icyo mushaka. "
                "Mwashobora gusobanura? Urugero: "
                "\"Ndashaka telefoni ya Samsung munsi ya 200k\" cyangwa "
                "\"Mbereke laptop zihendutse\"."
            )
        else:
            clarification = (
                "I'm not quite sure what you're looking for. Could you clarify? "
                "For example: \"Show me Samsung phones under 200k\" or "
                "\"I need an affordable laptop for work\"."
            )
        low_confidence_response = {
            "response": clarification,
            "intent": intent,
            "confidence": round(confidence, 4),
            "language": language,
            "entities": {},
            "products": [],
            "popular_products": [],
            "customers_also_bought": [],
            "quick_replies": ["Show me phones", "Show me laptops", "I have a budget", "Contact support"],
            "model_used": "SVM (low confidence → clarification)",
            "session_memory": bool(session_id),
            "user_authenticated": bool(user_id),
            "price_label": "",
        }

    elif confidence < MID_CONFIDENCE_THRESHOLD:
        # Medium confidence — flag for Gemini to handle (set intent to unknown so Gemini takes over)
        intent = "unknown"
        confidence = confidence  # keep original for logging

    # Step 3c – Price qualifier detection (under/around/exact/none)
    qualifier = detect_price_qualifier(message)
    entities["price_qualifier"] = qualifier
    price_label = build_price_label(
        entities.get("budget_min"), entities.get("budget_max"), qualifier
    )
    entities["price_label"] = price_label

    # Return early if SVM confidence is too low (clarification response)
    if low_confidence_response is not None:
        return jsonify(low_confidence_response)

    # Step 4 – Enrich entities with context from previous turns
    entities = chat_memory.merge_context_into_entities(entities, session_id, user_id)

    # ── Track category browsing in memory for history-based recommendations ──
    if entities.get("category_name"):
        chat_memory.track_category_browse(session_id, user_id, entities["category_name"])

    # ── Fetch recently viewed products from DB & memory ──
    recently_viewed_from_db = []
    recently_viewed_from_memory = chat_memory.get_recently_viewed(session_id, user_id, 3)
    browsed_categories = chat_memory.get_browsed_categories(session_id, user_id)

    # Step 5 – Recommendation engine (only for product-related intents)
    products: list[dict] = []
    popular_products: list[dict] = []
    also_bought: list[dict] = []
    history_products: list[dict] = []
    semantic_results: list[dict] = []
    search_method = "keyword"

    needs_products = (
        intent in PRODUCT_INTENTS
        or entities.get("category_id") is not None
        or entities.get("brands")
        or entities.get("budget_max") is not None
        or entities.get("product_keywords")
    )
    if needs_products:
        # Primary: keyword/filter-based search
        products = fetch_recommendations(entities, intent, db_config=DB_CONFIG)

        # Semantic search fallback: if keyword search returns < 3 results,
        # use sentence-transformer embeddings to find semantically similar products
        if len(products) < 3 and semantic_available():
            semantic_results = semantic_search(
                query=message,
                top_k=8,
                min_similarity=0.25,
                db_config=DB_CONFIG,
                category_id=entities.get("category_id"),
                budget_max=entities.get("budget_max"),
            )
            if semantic_results:
                # Merge: add semantic results not already in keyword results
                existing_ids = {p["id"] for p in products}
                for sr in semantic_results:
                    if sr["id"] not in existing_ids:
                        products.append(sr)
                        existing_ids.add(sr["id"])
                search_method = "semantic" if not products else "hybrid"
                products = products[:8]

        # Popularity ranking — top ordered products in same category/budget
        popular_products = fetch_popular_products(
            category_id=entities.get("category_id"),
            budget_max=entities.get("budget_max"),
            limit=5,
            db_config=DB_CONFIG,
        )

        # "Customers also bought" — if we found at least one product, use the top result
        if products:
            top_product_id = products[0].get("id")
            if top_product_id:
                also_bought = fetch_customers_also_bought(
                    product_id=top_product_id,
                    limit=4,
                    db_config=DB_CONFIG,
                )

    # ── History-based recommendations: if no explicit product filters, use past browsing ──
    if not needs_products and browsed_categories:
        history_products = fetch_recommendations_by_history(
            history_categories=browsed_categories,
            limit=4,
            db_config=DB_CONFIG,
        )

    # Step 6 – Save context for next turn (including browsing history)
    chat_memory.save_context_from_entities(entities, session_id, user_id)
    chat_memory.update(session_id, user_id, {
        "language": language,
        "last_intent": intent,
    })

    # ── Track shown products in memory for guest personalization ──
    for p in products[:5]:
        chat_memory.track_product_view(
            session_id=session_id,
            user_id=user_id,
            product_id=p.get("id", 0),
            product_name=p.get("name", ""),
            category=p.get("category", ""),
        )

    # Step 7 – Fallback SVM response
    fallback = get_response_for_tag(intent, intents_data)

    # Step 7b – Fetch user context for personalization
    user_context = fetch_user_context(user_id, DB_CONFIG)

    # ── Personalization for guests (from session memory) ──
    if not user_id:
        has_rv = any(v.get("product_name") for v in recently_viewed_from_memory)
        user_context = {
            "recently_viewed_memory": recently_viewed_from_memory if has_rv else [],
            "browsed_categories": browsed_categories or [],
        }

    # ── Cart awareness: fetch user's cart for cross-sell suggestions ──
    cart_products = []
    if user_id:
        cart_products = fetch_user_cart(user_id, DB_CONFIG)
    if cart_products and user_context is None:
        user_context = {}
    if cart_products and user_context is not None:
        user_context["cart_items"] = cart_products
        # Find cross-sell accessories for first cart item's category
        cat_id = cart_products[0].get("category_id")
        if cat_id:
            exclude_id = cart_products[0].get("product_id") or cart_products[0].get("id")
            accessories = fetch_accessories_for_category(cat_id, exclude_product_id=exclude_id, db_config=DB_CONFIG, limit=3)
            if accessories:
                user_context["cross_sell"] = accessories

    # Step 8 – Gemini formatter (with personalization + store knowledge)
    gemini_api_key = _GEMINI_KEY
    if gemini_api_key and (products or intent not in INFO_INTENTS):
        formatted = format_response_with_gemini(
            user_message=message,
            intent=intent,
            entities=entities,
            products=products,
            language=language,
            fallback_response=fallback,
            user_context=user_context,
            db_config=DB_CONFIG,
        )
    else:
        formatted = fallback

    # Step 9 – Build response
    response_payload: dict[str, Any] = {
        "response": formatted,
        "intent": intent,
        "confidence": round(confidence, 4),
        "language": language,
        "entities": entities,
        "price_label": price_label,
        "products": [
            {
                "id": p.get("id"),
                "name": p.get("name"),
                "brand": p.get("brand"),
                "price": p.get("price"),
                "price_formatted": _format_rwf(p.get("price")),
                "stock": p.get("stock"),
                "in_stock": bool(p.get("stock", 0) > 0),
                "category": p.get("category"),
                "description": p.get("description"),
            }
            for p in products
        ],
        "quick_replies": get_quick_replies(intent),
        "model_used": model_used + (" + Gemini 1.5 Flash" if gemini_api_key else ""),
        "session_memory": bool(session_id),
        "user_authenticated": bool(user_id and user_context),
        "search_method": search_method,
        "popular_products": [
            {
                "id": p.get("id"),
                "name": p.get("name"),
                "brand": p.get("brand"),
                "price": p.get("price"),
                "price_formatted": _format_rwf(p.get("price")),
                "stock": p.get("stock"),
                "in_stock": bool(p.get("stock", 0) > 0),
                "category": p.get("category"),
                "order_count": p.get("order_count", 0),
            }
            for p in popular_products
        ],
        "customers_also_bought": [
            {
                "id": p.get("id"),
                "name": p.get("name"),
                "brand": p.get("brand"),
                "price": p.get("price"),
                "price_formatted": _format_rwf(p.get("price")),
                "stock": p.get("stock"),
                "in_stock": bool(p.get("stock", 0) > 0),
                "category": p.get("category"),
                "co_count": p.get("co_count", 0),
            }
            for p in also_bought
        ],
        "history_products": [
            {
                "id": p.get("id"),
                "name": p.get("name"),
                "brand": p.get("brand"),
                "price": p.get("price"),
                "price_formatted": _format_rwf(p.get("price")),
                "stock": p.get("stock"),
                "in_stock": bool(p.get("stock", 0) > 0),
                "category": p.get("category"),
            }
            for p in history_products
        ],
        "recently_viewed_products": recently_viewed_from_db,
    }

    return jsonify(response_payload)


# ── Existing endpoints (preserved) ──────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "model": "SVM (Linear)",
        "accuracy": round(float(model_results.get("test_accuracy") or model_results.get("accuracy") or 0), 4),
        "intents": len(intents_data.get("intents", [])),
        "classes": model_results.get("num_classes"),
        "features": model_results.get("vectorizer", {}).get("vocabulary_size"),
        "version": model_results.get("model_version", "5.0.0"),
        "python": __import__("sys").version.split()[0],
        "gemini_enabled": bool(_GEMINI_KEY),
    })


@app.route("/predict", methods=["POST"])
def predict():
    body = request.get_json(force=True)
    message = body.get("message", "").strip()
    if not message:
        return jsonify({"error": "No message provided"}), 400

    from chatbot.spell_corrector import correct_message
    from entity_extractor import BRANDS, CATEGORY_MAP
    all_cat_kws = list({kw for kws in CATEGORY_MAP.values() for kw in kws})
    original = message
    corrected = correct_message(message, brands=BRANDS, category_keywords=all_cat_kws, db_config=DB_CONFIG)
    if corrected != message:
        message = corrected

    intent, confidence = predict_svm(message)
    return jsonify({
        "intent": intent,
        "confidence": round(confidence, 4),
        "response": get_response_for_tag(intent, intents_data),
        "model_used": "SVM (Linear)",
        "language": detect_language(message),
        "corrected_message": corrected if corrected != original else None,
    })


@app.route("/predict/all", methods=["POST"])
def predict_all():
    body = request.get_json(force=True)
    message = body.get("message", "").strip()
    if not message:
        return jsonify({"error": "No message provided"}), 400

    intent, confidence = predict_svm(message)
    return jsonify({
        "message": message,
        "production_model": "SVM (Linear)",
        "predictions": {
            "SVM (Linear)": {
                "intent": intent,
                "confidence": round(confidence, 4),
            }
        },
    })


@app.route("/predict/ensemble", methods=["POST"])
def predict_ensemble():
    """
    Runs SVM + BERT ensemble for intent classification.
    BERT is used as a fallback when SVM confidence is below threshold.
    Returns only intent + confidence (no recommendation pipeline).
    """
    body = request.get_json(force=True, silent=True) or {}
    message = body.get("message", "").strip()
    if not message:
        return jsonify({"error": "No message provided"}), 400

    from chatbot.spell_corrector import correct_message
    from entity_extractor import BRANDS, CATEGORY_MAP
    all_cat_kws = list({kw for kws in CATEGORY_MAP.values() for kw in kws})
    original = message
    corrected = correct_message(message, brands=BRANDS, category_keywords=all_cat_kws, db_config=DB_CONFIG)
    if corrected != message:
        message = corrected

    intent, confidence = predict_svm(message)
    model_used = "SVM (Linear)"

    if confidence < LOW_CONFIDENCE_THRESHOLD and bert_available():
        bert_intent, bert_conf = predict_bert(message)
        if bert_conf > confidence and bert_intent != "unknown":
            intent, confidence = bert_intent, bert_conf
            model_used = "DistilBERT (fallback)"

    from chatbot.response_generator import detect_sentiment
    sentiment_label, sentiment_score = detect_sentiment(message)

    return jsonify({
        "intent": intent,
        "confidence": round(confidence, 4),
        "model_used": model_used,
        "language": detect_language(message),
        "corrected_message": corrected if corrected != original else None,
        "sentiment_label": sentiment_label,
        "sentiment_score": round(float(sentiment_score), 4),
    })


@app.route("/models/performance", methods=["GET"])
def performance():
    return jsonify(model_results)


@app.route("/intents", methods=["GET"])
def intents():
    return jsonify([
        {"tag": i.get("tag"), "patterns": i.get("patterns", [])[:3]}
        for i in intents_data.get("intents", [])
    ])


@app.route("/semantic-search", methods=["POST"])
def semantic_search_endpoint():
    """
    Standalone semantic search endpoint.
    POST body: {"query": "cheap gaming laptop", "top_k": 8, "budget_max": 200000}

    Returns products ranked by semantic similarity — finds matches even when
    exact keywords don't overlap (e.g. "cheap" matches "affordable").
    """
    body = request.get_json(force=True, silent=True) or {}
    query = (body.get("query") or "").strip()
    if not query:
        return jsonify({"error": "No query provided"}), 400

    if not semantic_available():
        return jsonify({
            "error": "Semantic search unavailable — install sentence-transformers: pip install sentence-transformers",
            "available": False,
        }), 503

    results = semantic_search(
        query=query,
        top_k=int(body.get("top_k", 8)),
        min_similarity=float(body.get("min_similarity", 0.25)),
        db_config=DB_CONFIG,
        category_id=body.get("category_id"),
        budget_max=body.get("budget_max"),
    )

    return jsonify({
        "query": query,
        "results": [
            {
                "id": p.get("id"),
                "name": p.get("name"),
                "brand": p.get("brand"),
                "price": p.get("price"),
                "price_formatted": _format_rwf(p.get("price")),
                "stock": p.get("stock"),
                "in_stock": bool(p.get("stock", 0) > 0),
                "category": p.get("category"),
                "similarity_score": p.get("similarity_score"),
            }
            for p in results
        ],
        "count": len(results),
        "available": True,
    })


@app.route("/rebuild-index", methods=["POST"])
def rebuild_index_endpoint():
    """
    Rebuild the semantic search product embedding index.
    Call this after adding new products to the database.
    POST body: {} (no parameters needed)
    """
    if not semantic_available():
        return jsonify({
            "error": "Semantic search unavailable — install sentence-transformers",
            "available": False,
        }), 503

    try:
        count = rebuild_semantic_index(DB_CONFIG)
        return jsonify({
            "success": True,
            "products_indexed": count,
            "message": f"Semantic index rebuilt with {count} products.",
        })
    except Exception as exc:
        return jsonify({"error": str(exc)}), 500


# ── Entry point ─────────────────────────────────────────────────

if __name__ == "__main__":
    logger.info("Starting Flask ML API on http://localhost:%d", ML_API_PORT)
    app.run(debug=False, host="0.0.0.0", port=ML_API_PORT)
