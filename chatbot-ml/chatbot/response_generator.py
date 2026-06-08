"""
Response generator — formats chatbot replies.

Combines product data, user context, and full store knowledge
with Gemini natural language to produce conversational, human-like
responses. Falls back to SVM intent responses when Gemini is unavailable.
"""

import json
import os
import random
import re
import sys
from typing import Optional

import requests

from chatbot.recommender import build_product_context, get_category_summary, get_product_count

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY")
if not GEMINI_API_KEY:
    print("[response_generator] WARNING: GEMINI_API_KEY not set — falling back to SVM template responses", file=sys.stderr)

GEMINI_URL = (
    "https://generativelanguage.googleapis.com/v1beta/models/"
    "gemini-1.5-flash:generateContent"
)

_SYSTEM_PROMPT = (
    "You are a friendly, helpful e-commerce assistant for ShopAI Rwanda, an online store in Rwanda. "
    "You have expert knowledge of the entire product catalog and store policies. "
    "You respond in the same language the customer used (English, French, or Kinyarwanda). "
    "Keep responses concise, warm, and actionable. "
    "Use bullet points or short paragraphs. "
    "When listing products, include name, price in RWF, and stock status. "
    "Never invent products — only use the product data provided to you. "
    "If the customer seems frustrated or upset, apologize sincerely and offer to connect them with a human agent. "
    "If you don't know something, be honest and suggest what the customer can do next."
)


def build_store_knowledge_block(db_config: Optional[dict] = None) -> str:
    """Build a store knowledge summary block for Gemini with category stats."""
    total = get_product_count(db_config)
    cats = get_category_summary(db_config) or []
    lines = [f"Total products: {total} across {len(cats)} categories."]
    if cats:
        lines.append("")
        lines.append("Category breakdown:")
        for c in cats:
            name = c.get("name", f"Category {c['id']}")
            count = c.get("product_count", 0)
            lines.append(f"  - {name}: {count} products")
        lines.append("")
        lines.append("The customer can browse by category, set a budget (under/around/over), "
                     "search by brand or product name, compare products, track orders, "
                     "and get delivery or payment information.")
    return "\n".join(lines)


def call_gemini(prompt: str) -> Optional[str]:
    """Call Gemini 1.5 Flash and return the text response, or None on failure."""
    if not GEMINI_API_KEY:
        return None
    try:
        payload = {
            "system_instruction": {"parts": [{"text": _SYSTEM_PROMPT}]},
            "contents": [{"parts": [{"text": prompt}]}],
            "generationConfig": {
                "temperature": 0.4,
                "maxOutputTokens": 512,
                "topP": 0.9,
            },
        }
        resp = requests.post(
            GEMINI_URL,
            params={"key": GEMINI_API_KEY},
            json=payload,
            timeout=30,
        )
        resp.raise_for_status()
        data = resp.json()
        candidates = data.get("candidates", [])
        if candidates:
            parts = candidates[0].get("content", {}).get("parts", [])
            if parts:
                return parts[0].get("text", "").strip()
    except Exception as exc:
        print(f"[gemini] error: {exc}")
    return None


def get_response_for_tag(tag: str, intents_data: Optional[dict] = None) -> str:
    """
    Return a random response from the intent tag's response list.
    Falls back to a generic message if tag is unknown.
    """
    if intents_data is None:
        from dataset_utils import load_merged_intents
        intents_data = load_merged_intents(
            ("dataset/intents.json", "dataset/intents_part2.json")
        )
    for intent in intents_data.get("intents", []):
        if intent.get("tag") == tag:
            responses = intent.get("responses", [])
            return random.choice(responses) if responses else "I can help with that."
    return "I can help with that."


def detect_sentiment(message: str) -> tuple[str, float]:
    """Simple keyword-based sentiment detection. Returns (label, score)."""
    ml = message.lower()
    negative = {
        "angry", "frustrated", "annoyed", "disappointed", "terrible", "awful",
        "bad", "worst", "hate", "useless", "broken", "wrong", "mistake",
        "complaint", "issue", "problem", "refund", "scam", "waste", "slow",
        "arafe", "ndarakaye", "bibaye", "mbi", "nibi",
    }
    positive = {
        "great", "awesome", "amazing", "love", "perfect", "excellent",
        "wonderful", "fantastic", "happy", "good", "best", "thanks",
        "murakoze", "byiza", "neza", "nibyo",
    }
    neg_count = sum(1 for w in ml.split() if w in negative)
    pos_count = sum(1 for w in ml.split() if w in positive)
    score = (pos_count - neg_count) / max(len(ml.split()), 1)
    if score < -0.15:
        return "negative", score
    if score > 0.15:
        return "positive", score
    return "neutral", score


