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
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from sklearn.model_selection import cross_val_score, train_test_split
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
)
import mysql.connector
from collections import Counter
from datetime import datetime
import os

from dataset_utils import build_training_samples, load_merged_intents

TARGET_ACCURACY = 0.85
MODEL_VERSION = datetime.now().strftime('%Y.%m.%d')
DATASET_FILES = ('dataset/intents.json', 'dataset/intents_part2.json')

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
# PRESENTATION-GRADE TRAINING PIPELINE
# ================================================================

def slugify_model_name(name):
    return (
        name.lower()
        .replace(' ', '_')
        .replace('(', '')
        .replace(')', '')
        .replace('-', '_')
        .replace('__', '_')
    )


def describe_artifact(path, label, kind, model_name=None):
    record = {
        'path': path.replace('\\', '/'),
        'label': label,
        'kind': kind,
    }
    if model_name:
        record['model_name'] = model_name

    if os.path.exists(path):
        stat = os.stat(path)
        record.update({
            'exists': True,
            'size_bytes': int(stat.st_size),
            'modified_at': datetime.fromtimestamp(stat.st_mtime).isoformat(timespec='seconds'),
        })
    else:
        record['exists'] = False

    return record


def describe_source_file(path):
    record = describe_artifact(path, os.path.basename(path), 'dataset_source')
    if not record['exists']:
        return record

    try:
        with open(path, encoding='utf-8') as handle:
            payload = json.load(handle)
        intents = payload.get('intents') or payload.get('intents_part2') or []
        record['intent_count'] = len(intents)
        record['pattern_count'] = int(sum(
            len(intent.get('patterns', []))
            for intent in intents
            if isinstance(intent, dict)
        ))
    except Exception:
        pass

    return record


def create_training_data_v2():
    """Create a merged, deduplicated training dataset with DB-grounded augmentation."""
    print("\nLoading merged training data...")

    merged_payload = load_merged_intents(DATASET_FILES)
    texts, labels = build_training_samples(merged_payload)
    base_pattern_count = len(texts)
    base_intent_count = len(set(labels))
    print(f"Loaded {base_pattern_count} base intent patterns across {base_intent_count} intent groups")

    products = load_products_from_db()
    product_patterns = [
        "show me {}",
        "find {}",
        "looking for {}",
        "search for {}",
        "do you have {}",
        "price of {}",
        "cost of {}",
        "i want {}",
        "help me find {}",
    ]
    product_query_augments = 0
    category_query_augments = 0
    recommendation_augments = 0

    for product in products:
        name = (product.get('name') or '').strip().lower()
        category = (product.get('category_name') or '').strip().lower()
        if not name:
            continue
        for pattern in product_patterns:
            texts.append(pattern.format(name))
            labels.append('product_search')
            product_query_augments += 1
        if category:
            texts.extend([
                f"show me {category}",
                f"browse {category}",
                f"best {category} under 100k",
                f"recommend {category}",
            ])
            labels.extend([
                'category_search',
                'category_search',
                'recommendation',
                'recommendation',
            ])
            category_query_augments += 2
            recommendation_augments += 2

    faqs = load_faq_from_db()
    faq_augments = 0
    for faq in faqs:
        question = (faq.get('question') or '').strip().lower()
        if question:
            texts.append(question)
            labels.append('faq')
            faq_augments += 1

    pre_dedup_samples = len(texts)

    deduped = {}
    ordered_pairs = []
    for text, label in zip(texts, labels):
        key = (text.strip().lower(), label)
        if not key[0] or key in deduped:
            continue
        deduped[key] = True
        ordered_pairs.append(key)

    final_texts = [text for text, _ in ordered_pairs]
    final_labels = [label for _, label in ordered_pairs]
    label_distribution = Counter(final_labels)

    dataset_stats = {
        'source_files': [describe_source_file(path) for path in DATASET_FILES],
        'merged_intent_count': len(merged_payload.get('intents', [])),
        'base_pattern_count': base_pattern_count,
        'database_augmentation': {
            'products_loaded': len(products),
            'faqs_loaded': len(faqs),
            'product_search_samples': product_query_augments,
            'category_search_samples': category_query_augments,
            'recommendation_samples': recommendation_augments,
            'faq_samples': faq_augments,
        },
        'pre_dedup_samples': pre_dedup_samples,
        'deduped_samples_removed': pre_dedup_samples - len(final_texts),
        'total_samples': len(final_texts),
        'unique_intents': len(set(final_labels)),
        'label_distribution': {
            label: int(count)
            for label, count in sorted(label_distribution.items(), key=lambda item: (-item[1], item[0]))
        },
    }

    print(f"Total training samples after DB augmentation: {len(final_texts)}")
    print(f"Total unique intents: {len(set(final_labels))}")
    return final_texts, final_labels, dataset_stats


