"""
Spell Corrector for E-commerce Chatbot.

Corrects typos in customer messages using SymSpell (dictionary-based)
with fallback to rapidfuzz fuzzy matching against known product terms.

Handles examples like:
  "samsng phne"  → "samsung phone"
  "nkie shoes"   → "nike shoes"
  "iphon 14"     → "iphone 14"
  "laptoop"      → "laptop"
  "shampo"       → "shampoo"
"""

from __future__ import annotations

import re
from typing import Optional

# ── SymSpell (fast dictionary-based spelling correction) ─────
try:
    from symspellpy import SymSpell, Verbosity
    _SYMSPELL_AVAILABLE = True
except ImportError:
    _SYMSPELL_AVAILABLE = False
    SymSpell = None  # type: ignore[assignment]
    Verbosity = None  # type: ignore[assignment]

# ── rapidfuzz (fuzzy string matching fallback) ────────────────
try:
    from rapidfuzz import fuzz, process
    _FUZZ_AVAILABLE = True
except ImportError:
    _FUZZ_AVAILABLE = False
    fuzz = None  # type: ignore[assignment]
    process = None  # type: ignore[assignment]

# ── Common misspellings → correct forms ───────────────────────
# These override both SymSpell and rapidfuzz, catching cases
# where the edit distance is small but SymSpell's deletion-based
# metric can't find the correct word (e.g. "fone" → "phone").
_COMMON_MISSPELLINGS = {
    "fone": "phone", "fon": "phone", "phne": "phone",
    "earfones": "earphones", "earfone": "earphone",
    "samsng": "samsung", "laptoop": "laptop",
    "computr": "computer", "chargher": "charger",
    "shampo": "shampoo", "shoos": "shoes",
    "nkie": "nike", "iphon": "iphone",
    "androide": "android",
    "wht": "what", "wat": "what",
    "wher": "where", "wen": "when", "wy": "why",
    "hw": "how", "hw much": "how much",
    "ndshaka": "ndshaka",  # keep this Kinyarwanda word
}

# ── Known brands, categories, and common English words ────────