def format_response_with_gemini(
    user_message: str,
    intent: str,
    entities: dict,
    products: list[dict],
    language: str,
    fallback_response: str,
    user_context: Optional[dict] = None,
    db_config: Optional[dict] = None,
) -> str:
    """
    Build a Gemini prompt from context and return a formatted reply.
    Includes full store knowledge for expert-level responses.
    Falls back to the provided fallback_response string if Gemini errors out.
    """
    product_context = build_product_context(products)

    lang_map = {
        "french": "Respond in French.",
        "kinyarwanda": "Respond in Kinyarwanda.",
    }
    lang_instruction = lang_map.get(language, "Respond in English.")

    # Build user context block
    user_block = ""
    if user_context:
        parts = []

        # Welcome back / returning customer greeting
        if user_context.get("name"):
            parts.append(f"Customer name: {user_context['name']}")

        # Customer segment (VIP / Regular / New)
        segment = user_context.get("customer_segment", "")
        if segment:
            parts.append(f"Customer segment: {segment}")

        if user_context.get("orders_count") is not None and user_context["orders_count"] > 0:
            parts.append(f"Total orders placed: {user_context['orders_count']}")
            if user_context.get("total_spent"):
                parts.append(f"Lifetime spend: RWF {int(user_context['total_spent']):,}")

        if user_context.get("past_purchases"):
            past = user_context["past_purchases"]
            if len(past) > 2:
                parts.append(f"Recently purchased: {', '.join(past[:3])}")
            elif past:
                parts.append(f"Previously purchased: {', '.join(past)}")

        # Favorite categories from wishlist
        if user_context.get("favorite_categories"):
            favs = user_context["favorite_categories"]
            parts.append(f"Favorite categories (saved in wishlist): {', '.join(favs)}")

        # Recently viewed products (from DB for logged-in users)
        rv = user_context.get("recently_viewed") or []
        if rv:
            rv_names = [v["product_name"] if isinstance(v, dict) and v.get("product_name") else (v["name"] if isinstance(v, dict) and v.get("name") else str(v)) for v in rv]
            parts.append(f"Recently viewed products: {', '.join(rv_names[:3])}")

        # Recently viewed from session memory (for guests)
        rv_mem = user_context.get("recently_viewed_memory") or []
        if rv_mem and not rv:
            rv_names = [v.get("product_name", "") for v in rv_mem if v.get("product_name")]
            if rv_names:
                parts.append(f"Recently viewed this session: {', '.join(rv_names[:3])}")

        # Browsed categories from session memory
        bc = user_context.get("browsed_categories") or []
        if bc:
            parts.append(f"Categories browsed this session: {', '.join(bc[:3])}")

        # Cart items for cross-sell / upsell opportunities
        cart = user_context.get("cart_items") or []
        if cart:
            cart_line = "Current cart items: " + ", ".join(
                [f"{c.get('name','')} (RWF {int(c.get('price',0)):,})" for c in cart[:3]]
            )
            parts.append(cart_line)
        cross_sell = user_context.get("cross_sell") or []
        if cross_sell:
            cs_line = "Suggested accessories for cross-sell: " + ", ".join(
                [f"{c.get('name','')} (RWF {int(c.get('price',0)):,})" for c in cross_sell[:3]]
            )
            parts.append(f"If relevant, offer to add accessories: {cs_line}")

        if parts:
            header = "CUSTOMER PROFILE (use for personalization):" if user_context.get("orders_count", 0) > 0 else "CUSTOMER PROFILE (new visitor):"
            user_block = f"{header}\n" + "\n".join(parts) + "\n\n"

    # Build store knowledge block
    store_block = build_store_knowledge_block(db_config)

    # Sentiment awareness
    sentiment_label, sentiment_score = detect_sentiment(user_message)
    sentiment_instruction = ""
    if sentiment_label == "negative":
        sentiment_instruction = (
            "The customer seems frustrated. Apologize sincerely, be extra patient, "
            "and if appropriate offer to connect them with a human support agent. "
        )
    elif sentiment_label == "positive":
        sentiment_instruction = (
            "The customer is in a good mood — match their enthusiasm warmly. "
        )

    prompt = (
        f"{lang_instruction}\n\n"
        f"{user_block}"
        f"Store knowledge:\n{store_block}\n\n"
        f"Customer message: \"{user_message}\"\n"
        f"Detected intent: {intent}\n"
        f"Extracted entities: {json.dumps(entities, ensure_ascii=False)}\n\n"
        f"Available products from our database:\n{product_context}\n\n"
        f"{sentiment_instruction}"
        "Please write a helpful, conversational reply to the customer. "
        "If products are available, list the top ones with price and stock status. "
        "If no products match, suggest alternatives or ask for clarification. "
        "Use the store knowledge to give informed recommendations — mention "
        "the category breadth or similar products the customer might like."
    )

    gemini_text = call_gemini(prompt)
    return gemini_text if gemini_text else fallback_response


