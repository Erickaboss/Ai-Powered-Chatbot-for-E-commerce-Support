"""
=============================================================
  AI Chatbot — Model Training Pipeline
  Models: Logistic Regression, Random Forest,
          SVM, MLP Neural Network (scikit-learn)
  Compatible with Python 3.14+
=============================================================
"""

import json, os, pickle, warnings
import numpy as np
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import seaborn as sns

from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.svm import SVC
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import (accuracy_score, classification_report,
                              confusion_matrix, f1_score,
                              precision_score, recall_score)

warnings.filterwarnings('ignore')
os.makedirs('models',  exist_ok=True)
os.makedirs('reports', exist_ok=True)
os.makedirs('plots',   exist_ok=True)

# ─────────────────────────────────────────────────────────────
# 1. LOAD DATASET — merge all intent files
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  STEP 1: Loading Merged Dataset")
print("="*60)

def load_merged_intents(*files):
    merged = {'intents': []}
    seen_tags = set()
    for fpath in files:
        if not os.path.exists(fpath):
            print(f"  Skipping missing file: {fpath}")
            continue
        with open(fpath, 'r', encoding='utf-8') as f:
            data = json.load(f)
        for intent in data.get('intents', []):
            tag = intent['tag']
            if tag in seen_tags:
                # Merge patterns into existing intent
                for existing in merged['intents']:
                    if existing['tag'] == tag:
                        existing['patterns'].extend(intent['patterns'])
                        break
            else:
                merged['intents'].append(intent)
                seen_tags.add(tag)
        print(f"  Loaded: {fpath} ({len(data.get('intents',[]))} intents)")
    return merged

dataset_files = ['dataset/intents.json', 'dataset/intents_part2.json']
data = load_merged_intents(*dataset_files)

sentences, labels = [], []
for intent in data['intents']:
    for pattern in intent['patterns']:
        sentences.append(pattern.lower())
        labels.append(intent['tag'])

print(f"  Total samples  : {len(sentences)}")
print(f"  Total intents  : {len(set(labels))}")
print(f"  Classes        : {sorted(set(labels))}")

le = LabelEncoder()
y  = le.fit_transform(labels)
num_classes = len(le.classes_)

X_train_raw, X_test_raw, y_train, y_test = train_test_split(
    sentences, y, test_size=0.2, random_state=42, stratify=y
)
print(f"\n  Train samples  : {len(X_train_raw)}")
print(f"  Test  samples  : {len(X_test_raw)}")

pickle.dump(le, open('models/label_encoder.pkl', 'wb'))

# ─────────────────────────────────────────────────────────────
# 2. TF-IDF VECTORIZATION
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  STEP 2: TF-IDF Vectorization")
print("="*60)

tfidf = TfidfVectorizer(
    ngram_range=(1, 3),
    max_features=8000,
    sublinear_tf=True,
    min_df=1,
    analyzer='word',
    token_pattern=r'\b[a-zA-Z][a-zA-Z0-9]*\b'
)
X_train = tfidf.fit_transform(X_train_raw)
X_test  = tfidf.transform(X_test_raw)
pickle.dump(tfidf, open('models/tfidf_vectorizer.pkl', 'wb'))
print(f"  Vocabulary size: {len(tfidf.vocabulary_)}")

results = {}

def evaluate(name, model, X_tr, y_tr, X_te, y_te):
    model.fit(X_tr, y_tr)
    y_pred = model.predict(X_te)
    acc  = accuracy_score(y_te, y_pred)
    prec = precision_score(y_te, y_pred, average='weighted', zero_division=0)
    rec  = recall_score(y_te, y_pred, average='weighted', zero_division=0)
    f1   = f1_score(y_te, y_pred, average='weighted', zero_division=0)
    results[name] = {'accuracy': acc, 'precision': prec, 'recall': rec, 'f1': f1}
    print(f"\n  {name}")
    print(f"  Accuracy : {acc*100:.2f}%  |  F1: {f1*100:.2f}%  |  Precision: {prec*100:.2f}%  |  Recall: {rec*100:.2f}%")
    print(classification_report(y_te, y_pred, target_names=le.classes_))
    return model, y_pred

# ─────────────────────────────────────────────────────────────
# 3. MODEL 1 — LOGISTIC REGRESSION
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  MODEL 1: Logistic Regression (Baseline ML)")
print("="*60)
lr, y_pred_lr = evaluate('Logistic Regression', 
    LogisticRegression(max_iter=2000, C=20, solver='lbfgs', multi_class='multinomial', random_state=42),
    X_train, y_train, X_test, y_test)
pickle.dump(lr, open('models/logistic_regression.pkl', 'wb'))

