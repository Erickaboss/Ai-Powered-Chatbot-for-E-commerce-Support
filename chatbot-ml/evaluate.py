"""
=============================================================
  AI Chatbot — Model Evaluation & Report Generator
  Models: Logistic Regression, Random Forest, SVM, MLP
  Compatible with Python 3.14+ (scikit-learn only)
=============================================================
"""

import json, pickle, os
import numpy as np
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import seaborn as sns
from collections import Counter
from sklearn.metrics import (accuracy_score, precision_score, recall_score,
                              f1_score, confusion_matrix, classification_report)
from sklearn.model_selection import cross_val_score, train_test_split

from dataset_utils import build_training_samples, load_merged_intents

os.makedirs('plots',   exist_ok=True)
os.makedirs('reports', exist_ok=True)

# ── Load artifacts ────────────────────────────────────────────
print("Loading dataset and models...")

data = load_merged_intents(('dataset/intents.json', 'dataset/intents_part2.json'))

le    = pickle.load(open('models/label_encoder.pkl',    'rb'))
tfidf = pickle.load(open('models/tfidf_vectorizer.pkl', 'rb'))
lr    = pickle.load(open('models/logistic_regression.pkl', 'rb'))
rf    = pickle.load(open('models/random_forest.pkl',    'rb'))
svm   = pickle.load(open('models/svm.pkl',              'rb'))
mlp   = pickle.load(open('models/mlp_neural_network.pkl','rb'))

sentences, labels = build_training_samples(data)

y = le.transform(labels)
X = tfidf.transform(sentences)

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=y
)

print(f"  Dataset loaded: {len(sentences)} samples, {len(set(labels))} intents")

# ── Evaluate all models ───────────────────────────────────────
models = {
    'Logistic Regression': lr,
    'Random Forest':       rf,
    'SVM (Linear)':        svm,
    'MLP Neural Network':  mlp,
}

all_preds   = {}
all_metrics = {}

for name, model in models.items():
    y_pred = model.predict(X_test)
    all_preds[name] = y_pred
    all_metrics[name] = {
        'accuracy':  accuracy_score(y_test, y_pred),
        'precision': precision_score(y_test, y_pred, average='weighted', zero_division=0),
        'recall':    recall_score(y_test, y_pred, average='weighted', zero_division=0),
        'f1':        f1_score(y_test, y_pred, average='weighted', zero_division=0),
    }
    print(f"  {name}: Acc={all_metrics[name]['accuracy']*100:.2f}%  F1={all_metrics[name]['f1']*100:.2f}%")

# ── PLOT 1: Model Comparison ──────────────────────────────────
print("\nGenerating plots...")

metrics_names = ['accuracy', 'precision', 'recall', 'f1']
metric_labels = ['Accuracy', 'Precision', 'Recall', 'F1 Score']
colors        = ['#0f3460', '#e94560', '#f5a623', '#2ecc71']
model_names   = list(all_metrics.keys())

fig, axes = plt.subplots(1, 4, figsize=(18, 6))
fig.suptitle('Model Performance Comparison — All Metrics', fontsize=15, fontweight='bold', y=1.02)
for ax, metric, label, color in zip(axes, metrics_names, metric_labels, colors):
    vals = [all_metrics[m][metric] * 100 for m in model_names]
    bars = ax.bar(model_names, vals, color=color, alpha=0.85, edgecolor='white', linewidth=1.5)
    for bar, v in zip(bars, vals):
        ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.5,
                f'{v:.1f}%', ha='center', va='bottom', fontsize=9, fontweight='bold')
    ax.set_title(label, fontsize=12, fontweight='bold')
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
for i, (mk, ml, color) in enumerate(zip(metrics_names, metric_labels, colors)):
    vals = [all_metrics[m][mk]*100 for m in model_names]
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

# ── PLOT 2: Confusion Matrices ────────────────────────────────
for name, y_pred in all_preds.items():
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

# ── PLOT 3: Cross-Validation ──────────────────────────────────
print("\nRunning 5-fold cross-validation...")
cv_results = {}
for name, model in models.items():
    scores = cross_val_score(model, X, y, cv=5, scoring='accuracy', n_jobs=-1)
    cv_results[name] = scores
    print(f"  {name}: {scores.mean()*100:.2f}% ± {scores.std()*100:.2f}%")