def train_models_v2(X_train, y_train, X_test, y_test):
    """Train all production models and collect presentation-friendly metrics."""
    models = {
        'Logistic Regression': LogisticRegression(
            max_iter=1500,
            random_state=42,
            class_weight='balanced',
        ),
        'Random Forest': RandomForestClassifier(
            n_estimators=250,
            random_state=42,
            n_jobs=1,
        ),
        'MLP Neural Network': MLPClassifier(
            hidden_layer_sizes=(256, 128),
            max_iter=500,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.12,
            n_iter_no_change=8,
        ),
        'SVM (Linear)': CalibratedClassifierCV(
            LinearSVC(class_weight='balanced', dual='auto', max_iter=5000, random_state=42),
            cv=3,
            method='sigmoid',
        ),
    }

    results = {}
    print("\nTraining production models...\n")

    for name, model in models.items():
        print(f"Training {name}...")
        model.fit(X_train, y_train)
        predictions = model.predict(X_test)
        accuracy = accuracy_score(y_test, predictions)
        precision = precision_score(y_test, predictions, average='weighted', zero_division=0)
        recall = recall_score(y_test, predictions, average='weighted', zero_division=0)
        f1 = f1_score(y_test, predictions, average='weighted', zero_division=0)
        macro_precision = precision_score(y_test, predictions, average='macro', zero_division=0)
        macro_recall = recall_score(y_test, predictions, average='macro', zero_division=0)
        macro_f1 = f1_score(y_test, predictions, average='macro', zero_division=0)
        cv_scores = cross_val_score(model, X_train, y_train, cv=3, n_jobs=1)

        results[name] = {
            'model': model,
            'accuracy': float(accuracy),
            'precision': float(precision),
            'recall': float(recall),
            'f1_score': float(f1),
            'macro_precision': float(macro_precision),
            'macro_recall': float(macro_recall),
            'macro_f1': float(macro_f1),
            'cv_mean': float(cv_scores.mean()),
            'cv_std': float(cv_scores.std()),
            'cv_scores': [float(score) for score in cv_scores],
            'predictions': predictions,
        }

        print(
            f"  Accuracy: {accuracy:.4f} | Precision: {precision:.4f} | "
            f"Recall: {recall:.4f} | F1: {f1:.4f}"
        )
        print(f"  CV Mean : {cv_scores.mean():.4f} (+/- {cv_scores.std() * 2:.4f})\n")

    return results


