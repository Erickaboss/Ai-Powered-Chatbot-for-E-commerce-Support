"""
Language Detection Module for Chatbot
Detects English, Kinyarwanda, and French
"""

import re

# Common Kinyarwanda words and patterns
KINYARWANDA_WORDS = {
    'mwaramutse', 'mwiriwe', 'muraho', 'habari', 'amakuru', 'iki', 'nde', 'ute',
    'inyumba', 'inyamaswa', 'inyama', 'inyanya',
    'umuntu', 'umunsi', 'umwanya', 'umwari',
    'abantu', 'abakazi', 'abagabo', 'abana', 'abakungu', 'abakobwa',
    'igitabo', 'igice', 'igipimo', 'igihe', 'igikorwa', 'igikoni',
    'igikoresho', 'igitambara', 'igitero', 'igitondo', 'igituba', 'igitugu',
    'igitukuza', 'igitukuzwa', 'igituza', 'igituzwa', 'igitwa', 'igitwe', 'igitweho',
    'ibikinini', 'ibikoresho', 'ibigitabo', 'ibigitero', 'ibigitonda', 'ibigituba',
    'ibigitugu', 'ibigitukuza', 'ibigitukuzwa', 'ibigituza', 'ibigituzwa', 'ibigitwa',
    'ibigitwe', 'ibigitweho',
    'amafaranga', 'ubwoko', 'ikinini', 'icyuma', 'icyumba',
    'murakoze', 'urakoze', 'asante', 'ahubwo', 'yego', 'oya', 'nta', 'niba',
    'ariko', 'kandi', 'cyangwa', 'kubera', 'nkuko', 'uko', 'aho', 'hano',
    'hari', 'nari', 'icyiciro', 'inzira', 'inziranzira',
    'kugura', 'kuguza', 'kugurisha', 'kugurwa',
    'kunywa', 'kurya', 'kuryamwo',
    'gusoma', 'gusomeka',
    'kwiyandikisha', 'kwiyandikira',
}

# Common French words
FRENCH_WORDS = {
    'bonjour', 'bonsoir', 'bonne', 'nuit', 'salut', 'coucou', 'allo', 'allô',
    'merci', 'merci beaucoup', 'merci bien', 'de rien', 'au revoir', 'adieu',
    'oui', 'non', 'peut-être', 'peut', 'peux', 'pouvez', 'pouvez-vous',
    'je', 'tu', 'il', 'elle', 'nous', 'vous', 'ils', 'elles',
    'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses',
    'notre', 'nos', 'votre', 'vos', 'leur', 'leurs',
    'un', 'une', 'des', 'le', 'la', 'les', 'l',
    'et', 'ou', 'mais', 'donc', 'car', 'parce', 'que', 'qui', 'quoi',
    'où', 'quand', 'comment', 'pourquoi', 'combien', 'quel', 'quelle',
    'quels', 'quelles', 'lequel', 'laquelle', 'lesquels', 'lesquelles',
    'ce', 'cet', 'cette', 'ces', 'celui', 'celle', 'ceux', 'celles',
    'ça', 'cela', 'ceci', 'là', 'ici', 'là-bas', 'là-haut', 'là-dedans',
    'veux', 'vouloir', 'voulez', 'voudriez', 'aimerais', 'aimer', 'aimez',
    'besoin', 'besoin', 'besoin', 'besoin', 'besoin', 'besoin', 'besoin',
    'cherche', 'chercher', 'cherchez', 'cherchais', 'cherchait', 'cherchaient',
    'trouve', 'trouver', 'trouvez', 'trouvais', 'trouvait', 'trouvaient',
    'prix', 'coût', 'coûte', 'coûter', 'coûtez', 'coûtais', 'coûtait',
    'livraison', 'livrer', 'livrez', 'livrais', 'livrait', 'livraient',
    'commande', 'commander', 'commandez', 'commandais', 'commandait', 'commandaient',
    'paiement', 'payer', 'payez', 'payais', 'payait', 'payaient',
    'retour', 'retourner', 'retournez', 'retournais', 'retournait', 'retournaient',
    'produit', 'produits', 'article', 'articles', 'item', 'items',
    'magasin', 'boutique', 'store', 'shop', 'commerce', 'commerces',
    'client', 'clients', 'customer', 'customers', 'acheteur', 'acheteurs',
    'vendeur', 'vendeurs', 'seller', 'sellers', 'marchand', 'marchands',
    'combien', 'combien ça coûte', 'quel est le prix', 'quel prix',
    'frais', 'frais de port', 'frais de livraison', 'frais de shipping',
    'gratuit', 'gratuite', 'gratuits', 'gratuites', 'free', 'sans frais',
    'jour', 'jours', 'jour ouvrable', 'jours ouvrables', 'jour de travail',
    'semaine', 'semaines', 'mois', 'mois', 'année', 'années',
    'rapide', 'rapides', 'rapide', 'rapide', 'rapide', 'rapide', 'rapide',
    'lent', 'lents', 'lent', 'lent', 'lent', 'lent', 'lent', 'lent',
    'cher', 'chère', 'chers', 'chères', 'expensive', 'expensive', 'expensive',
    'bon marché', 'bon', 'bonne', 'bons', 'bonnes', 'cheap', 'cheap', 'cheap',
    'qualité', 'qualités', 'quality', 'qualities', 'bon', 'bonne', 'bons',
    'mauvais', 'mauvaise', 'mauvais', 'mauvaise', 'mauvais', 'mauvaise', 'mauvais',
    'excellent', 'excellente', 'excellents', 'excellentes', 'excellent', 'excellent',
    'terrible', 'terribles', 'terrible', 'terrible', 'terrible', 'terrible', 'terrible',
    'bien', 'bien', 'bien', 'bien', 'bien', 'bien', 'bien', 'bien', 'bien',
    'mal', 'mal', 'mal', 'mal', 'mal', 'mal', 'mal', 'mal', 'mal', 'mal',
    'très', 'très', 'très', 'très', 'très', 'très', 'très', 'très', 'très',
    'beaucoup', 'beaucoup', 'beaucoup', 'beaucoup', 'beaucoup', 'beaucoup', 'beaucoup',
    'peu', 'peu', 'peu', 'peu', 'peu', 'peu', 'peu', 'peu', 'peu', 'peu',
    'assez', 'assez', 'assez', 'assez', 'assez', 'assez', 'assez', 'assez', 'assez',
    'trop', 'trop', 'trop', 'trop', 'trop', 'trop', 'trop', 'trop', 'trop', 'trop',
    'pas', 'pas', 'pas', 'pas', 'pas', 'pas', 'pas', 'pas', 'pas', 'pas', 'pas',
    'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne', 'ne',
    'rien', 'rien', 'rien', 'rien', 'rien', 'rien', 'rien', 'rien', 'rien', 'rien',
    'jamais', 'jamais', 'jamais', 'jamais', 'jamais', 'jamais', 'jamais', 'jamais',
    'toujours', 'toujours', 'toujours', 'toujours', 'toujours', 'toujours', 'toujours',
    'encore', 'encore', 'encore', 'encore', 'encore', 'encore', 'encore', 'encore',
    'déjà', 'déjà', 'déjà', 'déjà', 'déjà', 'déjà', 'déjà', 'déjà', 'déjà',
    'bientôt', 'bientôt', 'bientôt', 'bientôt', 'bientôt', 'bientôt', 'bientôt',
    'maintenant', 'maintenant', 'maintenant', 'maintenant', 'maintenant', 'maintenant',
    'aujourd', 'aujourd hui', 'aujourd\'hui', 'aujourd hui', 'aujourd hui',
    'hier', 'hier', 'hier', 'hier', 'hier', 'hier', 'hier', 'hier', 'hier',
    'demain', 'demain', 'demain', 'demain', 'demain', 'demain', 'demain', 'demain',
}