_COMMON_WORDS = {
    # Modifiers / qualifiers
    "new", "old", "big", "small", "large", "tiny", "huge", "mini", "max",
    "red", "blue", "black", "white", "green", "yellow", "gold", "silver",
    "pink", "purple", "gray", "grey", "brown", "orange", "navy",
    "pro", "plus", "ultra", "lite", "air", "max", "slim", "led", "lcd",
    "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten",
    "first", "second", "last", "next", "previous", "other", "another",
    "this", "that", "these", "those", "any", "some", "many", "much", "few",
    "here", "there", "where", "what", "which", "who", "when", "why", "how",
    "all", "every", "each", "both", "either", "neither",
    # Articles / prepositions / conjunctions
    "the", "a", "an", "in", "on", "at", "to", "for", "of", "with",
    "by", "from", "up", "down", "into", "onto", "upon", "out",
    "and", "or", "but", "nor", "yet", "so", "as", "if", "than",
    "about", "above", "across", "after", "against", "along", "among",
    "around", "before", "behind", "below", "beneath", "beside",
    "between", "beyond", "during", "except", "inside", "outside",
    "over", "through", "throughout", "toward", "under", "until",
    # Pronouns / possessives
    "i", "you", "he", "she", "it", "we", "they", "me", "him", "her",
    "us", "them", "my", "your", "his", "its", "our", "their",
    "mine", "yours", "hers", "ours", "theirs", "myself",
    "yourself", "himself", "herself", "itself", "ourselves",
    # Verbs (common)
    "is", "are", "was", "were", "been", "being", "have", "has", "had",
    "having", "do", "does", "did", "doing", "make", "makes", "made",
    "making", "get", "gets", "got", "getting", "use", "uses", "used",
    "using", "want", "wants", "wanted", "wanting", "need", "needs",
    "needed", "needing", "look", "looks", "looked", "looking",
    "find", "finds", "found", "finding", "tell", "tells", "told",
    "show", "shows", "showed", "showing", "give", "gives", "gave",
    "giving", "take", "takes", "took", "taking", "buy", "buys",
    "bought", "buying", "sell", "sells", "sold", "selling",
    "help", "helps", "helped", "helping", "work", "works", "worked",
    "working", "know", "knows", "knew", "knowing", "think",
    "thinks", "thought", "thinking", "see", "sees", "saw",
    "seeing", "come", "comes", "came", "coming", "go", "goes",
    "went", "going", "gone", "say", "says", "said", "saying",
    "please", "thank", "thanks", "welcome",
    # Adjectives / adverbs
    "good", "better", "best", "bad", "worse", "worst",
    "big", "bigger", "biggest", "small", "smaller", "smallest",
    "cheap", "cheaper", "cheapest", "expensive", "dear",
    "affordable", "reasonable", "fair", "great", "nice", "cool",
    "awesome", "amazing", "perfect", "excellent", "wonderful",
    "fast", "faster", "fastest", "quick", "quickly", "slow",
    "easy", "hard", "simple", "difficult", "possible",
    "high", "low", "top", "bottom", "front", "back",
    "left", "right", "middle", "center", "side",
    "full", "empty", "open", "closed", "available",
    "online", "offline", "local", "global", "daily",
    "weekly", "monthly", "yearly", "current", "recent",
    "popular", "trending", "latest", "newest", "oldest",
    "special", "limited", "exclusive", "extra", "additional",
    "free", "paid", "premium", "basic", "standard",
    "delivery", "shipping", "pickup", "return", "refund",
    "exchange", "warranty", "guarantee", "support",
    "original", "genuine", "authentic", "fake", "copy",
    "indoor", "outdoor", "portable", "foldable", "wireless",
    "bluetooth", "wifi", "usb", "hdmi", "input", "output",
    "digital", "analog", "automatic", "manual", "smart",
    "touch", "screen", "display", "button", "switch",
    "size", "weight", "color", "material", "design",
    # Numbers / quantities
    "zero", "hundred", "thousand", "million", "billion",
    "half", "quarter", "third", "fourth", "fifth",
    "single", "double", "triple", "pair", "set", "pack",
    "dozen", "bunch", "lot", "loads", "tons",
    "piece", "pieces", "unit", "units", "item", "items",
    "count", "number", "amount", "total", "sum",
    # Time
    "today", "tomorrow", "yesterday", "now", "later",
    "soon", "immediately", "urgent", "emergency",
    "morning", "afternoon", "evening", "night",
    "hour", "hours", "minute", "minutes", "day", "days",
    "week", "weeks", "month", "months", "year", "years",
    "time", "times", "while", "during", "until", "since",
    # Questions / help
    "help", "assist", "support", "question", "questions",
    "problem", "issue", "error", "bug", "trouble",
    "how", "what", "where", "when", "why", "which",
    "who", "whom", "whose", "explain", "describe",
    "close", "open", "start", "stop", "begin", "end",
    # Modal / auxiliary verbs
    "can", "cannot", "cant", "could", "would", "should", "shall",
    "will", "may", "might", "must", "need", "dare", "ought",
    "am", "are", "is", "was", "were", "be", "been", "being",
    "do", "does", "did", "done", "doing",
    "have", "has", "had", "having",
    # Contractions (without apostrophe)
    "dont", "doesnt", "didnt", "wont", "wouldnt", "shouldnt",
    "cant", "couldnt", "havent", "hasnt", "hadnt", "isnt",
    "arent", "wasnt", "werent", "neednt", "mustnt",
    "im", "youre", "hes", "shes", "its", "were", "theyre",
    "ive", "youve", "weve", "theyve",
    "ill", "youll", "hell", "shell", "well", "theyll",
    # Common typos for stopwords (these are already in stopword list)
    "pls", "plz", "thx", "tnx", "ty", "yw", "np",
    "u", "ur", "urself", "bcoz", "bcuz", "cos", "cuz",
    "abt", "wrt", "wud", "shud", "cud",
    # Shopping / e-commerce
    "cart", "wishlist", "order", "orders", "checkout",
    "payment", "pay", "paid", "cash", "credit", "debit",
    "mobile", "money", "mtn", "airtel", "bk", "equity",
    "stock", "store", "shop", "seller", "vendor",
    "address", "location", "store", "shop", "market",
    "seller", "vendor", "brand", "quality", "condition",
    "discount", "offer", "deal", "promo", "coupon", "voucher",
    "catalog", "catalogue", "catalog", "category", "categories",
    "search", "browse", "filter", "sort", "view",
    "account", "login", "sign", "register", "profile",
    "contact", "call", "phone", "email", "message", "chat",
    "price", "prices", "pricing", "cost", "costs", "budget",
    "range", "max", "min", "limit", "maximum", "minimum",
    "under", "over", "above", "below", "around", "between",
    "rating", "ratings", "review", "reviews", "star", "stars",
    "rate", "score", "popular", "top", "featured",
    "recommend", "recommended", "recommendation",
    "similar", "related", "alternative", "compatible",
    "accessory", "accessories", "spare", "part", "parts",
    "repair", "service", "maintenance", "cleaning",
    "installment", "installments", "layaway", "finance",
    "dry", "hair", "face", "body", "hand", "foot", "feet",
    "skin", "oil", "cream", "lotion", "soap", "wash",
    "also", "even", "just", "only", "very", "too", "quite",
    "still", "already", "always", "never", "ever", "often",
    "yes", "no", "ok", "okay", "sure", "fine", "alright",
    "hello", "hi", "hey", "bye", "goodbye", "cya", "later",
    "sorry", "apologize", "please", "thanks", "thank",
    "welcome", "nice", "glad", "happy", "sad", "angry",
    "wrong", "right", "true", "false", "real", "sure",
    "maybe", "perhaps", "probably", "actually", "really",
    "much", "more", "most", "less", "least", "few", "several",
    "some", "something", "someone", "somebody", "somewhere",
    "nothing", "nobody", "nowhere", "everything", "everyone",
    "everybody", "everywhere", "anyone", "anybody", "anywhere",
    "anything", "whatever", "whoever", "whenever", "wherever",
    "although", "though", "however", "therefore", "because",
    "since", "unless", "until", "while", "whether",
    "often", "usually", "sometimes", "rarely", "seldom",
    "never", "always", "maybe", "perhaps", "pretty", "fairly",
    "along", "away", "back", "forward", "again", "once", "twice",
}