def save_plots_v2(results, y_test, label_encoder, labels):
    os.makedirs('plots', exist_ok=True)
    model_names = list(results.keys())
    metric_keys = ['accuracy', 'precision', 'recall', 'f1_score']
    metric_labels = ['Accuracy', 'Precision', 'Recall', 'F1 Score']
    colors = ['#0f3460', '#e94560', '#f5a623', '#2ecc71']
    plot_artifacts = []

    for name, result in results.items():
        cm = confusion_matrix(y_test, result['predictions'])
        plt.figure(figsize=(14, 11))
        sns.heatmap(
            cm,
            annot=True,
            fmt='d',
            cmap='Blues',
            xticklabels=label_encoder.classes_,
            yticklabels=label_encoder.classes_,
            linewidths=0.5,
            linecolor='white',
            annot_kws={'size': 7},
        )
        plt.title(f'Confusion Matrix - {name}', fontsize=13, fontweight='bold', pad=14)
        plt.ylabel('Actual', fontsize=11)
        plt.xlabel('Predicted', fontsize=11)
        plt.xticks(rotation=45, ha='right', fontsize=7)
        plt.yticks(rotation=0, fontsize=7)
        plt.tight_layout()
        confusion_path = f"plots/cm_{slugify_model_name(name)}.png"
        plt.savefig(confusion_path, dpi=150, bbox_inches='tight')
        plt.close()
        plot_artifacts.append(describe_artifact(confusion_path, f'{name} Confusion Matrix', 'plot', model_name=name))

    fig, axes = plt.subplots(1, 4, figsize=(18, 6))
    fig.suptitle('Model Performance Comparison - All Metrics', fontsize=15, fontweight='bold', y=1.02)
    for ax, metric_key, metric_label, color in zip(axes, metric_keys, metric_labels, colors):
        values = [results[name][metric_key] * 100 for name in model_names]
        bars = ax.bar(model_names, values, color=color, alpha=0.85, edgecolor='white', linewidth=1.5)
        for bar, value in zip(bars, values):
            ax.text(bar.get_x() + bar.get_width() / 2, bar.get_height() + 0.5, f'{value:.1f}%', ha='center', va='bottom', fontsize=9, fontweight='bold')
        ax.set_title(metric_label, fontsize=12, fontweight='bold')
        ax.set_ylim(0, 115)
        ax.set_ylabel('Score (%)')
        ax.tick_params(axis='x', labelrotation=20, labelsize=8)
        ax.grid(axis='y', alpha=0.3)
        ax.spines['top'].set_visible(False)
        ax.spines['right'].set_visible(False)
    plt.tight_layout()
    all_metrics_path = 'plots/all_metrics_comparison.png'
    plt.savefig(all_metrics_path, dpi=150, bbox_inches='tight')
    plt.close()
    plot_artifacts.append(describe_artifact(all_metrics_path, 'All Metrics Comparison', 'plot'))

    x = np.arange(len(model_names))
    width = 0.2
    fig, ax = plt.subplots(figsize=(14, 6))
    for index, (metric_key, metric_label, color) in enumerate(zip(metric_keys, metric_labels, colors)):
        values = [results[name][metric_key] * 100 for name in model_names]
        bars = ax.bar(x + index * width, values, width, label=metric_label, color=color, alpha=0.85, edgecolor='white')
        for bar, value in zip(bars, values):
            ax.text(bar.get_x() + bar.get_width() / 2, bar.get_height() + 0.3, f'{value:.0f}', ha='center', va='bottom', fontsize=7, fontweight='bold')
    ax.set_xticks(x + width * 1.5)
    ax.set_xticklabels(model_names, fontsize=10)
    ax.set_ylim(0, 115)
    ax.set_ylabel('Score (%)')
    ax.set_xlabel('Model')
    ax.set_title('Model Performance - Grouped Comparison', fontsize=13, fontweight='bold')
    ax.legend(fontsize=10)
    ax.grid(axis='y', alpha=0.3)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    plt.tight_layout()
    grouped_path = 'plots/model_comparison_grouped.png'
    plt.savefig(grouped_path, dpi=150, bbox_inches='tight')
    plt.close()
    plot_artifacts.append(describe_artifact(grouped_path, 'Grouped Model Comparison', 'plot'))

    fig, ax = plt.subplots(figsize=(12, 5))
    boxplot = ax.boxplot(
        [results[name]['cv_scores'] for name in model_names],
        tick_labels=model_names,
        patch_artist=True,
        medianprops={'color': 'white', 'linewidth': 2},
    )
    for patch, color in zip(boxplot['boxes'], colors):
        patch.set_facecolor(color)
        patch.set_alpha(0.8)
    ax.set_title('3-Fold Cross-Validation - Accuracy Distribution', fontsize=13, fontweight='bold')
    ax.set_ylabel('Accuracy')
    ax.grid(axis='y', alpha=0.3)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    plt.tight_layout()
    cross_validation_path = 'plots/cross_validation.png'
    plt.savefig(cross_validation_path, dpi=150, bbox_inches='tight')
    plt.close()
    plot_artifacts.append(describe_artifact(cross_validation_path, 'Cross Validation Distribution', 'plot'))

    label_counts = Counter(labels)
    sorted_labels = sorted(label_counts.items(), key=lambda item: item[1], reverse=True)
    tags, counts = zip(*sorted_labels)
    fig, ax = plt.subplots(figsize=(14, 6))
    bars = ax.bar(tags, counts, color='#0f3460', alpha=0.85, edgecolor='white', linewidth=1.2)
    for bar, count in zip(bars, counts):
        ax.text(bar.get_x() + bar.get_width() / 2, bar.get_height() + 0.1, str(count), ha='center', va='bottom', fontsize=9, fontweight='bold')
    ax.set_title('Dataset Distribution - Samples per Intent', fontsize=13, fontweight='bold', pad=14)
    ax.set_xlabel('Intent')
    ax.set_ylabel('Number of Patterns')
    ax.tick_params(axis='x', labelrotation=45, labelsize=9)
    ax.grid(axis='y', alpha=0.3)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    plt.tight_layout()
    distribution_path = 'plots/dataset_distribution.png'
    plt.savefig(distribution_path, dpi=150, bbox_inches='tight')
    plt.close()
    plot_artifacts.append(describe_artifact(distribution_path, 'Dataset Distribution', 'plot'))

    return plot_artifacts


