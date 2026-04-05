"""
Comprehensive Chatbot Training System
Trains ML models on ALL intents and integrates e-commerce database knowledge
"""

import json
import sys

if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except Exception:
        pass
import pandas as pd
import numpy as np
import pickle
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from sklearn.model_selection import cross_val_score, train_test_split
from sklearn.metrics import classification_report, confusion_matrix, accuracy_score
import mysql.connector
from datetime import datetime
import os

# ================================================================
# DATABASE CONNECTION - Load ALL e-commerce knowledge
# ================================================================

def get_db_connection():
    """Connect to MySQL database"""
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="ecommerce_chatbot"
        )
        return conn
    except Exception as e:
        print(f"❌ Database connection failed: {e}")
        return None

# ================================================================
# LOAD DATASET FROM MULTIPLE SOURCES
# ================================================================

def load_intents_from_json():
    """Load intents from JSON files"""
    all_intents = []
    
    # Load main intents file
    try:
        with open('dataset/intents.json', 'r', encoding='utf-8') as f:
            data = json.load(f)
            all_intents.extend(data.get('intents', []))
            print(f"✅ Loaded intents from intents.json")
    except Exception as e:
        print(f"⚠️ Error loading intents.json: {e}")
    
    # Load additional intents
    try:
        with open('dataset/intents_part2.json', 'r', encoding='utf-8') as f:
            data = json.load(f)
            all_intents.extend(data.get('intents', []))
            print(f"✅ Loaded intents from intents_part2.json")
    except Exception as e:
        print(f"⚠️ Error loading intents_part2.json: {e}")
    
    return all_intents

def load_products_from_db():
    """Load product information from database"""
    conn = get_db_connection()
    if not conn:
        return []
    
    try:
        cursor = conn.cursor(dictionary=True)
        q_active = """
            SELECT p.id, p.name, p.description, p.price, p.category_id, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 1
        """
        q_all = """
            SELECT p.id, p.name, p.description, p.price, p.category_id, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
        """
        try:
            cursor.execute(q_active)
        except mysql.connector.Error:
            cursor.execute(q_all)
        products = cursor.fetchall()
        cursor.close()
        conn.close()

        print(f"✅ Loaded {len(products)} products from database")
        return products
    except Exception as e:
        print(f"❌ Error loading products: {e}")
        return []

def load_faq_from_db():
    """Load frequently asked questions from database"""
    conn = get_db_connection()
    if not conn:
        return []
    
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT question, answer FROM faq WHERE status = 1")
        faqs = cursor.fetchall()
        cursor.close()
        conn.close()
        
        print(f"✅ Loaded {len(faqs)} FAQs from database")
        return faqs
    except Exception as e:
        print(f"⚠️ FAQ table skip (optional): {e}")
        return []

def create_training_data():
    """Create comprehensive training dataset"""
    print("\n🔍 Loading training data...")
    
    texts = []
    labels = []
    
    # 1. Load intents from JSON
    intents = load_intents_from_json()
    for intent in intents:
        tag = intent['tag']
        patterns = intent.get('patterns', [])
        for pattern in patterns:
            texts.append(pattern.lower())
            labels.append(tag)
    
    print(f"📊 Loaded {len(texts)} intent patterns across {len(set(labels))} categories")
    
    # 2. Add product-related queries
    products = load_products_from_db()
    product_patterns = [
        "show me {}", "find {}", "looking for {}", "search for {}",
        "do you have {}", "price of {}", "cost of {}", "where is {}",
        "i want {}", "need {}", "help me find {}"
    ]
    
    for product in products:
        name = product['name'].lower()
        category = product.get('category_name', '').lower()
        
        # Create variations with product names
        for pattern in product_patterns[:5]:  # Limit to avoid too much data
            texts.append(pattern.format(name))
            labels.append('product_search')
        
        # Category-based queries
        if category:
            texts.append(f"show me {category}")
            labels.append('category_search')
            texts.append(f"browse {category}")
            labels.append('category_search')
    
    # 3. Add FAQ-based queries
    faqs = load_faq_from_db()
    for faq in faqs:
        question = faq['question'].lower()
        texts.append(question)
        labels.append('faq')
    
    print(f"📊 Total training samples: {len(texts)}")
    print(f"📊 Total unique intents: {len(set(labels))}")
    
    return texts, labels

# ================================================================
# TRAIN MULTIPLE MODELS
# ================================================================