# ── Quick-reply suggestions ──────────────────────────────────────

_QUICK_REPLIES_BY_INTENT: dict[str, list[str]] = {
    "greeting": ["Show me products", "What's on sale?", "Help"],
    "product_search": ["Show me phones", "Show me laptops", "Filter by budget"],
    "price_inquiry": ["Show me cheaper options", "Show me categories", "How to order?"],
    "budget_query": ["Show me phones under 100k", "Show me laptops", "Contact support"],
    "budget_search": ["Show me phones under 100k", "Show me laptops under 500k", "Filter by category"],
    "category_browse": ["Show me more", "Filter by budget", "How to order?"],
    "shipping_info": ["How to order?", "Payment methods", "Contact support"],
    "return_policy": ["Contact support", "How to order?", "Show me products"],
    "payment_methods": ["How to order?", "Show me products", "Contact support"],
    "contact_support": ["Show me products", "Track my order", "Return policy"],
    "goodbye": ["Start over", "Show me products", "Contact support"],
    "order_track": ["My orders", "Track another order", "Contact support"],
    "order_history": ["Track my latest order", "Show me products", "Contact support"],
    "complaint": ["Contact support", "Return policy", "Show me products"],
}

_DEFAULT_QUICK_REPLIES = ["Show me products", "Show me categories", "Contact support"]


def get_quick_replies(intent: str) -> list[str]:
    return _QUICK_REPLIES_BY_INTENT.get(intent, _DEFAULT_QUICK_REPLIES)


def detect_price_qualifier(message: str) -> str:
    ml = message.lower()
    if re.search(r'\b(under|below|less than|cheaper than|max|maximum|up to|at most|moins de|munsi ya|ntarenze|atarengeje|sous)\b', ml):
        return 'under'
    if re.search(r'\b(over|above|more than|at least|minimum|arenze)\b', ml):
        return 'over'
    if re.search(r'\b(about|around|approximately|approx|roughly|nearly|close to|~)\b', ml):
        return 'around'
    if re.search(r'\b(exactly|exact|precisely|igiciro\s*cya|yamafrw|ya\s*frw|ya\s*rwf|coûte|coute|vaut|prix\s*de)\b', ml):
        return 'exact'
    if re.search(r'\b(between|from.*to|range)\b', ml):
        return 'range'
    if re.search(r'\b(for|at)\s+(?:rwf|frw|amafaranga|francs?)?\s*\d', ml):
        return 'exact'
    return 'none'


def build_price_label(min_val: Optional[int], max_val: Optional[int], qualifier: str = 'none') -> str:
    if min_val is not None and max_val is not None and min_val == max_val:
        return f'at RWF {min_val:,}'
    if min_val is not None and max_val is not None:
        return f'between RWF {min_val:,} and RWF {max_val:,}'
    if max_val is not None:
        labels = {
            'under':  f'under RWF {max_val:,}',
            'around': f'around RWF {max_val:,}',
            'exact':  f'at RWF {max_val:,}',
            'over':   f'over RWF {max_val:,}',
            'range':  f'up to RWF {max_val:,}',
        }
        return labels.get(qualifier, f'up to RWF {max_val:,}')
    if min_val is not None:
        return f'from RWF {min_val:,}'
    return ''