fig, ax = plt.subplots(figsize=(12, 5))
bp = ax.boxplot([cv_results[m]*100 for m in cv_results], tick_labels=list(cv_results.keys()),
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

# ── PLOT 4: Dataset Distribution ─────────────────────────────
label_counts  = Counter(labels)
sorted_labels = sorted(label_counts.items(), key=lambda x: x[1], reverse=True)
tags, counts  = zip(*sorted_labels)

fig, ax = plt.subplots(figsize=(14, 6))
bars = ax.bar(tags, counts, color='#0f3460', alpha=0.85, edgecolor='white', linewidth=1.2)
for bar, c in zip(bars, counts):
    ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.1,
            str(c), ha='center', va='bottom', fontsize=9, fontweight='bold')
ax.set_title('Dataset Distribution — Samples per Intent', fontsize=13, fontweight='bold', pad=14)
ax.set_xlabel('Intent', fontsize=11); ax.set_ylabel('Number of Patterns', fontsize=11)
ax.set_xticklabels(tags, rotation=45, ha='right', fontsize=9)
ax.grid(axis='y', alpha=0.3)
ax.spines['top'].set_visible(False); ax.spines['right'].set_visible(False)
plt.tight_layout()
plt.savefig('plots/dataset_distribution.png', dpi=150, bbox_inches='tight')
plt.close()
print("  Saved: plots/dataset_distribution.png")

# ── FULL TEXT REPORT ──────────────────────────────────────────
print("\nGenerating full evaluation report...")

sep  = "=" * 65
sep2 = "-" * 65
best = max(all_metrics, key=lambda m: all_metrics[m]['accuracy'])

lines = [
    sep,
    "  AI CHATBOT FOR E-COMMERCE — FULL EVALUATION REPORT",
    "  AI-Powered Chatbot For E-commerce Support",
    sep, "",
    "1. DATASET SUMMARY", sep2,
    f"   Source          : Merged e-commerce intents (intents.json + intents_part2.json)",
    f"   Total Patterns  : {len(sentences)}",
    f"   Total Intents   : {len(set(labels))}",
    f"   Train Samples   : {int(len(sentences)*0.8)}",
    f"   Test  Samples   : {int(len(sentences)*0.2)}",
    f"   Split Ratio     : 80% Train / 20% Test (Stratified)",
    "",
    "2. MODELS SELECTED", sep2,
    "   Model 1 : Logistic Regression (TF-IDF + LR)   — ML Baseline",
    "   Model 2 : Random Forest (TF-IDF + RF)          — ML Ensemble",
    "   Model 3 : SVM (Linear)                         — ML Margin Classifier",
    "   Model 4 : MLP Neural Network                  — Neural Classifier",
    "",
    "3. PERFORMANCE METRICS", sep2,
    f"   {'Model':<28} {'Accuracy':>10} {'Precision':>10} {'Recall':>10} {'F1 Score':>10}",
    "   " + "-"*60,
]

for name, m in all_metrics.items():
    lines.append(
        f"   {name:<28} {m['accuracy']*100:>9.2f}% {m['precision']*100:>9.2f}% "
        f"{m['recall']*100:>9.2f}% {m['f1']*100:>9.2f}%"
    )

lines += [
    "",
    "4. BEST MODEL", sep2,
    f"   Selected Model  : {best}",
    f"   Accuracy        : {all_metrics[best]['accuracy']*100:.2f}%",
    f"   F1 Score        : {all_metrics[best]['f1']*100:.2f}%",
    "",
    "5. CROSS-VALIDATION (5-Fold)", sep2,
]
for name, scores in cv_results.items():
    lines.append(f"   {name:<28} Mean: {scores.mean()*100:.2f}%  Std: ±{scores.std()*100:.2f}%")

lines += [
    "",
    "6. SYSTEM ARCHITECTURE", sep2,
    "   Frontend  : JavaScript (chatbot widget)",
    "   Backend   : PHP 8 (intent routing, DB queries)",
    "   ML API    : Python Flask (intent classification)",
    "   Database  : MySQL (products, orders, users, logs)",
    "   AI Layer  : Optional Gemini last-resort responses (outside core ML metrics)",
    "   Email     : PHPMailer + Gmail SMTP",
    "",
    "7. PLOTS GENERATED", sep2,
    "   plots/all_metrics_comparison.png",
    "   plots/model_comparison_grouped.png",
    "   plots/dataset_distribution.png",
    "   plots/cross_validation.png",
    "   plots/cm_logistic_regression.png",
    "   plots/cm_random_forest.png",
    "   plots/cm_svm_linear.png",
    "   plots/cm_mlp_neural_network.png",
    "",
    sep,
]

report = "\n".join(lines)
print(report)
with open('reports/full_evaluation_report.txt', 'w') as f:
    f.write(report)
print("\n  Saved: reports/full_evaluation_report.txt")
print("  Evaluation complete.")