# Common shopping words + brands + categories
_SHOPPING_WORDS = [
    "phone", "phones", "smartphone", "smartphones", "mobile", "tablet",
    "laptop", "laptops", "computer", "computers", "notebook", "desktop",
    "headphone", "headphones", "earphone", "earbuds", "speaker", "speakers",
    "charger", "cable", "adapter", "cover", "case", "screen", "protector",
    "keyboard", "mouse", "mousepad", "bag", "backpack", "battery",
    "shirt", "trouser", "trousers", "shoes", "shoe", "dress", "skirt",
    "blouse", "jacket", "coat", "sweater", "hoodie", "shorts", "jeans",
    "watch", "watches", "jewelry", "ring", "necklace", "bracelet",
    "fridge", "refrigerator", "freezer", "microwave", "oven", "cooker",
    "kettle", "blender", "iron", "vacuum", "cleaner", "washing", "machine",
    "tv", "television", "soundbar", "subwoofer", "audio", "radio",
    "camera", "canon", "nikon", "tripod", "lens", "photography",
    "gaming", "playstation", "xbox", "console", "controller", "game",
    "book", "pen", "pencil", "notebook", "stationery", "school",
    "beauty", "skincare", "lotion", "shampoo", "perfume", "cosmetic",
    "cream", "makeup", "soap", "deodorant", "toothpaste", "brush",
    "food", "rice", "milk", "coffee", "tea", "sugar", "oil", "bread",
    "butter", "cheese", "egg", "chicken", "meat", "fish", "vegetable",
    "fruit", "water", "juice", "soda", "snack", "cereal", "pasta",
    "baby", "kids", "toy", "toys", "diaper", "pampers", "stroller",
    "furniture", "sofa", "couch", "bed", "table", "chair", "wardrobe",
    "shelf", "desk", "lamp", "decor", "carpet", "curtain", "pillow",
    "car", "vehicle", "tyre", "tire", "auto", "accessory", "accessories",
    "sport", "sports", "gym", "fitness", "football", "basketball",
    "running", "yoga", "mat", "dumbbell", "weight", "protein",
    "grocery", "groceries", "appliance", "appliances", "electronics",
    "fashion", "men", "women", "unisex", "kids", "children",
    "cheap", "affordable", "expensive", "discount", "sale", "offer",
    # Kinyarwanda common words
    "amafaranga", "ibicuruzwa", "kugura", "gutura", "gusura",
    "ibintu", "uburyo", "igihe", "igera", "kugeza",
    # French common words
    "produit", "prix", "livraison", "commande", "paiement",
    "retour", "garantie", "remboursement", "magasin", "acheter",
    "disponible", "reduction", "solde", "cher", "bon", "marche",
    # Kinyarwanda — never correct these
    "nshaka", "ndashaka", "ndshaka", "shaka", "amafaranga", "ibicuruzwa", "murakoze",
    "muraho", "mwaramutse", "mwiriwe", "yego", "oya", "bite", "amakuru",
    "kugura", "gufata", "ibintu", "ninde", "iki", "aho", "igihe", "uburyo",
    "gusura", "gutura", "kigali", "rwanda", "cyangwa", "ariko", "rero",
    "none", "vuba", "kandi", "nanone", "hakaba", "nkaba", "ndaba",
    "baba", "bari", "ibikoresho", "abagabo", "abagore", "kwisiga",
    # Common product names that should never be over-corrected
    "iphone", "iphones", "ipad", "ipads", "macbook", "macbooks", "imac", "airpods", "ipod",
    "galaxy", "thinkpad", "surface", "pixel", "kindle", "firestick",
    "playstation", "xbox", "nintendo", "switch", "raspberry",
]