# ─────────────────────────────────────────────────────────────
# 4. MODEL 2 — RANDOM FOREST
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  MODEL 2: Random Forest (Ensemble ML)")
print("="*60)
rf, y_pred_rf = evaluate('Random Forest',
    RandomForestClassifier(n_estimators=300, max_depth=None, min_samples_split=2,
                           min_samples_leaf=1, random_state=42, n_jobs=-1),
    X_train, y_train, X_test, y_test)
pickle.dump(rf, open('models/random_forest.pkl', 'wb'))

# ─────────────────────────────────────────────────────────────
# 5. MODEL 3 — SVM
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  MODEL 3: Support Vector Machine (SVM)")
print("="*60)
svm, y_pred_svm = evaluate('SVM (RBF Kernel)',
    SVC(kernel='rbf', C=50, gamma='scale', probability=True, random_state=42),
    X_train, y_train, X_test, y_test)
pickle.dump(svm, open('models/svm.pkl', 'wb'))

# ─────────────────────────────────────────────────────────────
# 6. MODEL 4 — MLP NEURAL NETWORK
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  MODEL 4: MLP Neural Network (Deep Learning)")
print("="*60)
mlp, y_pred_mlp = evaluate('MLP Neural Network',
    MLPClassifier(
        hidden_layer_sizes=(512, 256, 128),
        activation='relu',
        solver='adam',
        max_iter=1000,
        random_state=42,
        early_stopping=True,
        validation_fraction=0.1,
        learning_rate_init=0.001,
        learning_rate='adaptive',
        batch_size=32,
        verbose=False
    ),
    X_train, y_train, X_test, y_test)
pickle.dump(mlp, open('models/mlp_neural_network.pkl', 'wb'))

# ─────────────────────────────────────────────────────────────
# 7. CONFUSION MATRICES
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  STEP 7: Confusion Matrices")
print("="*60)

preds_map = {
    'Logistic Regression': y_pred_lr,
    'Random Forest':       y_pred_rf,
    'SVM (RBF Kernel)':    y_pred_svm,
    'MLP Neural Network':  y_pred_mlp,
}

for name, y_pred in preds_map.items():
    cm = confusion_matrix(y_test, y_pred)
    plt.figure(figsize=(14, 11))
    sns.heatmap(cm, annot=True, fmt='d', cmap='Blues',
                xticklabels=le.classes_, yticklabels=le.classes_,
                linewidths=0.5, linecolor='white', annot_kws={'size': 7})
    plt.title(f'Confusion Matrix — {name}', fontsize=13, fontweight='bold', pad=14)
    plt.ylabel('Actual', fontsize=11); plt.xlabel('Predicted', fontsize=11)
    plt.xticks(rotation=45, ha='right', fontsize=7)
    plt.yticks(rotation=0, fontsize=7)
    plt.tight_layout()
    fname = name.lower().replace(' ','_').replace('(','').replace(')','').replace('-','')
    plt.savefig(f'plots/cm_{fname}.png', dpi=150, bbox_inches='tight')
    plt.close()
    print(f"  Saved: plots/cm_{fname}.png")

# ─────────────────────────────────────────────────────────────
# 8. MODEL COMPARISON CHART
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  STEP 8: Model Comparison Charts")
print("="*60)

model_names   = list(results.keys())
metrics_keys  = ['accuracy', 'precision', 'recall', 'f1']
metric_labels = ['Accuracy', 'Precision', 'Recall', 'F1 Score']
colors        = ['#0f3460', '#e94560', '#f5a623', '#2ecc71']

# 4-metric comparison
fig, axes = plt.subplots(1, 4, figsize=(18, 6))
fig.suptitle('Model Performance Comparison — All Metrics', fontsize=15, fontweight='bold', y=1.02)
for ax, mk, ml, color in zip(axes, metrics_keys, metric_labels, colors):
    vals = [results[m][mk] * 100 for m in model_names]
    bars = ax.bar(model_names, vals, color=color, alpha=0.85, edgecolor='white', linewidth=1.5)
    for bar, v in zip(bars, vals):
        ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.5,
                f'{v:.1f}%', ha='center', va='bottom', fontsize=9, fontweight='bold')
    ax.set_title(ml, fontsize=12, fontweight='bold')
    ax.set_ylim(0, 115); ax.set_ylabel('Score (%)')
    ax.set_xticklabels(model_names, rotation=20, ha='right', fontsize=8)
    ax.grid(axis='y', alpha=0.3)
    ax.spines['top'].set_visible(False); ax.spines['right'].set_visible(False)
plt.tight_layout()
plt.savefig('plots/all_metrics_comparison.png', dpi=150, bbox_inches='tight')
plt.close()
print("  Saved: plots/all_metrics_comparison.png")