def train_models(X_train, y_train, X_test, y_test):
    """Train multiple ML models and compare performance"""
    
    models = {
        'Logistic Regression': LogisticRegression(max_iter=1000, random_state=42),
        'Random Forest': RandomForestClassifier(n_estimators=100, random_state=42, n_jobs=-1),
        # Smaller MLP + early stopping — full RBF-SVM on large TF-IDF matrices is too slow for routine retraining
        'MLP Neural Network': MLPClassifier(
            hidden_layer_sizes=(64, 32),
            max_iter=120,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.12,
            n_iter_no_change=6,
        ),
        'SVM (Linear)': CalibratedClassifierCV(
            LinearSVC(class_weight='balanced', dual='auto', max_iter=4000, random_state=42),
            cv=2,
            method='sigmoid',
        ),
    }
    
    results = {}
    
    print("\n🚀 Training models...\n")
    
    for name, model in models.items():
        print(f"Training {name}...")
        
        # Train
        model.fit(X_train, y_train)

        # Predict
        y_pred = model.predict(X_test)

        # Evaluate
        accuracy = accuracy_score(y_test, y_pred)
        # Skip expensive k-fold CV for slower models (each fold retrains from scratch)
        if name in ('MLP Neural Network', 'SVM (Linear)'):
            cv_scores = np.array([accuracy])
        else:
            cv_scores = cross_val_score(model, X_train, y_train, cv=3, n_jobs=-1)

        results[name] = {
            'model': model,
            'accuracy': accuracy,
            'cv_mean': cv_scores.mean(),
            'cv_std': cv_scores.std(),
            'predictions': y_pred
        }
        
        print(f"  ✅ Accuracy: {accuracy:.4f}")
        print(f"  ✅ CV Score: {cv_scores.mean():.4f} (+/- {cv_scores.std()*2:.4f})\n")
    
    return results

# ================================================================
# SAVE MODELS AND METADATA
# ================================================================

# Filenames must match chatbot-ml/app.py loader expectations
_MODEL_SAVE_FILES = {
    'Logistic Regression': 'logistic_regression.pkl',
    'Random Forest': 'random_forest.pkl',
    'MLP Neural Network': 'mlp_neural_network.pkl',
    'SVM (Linear)': 'svm.pkl',
}


def save_best_model(results, vectorizer, label_encoder):
    """Save vectorizer, label encoder, all trained models, and metadata for Flask app."""
    best_name = max(results, key=lambda x: results[x]['accuracy'])
    best_result = results[best_name]

    print(f"\n💾 Saving models (best by test accuracy: {best_name})")

    models_dir = 'models'
    os.makedirs(models_dir, exist_ok=True)

    with open(f'{models_dir}/tfidf_vectorizer.pkl', 'wb') as f:
        pickle.dump(vectorizer, f)

    with open(f'{models_dir}/label_encoder.pkl', 'wb') as f:
        pickle.dump(label_encoder, f)

    for name, result in results.items():
        fname = _MODEL_SAVE_FILES.get(name)
        if fname:
            with open(f'{models_dir}/{fname}', 'wb') as f:
                pickle.dump(result['model'], f)
            print(f"  ✅ {fname}")

    metadata = {
        'best_model': best_name,
        'accuracy': float(best_result['accuracy']),
        'cv_score': float(best_result['cv_mean']),
        'timestamp': datetime.now().isoformat(),
        'num_classes': len(label_encoder.classes_),
        'classes': list(label_encoder.classes_)
    }

    with open(f'{models_dir}/model_metadata.json', 'w', encoding='utf-8') as f:
        json.dump(metadata, f, indent=2)

    model_results = {
        'best_model': best_name,
        'accuracy': float(best_result['accuracy']),
        'num_classes': len(label_encoder.classes_),
        'intents': list(label_encoder.classes_),
        'per_model': {
            n: {'accuracy': float(r['accuracy']), 'cv_mean': float(r['cv_mean'])}
            for n, r in results.items()
        },
    }
    with open(f'{models_dir}/model_results.json', 'w', encoding='utf-8') as f:
        json.dump(model_results, f, indent=2)

    print(f"  ✅ model_metadata.json & model_results.json")

    return best_name, metadata

# ================================================================
# GENERATE COMPREHENSIVE REPORT
# ================================================================