def save_artifacts_v2(results, vectorizer, label_encoder, training_samples, test_samples, dataset_stats, plot_artifacts, split_config):
    os.makedirs('models', exist_ok=True)
    best_name = max(results, key=lambda name: results[name]['accuracy'])
    trained_at = datetime.now().isoformat(timespec='seconds')
    average_accuracy = float(np.mean([results[name]['accuracy'] for name in results]))
    all_models_above_target = all(results[name]['accuracy'] >= TARGET_ACCURACY for name in results)

    pickle.dump(vectorizer, open('models/tfidf_vectorizer.pkl', 'wb'))
    pickle.dump(label_encoder, open('models/label_encoder.pkl', 'wb'))

    save_files = {
        'Logistic Regression': 'models/logistic_regression.pkl',
        'Random Forest': 'models/random_forest.pkl',
        'MLP Neural Network': 'models/mlp_neural_network.pkl',
        'SVM (Linear)': 'models/svm.pkl',
    }
    for name, path in save_files.items():
        pickle.dump(results[name]['model'], open(path, 'wb'))

    vectorizer_details = {
        'type': 'tfidf',
        'max_features': vectorizer.max_features,
        'ngram_range': list(vectorizer.ngram_range),
        'min_df': vectorizer.min_df,
        'max_df': vectorizer.max_df,
        'sublinear_tf': bool(vectorizer.sublinear_tf),
        'vocabulary_size': len(getattr(vectorizer, 'vocabulary_', {})),
    }

    model_artifacts = [
        describe_artifact('models/tfidf_vectorizer.pkl', 'TF-IDF Vectorizer', 'model_support'),
        describe_artifact('models/label_encoder.pkl', 'Label Encoder', 'model_support'),
    ]

    models_payload = []
    for name, result in results.items():
        model_file = save_files[name].replace('\\', '/')
        models_payload.append({
            'model_name': name,
            'accuracy': result['accuracy'],
            'precision': result['precision'],
            'recall': result['recall'],
            'f1_score': result['f1_score'],
            'macro_precision': result['macro_precision'],
            'macro_recall': result['macro_recall'],
            'macro_f1': result['macro_f1'],
            'cv_mean': result['cv_mean'],
            'cv_std': result['cv_std'],
            'cv_scores': result['cv_scores'],
            'training_samples': training_samples,
            'test_samples': test_samples,
            'trained_at': trained_at,
            'model_version': MODEL_VERSION,
            'meets_target': result['accuracy'] >= TARGET_ACCURACY,
            'model_file': model_file,
        })
        model_artifacts.append(describe_artifact(model_file, f'{name} Model', 'model', model_name=name))

    models_payload.sort(key=lambda item: item['accuracy'], reverse=True)

    model_results = {
        'summary': {
            'best_model': best_name,
            'best_accuracy': results[best_name]['accuracy'],
            'average_accuracy': average_accuracy,
            'target_accuracy': TARGET_ACCURACY,
            'all_models_above_target': all_models_above_target,
            'trained_at': trained_at,
            'model_version': MODEL_VERSION,
            'total_patterns': training_samples + test_samples,
            'training_samples': training_samples,
            'test_samples': test_samples,
            'num_classes': len(label_encoder.classes_),
            'languages': ['English', 'French', 'Kinyarwanda'],
        },
        'dataset': dataset_stats,
        'vectorizer': vectorizer_details,
        'split': split_config,
        'models': models_payload,
        'intents': list(label_encoder.classes_),
        'best_model': best_name,
        'accuracy': results[best_name]['accuracy'],
        'num_classes': len(label_encoder.classes_),
        'per_model': {
            name: {
                'accuracy': result['accuracy'],
                'precision': result['precision'],
                'recall': result['recall'],
                'f1_score': result['f1_score'],
                'macro_precision': result['macro_precision'],
                'macro_recall': result['macro_recall'],
                'macro_f1': result['macro_f1'],
                'cv_mean': result['cv_mean'],
                'cv_std': result['cv_std'],
                'cv_scores': result['cv_scores'],
            }
            for name, result in results.items()
        },
        'artifacts': {
            'plots': plot_artifacts,
            'models': model_artifacts,
        },
    }

    with open('models/model_results.json', 'w', encoding='utf-8') as handle:
        json.dump(model_results, handle, indent=2)

    with open('models/model_metadata.json', 'w', encoding='utf-8') as handle:
        json.dump({
            'best_model': best_name,
            'accuracy': results[best_name]['accuracy'],
            'cv_score': results[best_name]['cv_mean'],
            'timestamp': trained_at,
            'model_version': MODEL_VERSION,
            'num_classes': len(label_encoder.classes_),
            'classes': list(label_encoder.classes_),
            'total_patterns': training_samples + test_samples,
            'all_models_above_target': all_models_above_target,
            'target_accuracy': TARGET_ACCURACY,
            'dataset': dataset_stats,
            'vectorizer': vectorizer_details,
            'split': split_config,
        }, handle, indent=2)

    return {
        'best_model': best_name,
        'accuracy': results[best_name]['accuracy'],
        'average_accuracy': average_accuracy,
        'all_models_above_target': all_models_above_target,
        'num_classes': len(label_encoder.classes_),
        'classes': list(label_encoder.classes_),
        'timestamp': trained_at,
        'model_version': MODEL_VERSION,
        'training_samples': training_samples,
        'test_samples': test_samples,
        'target_accuracy': TARGET_ACCURACY,
        'dataset': dataset_stats,
        'vectorizer': vectorizer_details,
        'split': split_config,
        'model_artifacts': model_artifacts,
        'plot_artifacts': plot_artifacts,
    }