# Grouped bar chart
x = np.arange(len(model_names)); width = 0.2
fig, ax = plt.subplots(figsize=(14, 6))
for i, (mk, ml, color) in enumerate(zip(metrics_keys, metric_labels, colors)):
    vals = [results[m][mk]*100 for m in model_names]
    bars = ax.bar(x + i*width, vals, width, label=ml, color=color, alpha=0.85, edgecolor='white')
    for bar, v in zip(bars, vals):
        ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.3,
                f'{v:.0f}', ha='center', va='bottom', fontsize=7, fontweight='bold')
ax.set_xticks(x + width*1.5)
ax.set_xticklabels(model_names, fontsize=10)
ax.set_ylim(0, 115); ax.set_ylabel('Score (%)'); ax.set_xlabel('Model')
ax.set_title('Model Performance — Grouped Comparison', fontsize=13, fontweight='bold')
ax.legend(fontsize=10); ax.grid(axis='y', alpha=0.3)
ax.spines['top'].set_visible(False); ax.spines['right'].set_visible(False)
plt.tight_layout()
plt.savefig('plots/model_comparison_grouped.png', dpi=150, bbox_inches='tight')
plt.close()
print("  Saved: plots/model_comparison_grouped.png")

# ─────────────────────────────────────────────────────────────
# 9. CROSS-VALIDATION
# ─────────────────────────────────────────────────────────────
print("\n" + "="*60)
print("  STEP 9: 5-Fold Cross-Validation")
print("="*60)

X_all = tfidf.transform(sentences)
cv_results = {}
cv_models  = {'Logistic Regression': lr, 'Random Forest': rf,
              'SVM (RBF Kernel)': svm, 'MLP Neural Network': mlp}
for name, model in cv_models.items():
    scores = cross_val_score(model, X_all, y, cv=5, scoring='accuracy', n_jobs=-1)
    cv_results[name] = scores
    print(f"  {name:<28} Mean: {scores.mean()*100:.2f}%  Std: ±{scores.std()*100:.2f}%")

fig, ax = plt.subplots(figsize=(10, 5))
bp = ax.boxplot([cv_results[m]*100 for m in cv_results], labels=list(cv_results.keys()),
                patch_artist=True, medianprops={'color':'white','linewidth':2})
for patch, color in zip(bp['boxes'], ['#0f3460','#e94560','#f5a623','#2ecc71']):
    patch.set_facecolor(color); patch.set_alpha(0.8)
ax.set_title('5-Fold Cross-Validation — Accuracy Distribution', fontsize=13, fontweight='bold')
ax.set_ylabel('Accuracy (%)'); ax.grid(axis='y', alpha=0.3)
ax.spines['top'].set_visible(False); ax.spines['right'].set_visible(False)
plt.tight_layout()
plt.savefig('plots/cross_validation.png', dpi=150, bbox_inches='tight')
plt.close()
print("  Saved: plots/cross_validation.png")

# ─────────────────────────────────────────────────────────────
# 10. DATASET DISTRIBUTION
# ─────────────────────────────────────────────────────────────
from collections import Counter
label_counts  = Counter(labels)
sorted_labels = sorted(label_counts.items(), key=lambda x: x[1], reverse=True)
tags, counts  = zip(*sorted_labels)

fig, ax = plt.subplots(figsize=(14, 6))
bars = ax.bar(tags, counts, color='#0f3460', alpha=0.85, edgecolor='white', linewidth=1.2)
for bar, c in zip(bars, counts):
    ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.1,
            str(c), ha='center', va='bottom', fontsize=9, fontweight='bold')
ax.set_title('Dataset Distribution — Samples per Intent', fontsize=13, fontweight='bold', pad=14)
ax.set_xlabel('Intent'); ax.set_ylabel('Number of Patterns')
ax.set_xticklabels(tags, rotation=45, ha='right', fontsize=9)
ax.grid(axis='y', alpha=0.3)
ax.spines['top'].set_visible(False); ax.spines['right'].set_visible(False)
plt.tight_layout()
plt.savefig('plots/dataset_distribution.png', dpi=150, bbox_inches='tight')
plt.close()
print("  Saved: plots/dataset_distribution.png")

# ─────────────────────────────────────────────────────────────
# 11. SAVE REPORT + MODEL RESULTS JSON
# ─────────────────────────────────────────────────────────────
best = max(results, key=lambda m: results[m]['accuracy'])
sep  = "=" * 65