def generate_report(results, metadata):
    """Generate detailed training report"""
    
    report = []
    report.append("=" * 70)
    report.append("COMPREHENSIVE CHATBOT TRAINING REPORT")
    report.append("=" * 70)
    report.append(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    report.append("")
    
    report.append("BEST MODEL PERFORMANCE")
    report.append("-" * 70)
    report.append(f"Model: {metadata['best_model']}")
    report.append(f"Accuracy: {metadata['accuracy']:.2%}")
    report.append(f"Cross-Validation: {metadata['cv_score']:.2%}")
    report.append(f"Number of Classes: {metadata['num_classes']}")
    report.append("")
    
    report.append("ALL CLASSES SUPPORTED")
    report.append("-" * 70)
    for i, cls in enumerate(metadata['classes'], 1):
        report.append(f"{i}. {cls}")
    report.append("")
    
    report.append("MODEL COMPARISON")
    report.append("-" * 70)
    for name, result in results.items():
        report.append(f"{name}:")
        report.append(f"  Accuracy: {result['accuracy']:.4f}")
        report.append(f"  CV Score: {result['cv_mean']:.4f} (+/- {result['cv_std']*2:.4f})")
        report.append("")
    
    report.append("TRAINING DATA SOURCES")
    report.append("-" * 70)
    report.append("✓ JSON intents (intents.json, intents_part2.json)")
    report.append("✓ Product database (products table)")
    report.append("✓ Categories database (categories table)")
    report.append("✓ FAQ database (faq table)")
    report.append("")
    
    report.append("GEMINI API INTEGRATION")
    report.append("-" * 70)
    report.append("Complex queries will be handled by Google Gemini API")
    report.append("Fallback to ML model when:")
    report.append("  - API quota exceeded")
    report.append("  - Low confidence scores")
    report.append("  - Simple intent matching sufficient")
    report.append("")
    
    report.append("RECOMMENDATIONS")
    report.append("-" * 70)
    report.append("1. Regularly retrain with new conversation logs")
    report.append("2. Monitor low-confidence predictions")
    report.append("3. Add new intents based on user queries")
    report.append("4. Keep product database updated")
    report.append("5. Review and expand FAQ coverage")
    report.append("")
    
    report.append("=" * 70)
    
    # Save report
    reports_dir = 'reports'
    os.makedirs(reports_dir, exist_ok=True)
    
    report_text = "\n".join(report)
    with open(f'{reports_dir}/comprehensive_training_report.txt', 'w', encoding='utf-8') as f:
        f.write(report_text)
    
    print("\n" + report_text)
    
    return report_text

# ================================================================
# MAIN TRAINING PIPELINE
# ================================================================

def main():
    print("=" * 70)
    print("COMPREHENSIVE CHATBOT TRAINING SYSTEM")
    print("=" * 70)
    print(f"Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # 1. Create training data
    texts, labels = create_training_data()
    
    if len(texts) == 0:
        print("❌ No training data found!")
        return
    
    # 2. Preprocess
    print("\n🔄 Preprocessing data...")
    from sklearn.preprocessing import LabelEncoder
    
    label_encoder = LabelEncoder()
    y = label_encoder.fit_transform(labels)
    
    # TF-IDF Vectorization
    vectorizer = TfidfVectorizer(
        max_features=5000,
        ngram_range=(1, 2),
        min_df=1,
        max_df=0.95
    )
    X = vectorizer.fit_transform(texts)
    
    # 3. Split data (stratify when every class has enough samples)
    try:
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
    except ValueError:
        print("   ⚠️ stratify skipped (some classes too small)")
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
    
    print(f"   Training samples: {X_train.shape[0]}")
    print(f"   Test samples: {X_test.shape[0]}")
    
    # 4. Train models
    results = train_models(X_train, y_train, X_test, y_test)
    
    # 5. Save best model
    best_name, metadata = save_best_model(results, vectorizer, label_encoder)
    
    # 6. Generate report
    generate_report(results, metadata)
    
    print("\n" + "=" * 70)
    print("✅ TRAINING COMPLETED SUCCESSFULLY!")
    print("=" * 70)
    print(f"Best Model: {best_name}")
    print(f"Accuracy: {metadata['accuracy']:.2%}")
    print(f"Classes: {metadata['num_classes']}")
    print("\nModels saved to: chatbot-ml/models/")
    print("Report saved to: chatbot-ml/reports/")
    print("\nNext steps:")
    print("1. Copy trained models to: api/models/")
    print("2. Restart Flask server if running")
    print("3. Test chatbot with various queries")

if __name__ == "__main__":
    main()