# Common English words (for comparison)
ENGLISH_WORDS = {
    'hello', 'hi', 'hey', 'good', 'morning', 'afternoon', 'evening', 'night',
    'thank', 'thanks', 'thank you', 'thanks a lot', 'thanks so much',
    'yes', 'no', 'maybe', 'can', 'could', 'would', 'should', 'will', 'shall',
    'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
    'my', 'your', 'his', 'her', 'its', 'our', 'their', 'mine', 'yours', 'his',
    'a', 'an', 'the', 'and', 'or', 'but', 'so', 'because', 'that', 'which', 'who',
    'what', 'where', 'when', 'why', 'how', 'how much', 'how many', 'which', 'what',
    'this', 'that', 'these', 'those', 'here', 'there', 'there', 'where', 'where',
    'want', 'need', 'like', 'love', 'hate', 'prefer', 'choose', 'select', 'pick',
    'buy', 'purchase', 'sell', 'shop', 'shopping', 'store', 'shop', 'shop', 'shop',
    'product', 'products', 'item', 'items', 'thing', 'things', 'stuff', 'stuff',
    'price', 'cost', 'money', 'pay', 'payment', 'charge', 'fee', 'fees', 'fees',
    'delivery', 'shipping', 'ship', 'deliver', 'send', 'sent', 'arrive', 'arrived',
    'order', 'orders', 'order', 'order', 'order', 'order', 'order', 'order', 'order',
    'track', 'tracking', 'track', 'track', 'track', 'track', 'track', 'track', 'track',
    'return', 'returns', 'return', 'return', 'return', 'return', 'return', 'return',
    'refund', 'refunds', 'refund', 'refund', 'refund', 'refund', 'refund', 'refund',
    'cancel', 'cancellation', 'cancel', 'cancel', 'cancel', 'cancel', 'cancel', 'cancel',
    'help', 'help', 'help', 'help', 'help', 'help', 'help', 'help', 'help', 'help',
    'support', 'support', 'support', 'support', 'support', 'support', 'support', 'support',
    'question', 'questions', 'question', 'question', 'question', 'question', 'question',
    'answer', 'answers', 'answer', 'answer', 'answer', 'answer', 'answer', 'answer',
    'information', 'info', 'information', 'information', 'information', 'information',
    'know', 'know', 'know', 'know', 'know', 'know', 'know', 'know', 'know', 'know',
    'understand', 'understand', 'understand', 'understand', 'understand', 'understand',
    'see', 'see', 'see', 'see', 'see', 'see', 'see', 'see', 'see', 'see', 'see',
    'look', 'look', 'look', 'look', 'look', 'look', 'look', 'look', 'look', 'look',
    'find', 'find', 'find', 'find', 'find', 'find', 'find', 'find', 'find', 'find',
    'search', 'search', 'search', 'search', 'search', 'search', 'search', 'search',
    'show', 'show', 'show', 'show', 'show', 'show', 'show', 'show', 'show', 'show',
    'display', 'display', 'display', 'display', 'display', 'display', 'display', 'display',
    'list', 'list', 'list', 'list', 'list', 'list', 'list', 'list', 'list', 'list',
    'browse', 'browse', 'browse', 'browse', 'browse', 'browse', 'browse', 'browse',
    'fast', 'quick', 'slow', 'quick', 'quick', 'quick', 'quick', 'quick', 'quick',
    'expensive', 'cheap', 'expensive', 'cheap', 'expensive', 'cheap', 'expensive', 'cheap',
    'good', 'bad', 'good', 'bad', 'good', 'bad', 'good', 'bad', 'good', 'bad',
    'excellent', 'terrible', 'excellent', 'terrible', 'excellent', 'terrible', 'excellent',
    'very', 'very', 'very', 'very', 'very', 'very', 'very', 'very', 'very', 'very',
    'much', 'much', 'much', 'much', 'much', 'much', 'much', 'much', 'much', 'much',
    'little', 'little', 'little', 'little', 'little', 'little', 'little', 'little',
    'enough', 'enough', 'enough', 'enough', 'enough', 'enough', 'enough', 'enough',
    'too', 'too', 'too', 'too', 'too', 'too', 'too', 'too', 'too', 'too', 'too',
    'not', 'not', 'not', 'not', 'not', 'not', 'not', 'not', 'not', 'not', 'not',
    'nothing', 'nothing', 'nothing', 'nothing', 'nothing', 'nothing', 'nothing', 'nothing',
    'never', 'never', 'never', 'never', 'never', 'never', 'never', 'never', 'never',
    'always', 'always', 'always', 'always', 'always', 'always', 'always', 'always',
    'still', 'still', 'still', 'still', 'still', 'still', 'still', 'still', 'still',
    'already', 'already', 'already', 'already', 'already', 'already', 'already', 'already',
    'soon', 'soon', 'soon', 'soon', 'soon', 'soon', 'soon', 'soon', 'soon', 'soon',
    'now', 'now', 'now', 'now', 'now', 'now', 'now', 'now', 'now', 'now', 'now',
    'today', 'today', 'today', 'today', 'today', 'today', 'today', 'today', 'today',
    'yesterday', 'yesterday', 'yesterday', 'yesterday', 'yesterday', 'yesterday', 'yesterday',
    'tomorrow', 'tomorrow', 'tomorrow', 'tomorrow', 'tomorrow', 'tomorrow', 'tomorrow',
}