report = f"""
{sep}
  AI CHATBOT FOR E-COMMERCE — MODEL PERFORMANCE REPORT
  AI-Powered Chatbot For E-commerce Support
{sep}

1. DATASET SUMMARY
{"-"*65}
   Source         : Custom E-commerce Intents (intents.json)
   Total Patterns : {len(sentences)}
   Total Intents  : {num_classes}
   Train Samples  : {len(X_train_raw)}
   Test  Samples  : {len(X_test_raw)}
   Split Ratio    : 80% Train / 20% Test (Stratified)

2. MODELS SELECTED
{"-"*65}
   Model 1 : Logistic Regression (TF-IDF + LR)   — ML Baseline
   Model 2 : Random Forest (TF-IDF + RF)          — ML Ensemble
   Model 3 : SVM with RBF Kernel                  — ML Kernel Method
   Model 4 : MLP Neural Network (256-128-64)      — Deep Learning
   Model 5 : Google Gemini API                    — LLM (multilingual)

3. PERFORMANCE METRICS
{"-"*65}
   {"Model":<28} {"Accuracy":>10} {"Precision":>10} {"Recall":>10} {"F1 Score":>10}
   {"-"*60}"""

for name, m in results.items():
    report += f"\n   {name:<28} {m['accuracy']*100:>9.2f}% {m['precision']*100:>9.2f}% {m['recall']*100:>9.2f}% {m['f1']*100:>9.2f}%"

report += f"""

4. CROSS-VALIDATION (5-Fold)
{"-"*65}"""
for name, scores in cv_results.items():
    report += f"\n   {name:<28} Mean: {scores.mean()*100:.2f}%  Std: ±{scores.std()*100:.2f}%"

report += f"""

5. BEST MODEL
{"-"*65}
   Selected Model : {best}
   Accuracy       : {results[best]['accuracy']*100:.2f}%
   F1 Score       : {results[best]['f1']*100:.2f}%

6. SYSTEM ARCHITECTURE
{"-"*65}
   Frontend  : JavaScript (chatbot widget)
   Backend   : PHP 8 (intent routing, DB queries)
   ML API    : Python Flask (intent classification)
   Database  : MySQL (products, orders, users, logs)
   AI Layer  : Google Gemini API (multilingual polish)
   Email     : PHPMailer + Gmail SMTP

7. PLOTS GENERATED
{"-"*65}
   plots/all_metrics_comparison.png
   plots/model_comparison_grouped.png
   plots/dataset_distribution.png
   plots/cross_validation.png
   plots/cm_logistic_regression.png
   plots/cm_random_forest.png
   plots/cm_svm_rbf_kernel.png
   plots/cm_mlp_neural_network.png
{sep}
"""

print(report)
with open('reports/performance_report.txt', 'w') as f:
    f.write(report)
print("  Saved: reports/performance_report.txt")

with open('models/model_results.json', 'w') as f:
    json.dump({
        'results':     results,
        'best_model':  best,
        'num_classes': num_classes,
        'intents':     list(le.classes_),
        'cv_results':  {k: v.tolist() for k, v in cv_results.items()},
        'summary': {
            'best_model':          best,
            'accuracy':            results[best]['accuracy'],
            'f1_score':            results[best]['f1'],
            'average_accuracy':    sum(r['accuracy'] for r in results.values()) / len(results),
            'num_classes':         num_classes,
            'training_samples':    len(X_train_raw),
            'test_samples':        len(X_test_raw),
            'total_samples':       len(sentences),
            'all_models_above_85': all(r['accuracy'] >= 0.85 for r in results.values()),
            'target_accuracy':     0.85,
            'model_version':       '2.0.0',
            'dataset_files':       dataset_files,
        },
        'models': [
            {
                'model_name': name,
                'accuracy':   m['accuracy'],
                'precision':  m['precision'],
                'recall':     m['recall'],
                'f1_score':   m['f1'],
                'cv_mean':    cv_results.get(name, [0]).mean() if hasattr(cv_results.get(name, [0]), 'mean') else 0,
                'cv_std':     cv_results.get(name, [0]).std()  if hasattr(cv_results.get(name, [0]), 'std')  else 0,
                'is_best':    name == best,
            }
            for name, m in results.items()
        ],
        'dataset': {
            'total_samples':   len(sentences),
            'num_classes':     num_classes,
            'train_samples':   len(X_train_raw),
            'test_samples':    len(X_test_raw),
            'vocabulary_size': len(tfidf.vocabulary_),
            'dataset_files':   dataset_files,
            'database_augmentation': {
                'product_search_samples': 0,
                'faq_samples':            0,
                'note': 'DB-grounded responses handled by PHP layer at runtime',
            }
        },
        'reports': [
            {'filename': 'performance_report.txt', 'web_path': None},
        ],
    }, f, indent=2)

print("\n  All models trained and saved!")
print(f"  Best model: {best}")
print("  Run: python app.py  to start the Flask API\n")