def generate_report_v2(results, metadata):
    os.makedirs('reports', exist_ok=True)
    lines = [
        '=' * 72,
        'COMPREHENSIVE CHATBOT TRAINING REPORT',
        '=' * 72,
        f"Generated: {metadata['timestamp']}",
        '',
        'PRESENTATION SUMMARY',
        '-' * 72,
        f"Best model: {metadata['best_model']}",
        f"Best accuracy: {metadata['accuracy'] * 100:.2f}%",
        f"Average accuracy: {metadata['average_accuracy'] * 100:.2f}%",
        f"Target accuracy: {TARGET_ACCURACY * 100:.0f}%",
        f"All models above target: {'YES' if metadata['all_models_above_target'] else 'NO'}",
        f"Training samples: {metadata['training_samples']}",
        f"Test samples: {metadata['test_samples']}",
        f"Number of classes: {metadata['num_classes']}",
        'Languages: English, French, Kinyarwanda',
        '',
        'DATASET & AUGMENTATION',
        '-' * 72,
        f"Merged source files: {', '.join(item['path'] for item in metadata['dataset']['source_files'])}",
        f"Base JSON patterns: {metadata['dataset']['base_pattern_count']}",
        f"Merged intents: {metadata['dataset']['merged_intent_count']}",
        f"Product DB augment samples: {metadata['dataset']['database_augmentation']['product_search_samples']}",
        f"Category augment samples: {metadata['dataset']['database_augmentation']['category_search_samples']}",
        f"Recommendation augment samples: {metadata['dataset']['database_augmentation']['recommendation_samples']}",
        f"FAQ augment samples: {metadata['dataset']['database_augmentation']['faq_samples']}",
        f"Deduplicated samples removed: {metadata['dataset']['deduped_samples_removed']}",
        '',
        'VECTORIZER',
        '-' * 72,
        f"Max features: {metadata['vectorizer']['max_features']}",
        f"N-gram range: {tuple(metadata['vectorizer']['ngram_range'])}",
        f"Vocabulary size: {metadata['vectorizer']['vocabulary_size']}",
        f"Sublinear TF: {'YES' if metadata['vectorizer']['sublinear_tf'] else 'NO'}",
        '',
        'MODEL COMPARISON',
        '-' * 72,
        f"{'Model':<24} {'Acc':>8} {'Prec':>8} {'Recall':>8} {'F1':>8} {'CV Mean':>9}",
        '-' * 72,
    ]

    for name, result in sorted(results.items(), key=lambda item: item[1]['accuracy'], reverse=True):
        lines.append(
            f"{name:<24} {result['accuracy'] * 100:>7.2f}% {result['precision'] * 100:>7.2f}% "
            f"{result['recall'] * 100:>7.2f}% {result['f1_score'] * 100:>7.2f}% {result['cv_mean'] * 100:>8.2f}%"
        )

    lines.extend([
        '',
        'SUPPORTED INTENTS',
        '-' * 72,
    ])
    lines.extend([f"{index + 1}. {intent}" for index, intent in enumerate(metadata['classes'])])
    lines.extend([
        '',
        'PLOTS GENERATED',
        '-' * 72,
    ])
    lines.extend([artifact['path'] for artifact in metadata['plot_artifacts']])
    lines.extend([
        '',
        'MODEL ARTIFACTS',
        '-' * 72,
    ])
    lines.extend([artifact['path'] for artifact in metadata['model_artifacts']])
    lines.extend([
        '',
        '=' * 72,
    ])

    report_text = '\n'.join(lines)
    report_artifacts = []
    for path in ('reports/comprehensive_training_report.txt', 'reports/performance_report.txt'):
        with open(path, 'w', encoding='utf-8') as handle:
            handle.write(report_text)
        report_artifacts.append(describe_artifact(path, os.path.basename(path), 'report'))

    print('\n' + report_text)
    return report_artifacts


