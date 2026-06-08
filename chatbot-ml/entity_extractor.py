"""
Entity Extractor for E-commerce Chatbot
Extracts product names, brands, prices, categories, and budget from messages.

Supports two backends:
  1. Regex + keyword dictionaries (fast, no deps) — always available
  2. spaCy NLP (more robust, understands unseen patterns) — optional
"""

import re
from typing import Optional

# ── Optional spaCy NLP backend ──────────────────────────────────
try:
    import spacy

    _SPACY_AVAILABLE = False
    _nlp = None

    for model_name in ("en_core_web_sm", "en_core_web_md"):
        try:
            _nlp = spacy.load(model_name)
            _SPACY_AVAILABLE = True
            print(f"[entity_extractor] spaCy loaded: {model_name}")
            break
        except OSError:
            continue
except ImportError:
    _SPACY_AVAILABLE = False
    _nlp = None

# ── Known brands in the store ──
BRANDS = [
    "samsung", "apple", "nokia", "tecno", "infinix", "xiaomi", "oppo",
    "vivo", "hp", "dell", "lenovo", "asus", "acer", "lg", "sony", "jbl", "nike",
    "adidas", "huawei", "bose", "philips", "panasonic", "dyson", "kenwood", "casio",
    "fossil", "lego", "pampers", "nivea", "dove", "garnier", "colgate", "gillette",
    "maybelline", "nescafe", "lipton", "heinz", "coca-cola", "indomie", "itel",
    "motorola", "realme", "oneplus", "google", "microsoft", "logitech", "canon",
    "nikon", "hisense", "tcl", "haier", "midea", "ramtons", "bruhm", "nexus",
]

# ── Category keywords mapped to category IDs ──
CATEGORY_MAP = {
    1:  ["phone", "phones", "mobile", "smartphone", "smartphones", "iphone", "android",
         "tablet", "galaxy", "camon", "spark", "telefoni", "simu"],
    2:  ["laptop", "laptops", "computer", "computers", "pc", "macbook", "notebook",
         "chromebook", "desktop", "mudasobwa"],
     3:  ["tv", "television", "speaker", "speakers", "headphone", "headphones", "audio",
          "earphone", "subwoofer", "soundbar", "televiziyo", "electronics"],
    4:  ["fridge", "washing machine", "microwave", "appliance", "appliances", "cooker",
         "kettle", "blender", "iron", "vacuum", "oven", "freezer", "ibikoresho"],
     5:  ["men shirt", "men trouser", "men suit", "men shoe", "men fashion", "fashion",
          "men cloth", "men wear", "menswear", "gents", "abagabo", "imyambarire"],
    6:  ["women dress", "handbag", "heels", "ladies", "women fashion", "women cloth",
         "skirt", "blouse", "womenswear", "abagore"],
     7:  ["food", "grocery", "groceries", "rice", "milk", "coffee", "tea", "sugar",
          "ibiribwa", "ibiryo", "amavuta", "impamba", "ibijumbwe", "ibinyobwa"],
    8:  ["beauty", "skincare", "lotion", "shampoo", "perfume", "cream", "makeup",
         "cosmetic", "kwisiga"],
    9:  ["sport", "sports", "gym", "fitness", "football", "running", "yoga", "siporo"],
    10: ["baby", "kids", "child", "toy", "toys", "diaper", "umwana", "abana"],
    11: ["furniture", "sofa", "bed", "table", "chair", "wardrobe", "decor", "meuble"],
    12: ["car", "vehicle", "tyre", "auto", "imodoka"],
    13: ["book", "pen", "stationery", "school", "ibitabo"],
    14: ["watch", "jewelry", "ring", "necklace", "bracelet", "isaha", "bijou"],
    15: ["game", "gaming", "playstation", "xbox", "console", "imikino"],
}


def _parse_amount(token: str) -> Optional[int]:
    """Convert '50k', '50,000', '50000' → integer."""
    token = token.replace(",", "").strip().lower()
    m = re.match(r"^(\d+(?:\.\d+)?)k$", token)
    if m:
        return int(float(m.group(1)) * 1000)
    m = re.match(r"^(\d+)m(?:illion)?$", token)
    if m:
        return int(m.group(1)) * 1_000_000
    try:
        return int(float(token))
    except ValueError:
        return None


# ── spaCy entity extraction ─────────────────────────────────────

