#!/usr/bin/env python3
"""
Build the semantic product embedding index.
Run this once to create models/product_embeddings.pkl
"""
import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "ecommerce_chatbot",
    "connection_timeout": 10,
}

print("=" * 60)
print("BUILDING SEMANTIC SEARCH INDEX")
print("=" * 60)

# Check sentence-transformers
try:
    from sentence_transformers import SentenceTransformer
    print("✅ sentence-transformers available")
except ImportError:
    print("❌ sentence-transformers not installed")
    print("   Run: pip install sentence-transformers")
    sys.exit(1)

# Import semantic search module
from chatbot.semantic_search import rebuild_index, _CACHE_PATH

print(f"\n📦 Loading model (all-MiniLM-L6-v2)...")
print("   (First run downloads ~80MB — please wait)")

count = rebuild_index(DB_CONFIG)

if count > 0:
    print(f"\n✅ SUCCESS! Indexed {count} products")
    print(f"   Saved to: {_CACHE_PATH}")
    size_mb = os.path.getsize(_CACHE_PATH) / (1024 * 1024)
    print(f"   File size: {size_mb:.1f} MB")
    print("\n🎯 Semantic search is now active!")
    print("   'cheap gaming laptop' will match 'Affordable ASUS TUF laptop'")
else:
    print("\n❌ FAILED — check DB connection and try again")
    sys.exit(1)