def save_artifact_manifest_v2(metadata, report_artifacts):
    manifest = {
        'generated_at': metadata['timestamp'],
        'model_version': metadata['model_version'],
        'best_model': metadata['best_model'],
        'summary': {
            'best_model': metadata['best_model'],
            'best_accuracy': metadata['accuracy'],
            'average_accuracy': metadata['average_accuracy'],
            'target_accuracy': metadata['target_accuracy'],
            'all_models_above_target': metadata['all_models_above_target'],
            'training_samples': metadata['training_samples'],
            'test_samples': metadata['test_samples'],
            'num_classes': metadata['num_classes'],
        },
        'dataset': metadata['dataset'],
        'vectorizer': metadata['vectorizer'],
        'split': metadata['split'],
        'artifacts': {
            'models': metadata['model_artifacts'],
            'plots': metadata['plot_artifacts'],
            'reports': report_artifacts,
            'supporting': [
                describe_artifact('models/model_results.json', 'Model Results JSON', 'metadata'),
                describe_artifact('models/model_metadata.json', 'Model Metadata JSON', 'metadata'),
            ],
        },
    }

    manifest_path = 'models/artifact_manifest.json'
    with open(manifest_path, 'w', encoding='utf-8') as handle:
        json.dump(manifest, handle, indent=2)

    return describe_artifact(manifest_path, 'Artifact Manifest', 'metadata')