def extract_entities_spacy(message: str) -> dict:
    """
    Use spaCy NER to extract named entities (MONEY, PRODUCT, ORG, GPE).

    Returns a dict with spaCy-specific fields merged on top of the
    standard entity schema. Only called if spaCy is available.
    """
    if not _SPACY_AVAILABLE or _nlp is None:
        return {}

    doc = _nlp(message)

    spacy_entities: dict = {
        "spacy_orgs": [],
        "spacy_money": [],
        "spacy_products": [],
        "spacy_gpe": [],
    }

    for ent in doc.ents:
        label = ent.label_
        text = ent.text.strip().lower()
        if label == "ORG":
            spacy_entities["spacy_orgs"].append(text)
        elif label == "MONEY":
            spacy_entities["spacy_money"].append(text)
            # Try to extract a numeric value
            nums = re.findall(r'[\d,]+', text)
            for n in nums:
                val = int(n.replace(",", ""))
                if val >= 1000 and not spacy_entities.get("budget_max_spacy"):
                    spacy_entities["budget_max_spacy"] = val
        elif label == "PRODUCT":
            spacy_entities["spacy_products"].append(text)
        elif label in ("GPE", "LOC"):
            spacy_entities["spacy_gpe"].append(text)

    # Extract brand-like named entities (common brands are often ORG)
    for ent in doc.ents:
        if ent.label_ == "ORG":
            brand_lower = ent.text.strip().lower()
            if brand_lower in BRANDS:
                spacy_entities.setdefault("brands_spacy", []).append(brand_lower)

    return spacy_entities


def extract_entities_combined(message: str) -> dict:
    """
    Combined entity extraction: runs spaCy (if available) then regex,
    merging spaCy findings into the standard entity schema.

    spaCy provides better understanding of unseen patterns and
    named entities; regex provides reliable keyword matching.
    """
    entities = extract_entities(message)

    if _SPACY_AVAILABLE:
        spacy_ents = extract_entities_spacy(message)

        # Merge spaCy money into budget if regex didn't find one
        if not entities.get("budget_max") and spacy_ents.get("budget_max_spacy"):
            entities["budget_max"] = spacy_ents["budget_max_spacy"]

        # Merge spaCy orgs into brands if they match known brands
        if not entities.get("brands") and spacy_ents.get("brands_spacy"):
            entities["brands"] = spacy_ents["brands_spacy"]

        # Use spaCy product labels as extra keywords
        # Skip anything that looks like a budget amount (e.g. "500k", "50,000")
        if spacy_ents.get("spacy_products"):
            existing_kw = set(entities.get("product_keywords", []))
            for prod in spacy_ents["spacy_products"]:
                if re.search(r'\d', prod):
                    continue
                if prod not in existing_kw:
                    entities["product_keywords"].append(prod)

    return entities