class SpellCorrector:
    """Fast spelling corrector for e-commerce product search queries."""

    def __init__(self):
        self.initialized = False
        self._sym_spell: Optional[SymSpell] = None
        self._known_words: set[str] = set()
        self._brand_words: list[str] = []
        self._product_names: list[str] = []

    def initialize(
        self,
        brands: Optional[list[str]] = None,
        category_keywords: Optional[list[str]] = None,
        extra_stopwords: Optional[set[str]] = None,
        db_config: Optional[dict] = None,
    ) -> None:
        """Build the spelling dictionary from known terms.

        Loads common English words, brands, category keywords,
        common shopping words, and optionally product names from the database.
        """
        self._known_words = set(_SHOPPING_WORDS) | _COMMON_WORDS
        self._brand_words = []

        # Add brands (high priority)
        for b in (brands or []):
            self._known_words.add(b.lower())
            self._brand_words.append(b.lower())

        # Add category keywords
        for kw in (category_keywords or []):
            self._known_words.add(kw.lower())

        # Add extra stopwords (Kinyarwanda, French, etc.) — never correct these
        self._known_words |= (extra_stopwords or set())

        # Load product names from DB (optional)
        if db_config:
            try:
                import mysql.connector
                conn = mysql.connector.connect(**db_config)
                cur = conn.cursor()
                cur.execute(
                    "SELECT DISTINCT LOWER(name) FROM products WHERE name IS NOT NULL"
                )
                for (name,) in cur.fetchall():
                    if name:
                        for word in re.findall(r"[a-z]{3,}", name):
                            self._known_words.add(word)
                        self._product_names.append(name)
                cur.close()
                conn.close()
            except Exception as exc:
                print(f"[spell_corrector] DB load warning: {exc}")

        # Build SymSpell frequency dictionary
        if _SYMSPELL_AVAILABLE:
            self._sym_spell = SymSpell(
                max_dictionary_edit_distance=2,
                prefix_length=7,
            )
            # Equal default weight for all words — only brands get a boost
            for word in self._known_words:
                self._sym_spell.create_dictionary_entry(word, 10)
            # Brands and DB product names get higher weight to win ties
            for word in self._brand_words:
                self._sym_spell.create_dictionary_entry(word, 80)
            for name in self._product_names:
                self._sym_spell.create_dictionary_entry(name, 60)

        self.initialized = True
        print(
            f"[spell_corrector] Initialized with {len(self._known_words)} known words, "
            f"{len(self._brand_words)} brands, {len(self._product_names)} product names"
        )

    def correct(self, text: str) -> str:
        """Return the spelling-corrected version of *text*."""
        if not text or not self.initialized:
            return text

        # Track which words were originally capitalized (likely proper names)
        original_words = re.findall(r"[A-Za-z]{2,}", text)
        capitalized_words = {w.lower() for w in original_words if w[0].isupper()}

        words = re.findall(r"[a-z]{2,}", text.lower())
        if not words:
            return text

        corrections: dict[str, str] = {}
        for word in words:
            if word in self._known_words or word in _COMMON_WORDS:
                continue  # already known, no correction needed

            # Skip proper names (originally capitalized words)
            if word in capitalized_words:
                continue

            # Step 0: Direct lookup in common misspellings map
            mapped = _COMMON_MISSPELLINGS.get(word)
            if mapped and mapped != word:
                corrections[word] = mapped
                continue

            corrected = self._try_correct_word(word)
            if corrected and corrected != word:
                corrections[word] = corrected

        if not corrections:
            return text

        result = text.lower()
        for original, replacement in corrections.items():
            result = re.sub(r"\b" + re.escape(original) + r"\b", replacement, result)

        # Preserve original case for the first letter if the input was capitalized
        if text[0].isupper() and result:
            result = result[0].upper() + result[1:]

        return result

    def _try_correct_word(self, word: str) -> Optional[str]:
        """Try to correct a single word using SymSpell then rapidfuzz.

        Uses edit distance 2 for all words >= 4 chars; distance 1 for
        3-char words (to avoid over-correcting short function words).
        """
        min_len = len(word)
        max_dist = 1 if min_len <= 3 else 2

        # Step 1: SymSpell
        if self._sym_spell is not None:
            suggestions = self._sym_spell.lookup(
                word,
                Verbosity.CLOSEST,
                max_edit_distance=max_dist,
                include_unknown=True,
            )
            if suggestions:
                best = suggestions[0]
                if best.distance > 0 and best.distance <= max_dist:
                    return best.term

        # Step 2: rapidfuzz against brand names (brands are the most critical)
        if _FUZZ_AVAILABLE and self._brand_words:
            best_match, score, _ = process.extractOne(
                word, self._brand_words, scorer=fuzz.QRatio,
            )
            if score >= 70:
                return best_match

        # Step 3: rapidfuzz against all known words (use QRatio, NOT WRatio —
        #   WRatio does partial matching and over-corrects e.g. "earfones" → "on")
        if _FUZZ_AVAILABLE and self._known_words:
            best_match, score, _ = process.extractOne(
                word, list(self._known_words), scorer=fuzz.QRatio,
            )
            if score >= 72:
                return best_match

        return None


# ── Module-level singleton (lazy-initialized) ────────────────
_corrector: Optional[SpellCorrector] = None


def get_corrector() -> SpellCorrector:
    global _corrector
    if _corrector is None:
        _corrector = SpellCorrector()
    return _corrector


def correct_message(
    text: str,
    brands: Optional[list[str]] = None,
    category_keywords: Optional[list[str]] = None,
    extra_stopwords: Optional[set[str]] = None,
    db_config: Optional[dict] = None,
) -> str:
    """Public convenience function: correct spelling in *text*.

    Initializes the corrector on first call if not already done.
    """
    corr = get_corrector()
    if not corr.initialized:
        corr.initialize(
            brands=brands,
            category_keywords=category_keywords,
            extra_stopwords=extra_stopwords,
            db_config=db_config,
        )
    return corr.correct(text)