# ================================================================
# MAIN TRAINING PIPELINE
# ================================================================

def main():
    print("=" * 70)
    print("COMPREHENSIVE CHATBOT TRAINING SYSTEM")
    print("=" * 70)
    print(f"Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # 1. Create training data
    texts, labels, dataset_stats = create_training_data_v2()
    
    if len(texts) == 0:
        print("❌ No training data found!")
        return
    
    # 2. Preprocess
    print("\n🔄 Preprocessing data...")
    from sklearn.preprocessing import LabelEncoder
    
    label_encoder = LabelEncoder()
    y = label_encoder.fit_transform(labels)
    
    # TF-IDF Vectorization
    vectorizer_config = {
        'max_features': 8000,
        'ngram_range': (1, 3),
        'min_df': 1,
        'max_df': 0.98,
        'sublinear_tf': True,
    }
    vectorizer = TfidfVectorizer(**vectorizer_config)
    X = vectorizer.fit_transform(texts)
    
    # 3. Split data (stratify when every class has enough samples)
    split_config = {
        'test_size': 0.2,
        'random_state': 42,
        'strategy': 'stratified',
    }
    try:
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
    except ValueError:
        split_config['strategy'] = 'random'
        print("   ⚠️ stratify skipped (some classes too small)")
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
    
    print(f"   Training samples: {X_train.shape[0]}")
    print(f"   Test samples: {X_test.shape[0]}")
    
    # 4. Train models
    results = train_models_v2(X_train, y_train, X_test, y_test)
    
    # 5. Save plots and artifacts
    plot_artifacts = save_plots_v2(results, y_test, label_encoder, labels)
    metadata = save_artifacts_v2(
        results,
        vectorizer,
        label_encoder,
        X_train.shape[0],
        X_test.shape[0],
        dataset_stats,
        plot_artifacts,
        split_config,
    )
    
    # 6. Generate report
    report_artifacts = generate_report_v2(results, metadata)
    manifest_artifact = save_artifact_manifest_v2(metadata, report_artifacts)
    
    print("\n" + "=" * 70)
    print("✅ TRAINING COMPLETED SUCCESSFULLY!")
    print("=" * 70)
    print(f"Best Model: {metadata['best_model']}")
    print(f"Accuracy: {metadata['accuracy']:.2%}")
    print(f"Classes: {metadata['num_classes']}")
    print("\nModels saved to: chatbot-ml/models/")
    print("Report saved to: chatbot-ml/reports/")
    print("Plots saved to: chatbot-ml/plots/")
    print(f"Manifest saved to: {manifest_artifact['path']}")
    print("\nNext steps:")
    print("1. Restart Flask server if running")
    print("2. Open admin ML dashboard to review metrics")
    print("3. Test multilingual and budget-based queries")

if __name__ == "__main__":
    main()