def extract_entities(message: str) -> dict:
    """
    Extract entities from a user message.

    Returns:
        {
            "brands": [...],
            "category_id": int | None,
            "category_name": str | None,
            "budget_max": int | None,
            "budget_min": int | None,
            "price_range": [min, max] | None,
            "product_keywords": [...],
            "quantity": int | None,
        }
    """
    ml = message.lower().strip()

    entities = {
        "brands": [],
        "category_id": None,
        "category_name": None,
        "budget_max": None,
        "budget_min": None,
        "price_range": None,
        "product_keywords": [],
        "quantity": None,
        "rating_min": None,
        "size": None,
    }

    # ── Brand extraction ──
    for brand in BRANDS:
        if re.search(r'\b' + re.escape(brand) + r'\b', ml):
            entities["brands"].append(brand)

    # ── Category extraction ──
    for cat_id, keywords in CATEGORY_MAP.items():
        for kw in keywords:
            if kw in ml:
                entities["category_id"] = cat_id
                # Map ID to name
                cat_names = {
                    1: "Smartphones & Tablets", 2: "Laptops & Computers",
                    3: "TVs & Audio", 4: "Home Appliances", 5: "Men's Fashion",
                    6: "Women's Fashion", 7: "Groceries", 8: "Beauty & Health",
                    9: "Sports & Fitness", 10: "Baby & Kids", 11: "Furniture",
                    12: "Car Accessories", 13: "Books & Stationery",
                    14: "Watches & Jewelry", 15: "Gaming",
                }
                entities["category_name"] = cat_names.get(cat_id, f"Category {cat_id}")
                break
        if entities["category_id"]:
            break

    # ── Price range extraction (between X and Y) ──
    range_patterns = [
        r'between\s+([\d,]+k?)\s+and\s+([\d,]+k?)',
        r'from\s+([\d,]+k?)\s+to\s+([\d,]+k?)',
        r'([\d,]+k?)\s*(?:to|-)\s*([\d,]+k?)\s*(?:rwf|frw)?',
    ]
    for pat in range_patterns:
        m = re.search(pat, ml)
        if m:
            lo = _parse_amount(m.group(1))
            hi = _parse_amount(m.group(2))
            if lo and hi and lo < hi:
                entities["budget_min"] = lo
                entities["budget_max"] = hi
                entities["price_range"] = [lo, hi]
                break

    # ── Single budget / max price extraction ──
    if not entities["budget_max"]:
        budget_patterns = [
            r'(?:under|below|less than|cheaper than|maximum|max|up to|within|at most)\s*([\d,]+k?)',
            r'(?:i have|my budget is?|budget of|spending limit|i can spend|i can afford|i only have)\s*([\d,]+k?)',
            r'(?:products?|items?|phones?|laptops?|tvs?|watches?)\s+(?:under|below|for)\s+([\d,]+k?)',
            r'(?:what can i (?:get|buy) for)\s+([\d,]+k?)',
        ]
        for pat in budget_patterns:
            m = re.search(pat, ml)
            if m:
                val = _parse_amount(m.group(1))
                if val and val >= 1000:
                    entities["budget_max"] = val
                    break

    # ── Fallback: standalone number with k-suffix (e.g. "phones 110k", "110k") ──
    # Skip years (2000-2099) and unrealistically small budgets (< 500)
    if not entities["budget_max"]:
        fallback = re.search(r'(?:^|\s)(\d{2,}k?)(?:\s|$)', ml)
        if fallback:
            val = _parse_amount(fallback.group(1))
            if val and val >= 500 and not (2000 <= val <= 2099):
                entities["budget_max"] = val

    # ── Quantity extraction ──
    qty_m = re.search(r'\b(\d+)\s*(?:units?|pieces?|pcs?|items?)\b', ml)
    if qty_m:
        entities["quantity"] = int(qty_m.group(1))

    # ── Rating extraction (e.g. "4 star", "4+ stars", "rated 4.5", "highly rated") ──
    rating_patterns = [
        (r'(?:rated?\s*)?(\d+(?:\.\d+)?)\s*\+?\s*(?:stars?|rating|out of 5)', True),
        (r'(\d+(?:\.\d+)?)\s*\+?\s*stars?\s*(?:and|or)?\s*above', True),
        (r'(?:highly?|top|best)\s*rated', False),
    ]
    for pat, has_num in rating_patterns:
        m = re.search(pat, ml)
        if m:
            val = float(m.group(1)) if has_num else 4.0
            if 1.0 <= val <= 5.0:
                entities["rating_min"] = val
                break
    # Fallback: explicit "rated" keyword without number
    if not entities["rating_min"] and re.search(r'\b(?:highly?|top)\s*rated\b', ml):
        entities["rating_min"] = 4.0

    # ── Size extraction (e.g. "size 42", "size XL", "large size", "shoe size 9") ──
    size_patterns = [
        r'(?:size|shoe size)\s*(\d+(?:\.\d+)?)',
        r'(?:size)\s*(x[slm]|s|m|l|xl|xxl|xxxl)',
        r'\b(\d+(?:\.\d+)?)\s*(?:cm|mm|inches?|inch)\b',
    ]
    for pat in size_patterns:
        m = re.search(pat, ml, re.IGNORECASE)
        if m:
            entities["size"] = m.group(1).lower()
            break
    # Size words used standalone (e.g. "small", "medium", "large")
    if not entities["size"]:
        size_words = re.search(r'\b(small|medium|large|x[slm]|s|m|l|xl|xxl|xxxl)\b', ml, re.IGNORECASE)
        if size_words:
            entities["size"] = size_words.group(1).lower()

    # ── Volume / weight measurement extraction (e.g. "2L", "500ml", "2kg") ──
    # These are common in grocery and product names (Inyange Milk 1L, Cooking Oil 2L)
    vol_weight = re.search(r'\b(\d+(?:\.\d+)?)\s*(l|ml|cl|kg|g|oz|lb|litre|liter|litres|liters|kgs|gram|grams)\b', ml, re.IGNORECASE)
    if vol_weight:
        measurement = vol_weight.group(0).lower().strip()
        entities["size"] = measurement
        # Also add as product keyword so it's used in SQL LIKE search
        if measurement not in entities["product_keywords"]:
            entities["product_keywords"].append(measurement)

    # ── Lemmatization (via spaCy) for keyword extraction ──
    ml_lemmatized = ml
    if _SPACY_AVAILABLE and _nlp is not None:
        try:
            doc = _nlp(ml)
            lemmatized_tokens = [token.lemma_.lower() for token in doc]
            ml_lemmatized = " ".join(lemmatized_tokens)
        except Exception:
            pass  # fall back to raw text if lemmatization fails

    # ── Product keywords (non-stopword meaningful words) ──
    stopwords = {
        "show", "me", "find", "get", "i", "want", "need", "looking", "for",
        "a", "an", "the", "please", "can", "you", "do", "have", "any",
        "what", "which", "is", "are", "best", "good", "nice", "cheap",
        "affordable", "product", "products", "item", "items", "something",
        "under", "below", "above", "between", "and", "or", "with", "in",
        "my", "your", "our", "their", "this", "that", "these", "those",
        # Kinyarwanda stopwords
        "nshaka", "ndashaka", "shaka", "amafaranga", "ibicuruzwa", "murakoze",
        "muraho", "mwaramutse", "mwiriwe", "yego", "oya", "bite", "amakuru",
        "kugura", "gufata", "ibintu", "ninde", "iki", "aho", "igihe", "uburyo",
        "gusura", "gutura", "kigali", "rwanda", "ni", "na", "ku", "mu", "bya",
        "cya", "rya", "kwa", "mwa", "nka", "uba", "ari", "byo", "ayo", "izi",
        "iyi", "izo", "uru", "aka", "utu", "ubu", "ibi", "abo", "nta", "nti",
        "si", "nda", "njy", "wewe", "cyangwa", "ariko", "rero", "none", "ubu",
        "vuba", "kandi", "nanone", "hakaba", "nkaba", "ndaba", "baba", "bari",
        # French stopwords
        "bonjour", "merci", "produit", "prix", "livraison", "commande",
        "paiement", "retour", "aide", "comment", "combien", "quoi", "quel",
        "quelle", "quels", "quelles", "acheter", "vendre", "disponible",
        "garantie", "remboursement", "frais", "gratuit", "rapide", "lent",
        "je", "tu", "il", "elle", "nous", "vous", "ils", "elles", "le", "la",
        "les", "des", "un", "une", "du", "de", "dans", "sur", "avec", "pour",
        "par", "est", "sont", "a", "ont", "été", "était", "ce", "cette", "ces",
        # Budget qualifier words that should never be product keywords
        "around", "about", "approximately", "roughly", "near", "almost",
        # Generic product-type words (too broad for keyword filtering)
        "shoes", "shoe", "bag", "bags", "set", "sets", "pack", "packs", "bottle", "bottles",
        # Kinyarwanda budget/price words
        "igera", "kugeza", "hasi", "hejuru", "hagati", "gukoresha",
        # Additional French price/search stopwords
        "sous", "moins", "plus", "tres", "peu", "assez", "trop", "aussi",
        "mon", "ma", "mes", "ton", "ta", "tes", "son", "sa", "ses",
        "tout", "tous", "toute", "toutes", "autre", "chaque", "certain",
        "entre", "jusque", "depuis", "chez", "sans", "pendant", "apres",
        "avant", "trouver", "cherche", "chercher", "voir", "avoir", "faire",
    }
    words = re.findall(r'\b[a-z]{3,}\b', ml)
    lemmatized_words = re.findall(r'\b[a-z]{3,}\b', ml_lemmatized)
    # Use lemmatized forms for matching, but deduplicate against raw words
    keywords = [w for w in lemmatized_words if w not in stopwords and w not in BRANDS]
    # Remove category keywords too (use substring match: e.g. "telephones" contains "phone")
    all_cat_kws = {kw for kws in CATEGORY_MAP.values() for kw in kws}
    keywords = [w for w in keywords if not any(cat_kw in w for cat_kw in all_cat_kws)]
    entities["product_keywords"] = keywords[:5]

    return entities