def detect_language(text: str) -> str:
    """
    Detect language from text.
    Returns: 'kinyarwanda', 'french', or 'english'
    """
    if not text or len(text.strip()) == 0:
        return 'english'  # default
    
    text_lower = text.lower().strip()
    words = re.findall(r'\b\w+\b', text_lower)
    
    if not words:
        return 'english'
    
    # Count matches for each language
    kinyarwanda_count = sum(1 for w in words if w in KINYARWANDA_WORDS)
    french_count = sum(1 for w in words if w in FRENCH_WORDS)
    english_count = sum(1 for w in words if w in ENGLISH_WORDS)
    
    # Calculate percentages
    total_matches = kinyarwanda_count + french_count + english_count
    
    if total_matches == 0:
        # No matches, use heuristics
        # Check for Kinyarwanda patterns (e.g., words ending in -a, -e, -i, -o, -u)
        if re.search(r'\b\w+[aeiou]$', text_lower):
            return 'kinyarwanda'
        return 'english'
    
    # Determine language by highest count
    if kinyarwanda_count > french_count and kinyarwanda_count > english_count:
        return 'kinyarwanda'
    elif french_count > english_count:
        return 'french'
    else:
        return 'english'

if __name__ == '__main__':
    # Test
    test_cases = [
        "mwaramutse",
        "hello",
        "bonjour",
        "how are you",
        "comment allez-vous",
        "waramutse gutese",
    ]
    
    for test in test_cases:
        lang = detect_language(test)
        print(f"'{test}' -> {lang}")
