# AI Chatbot — ML/DL Pipeline

## Stack
- Python 3.10+, Flask, scikit-learn, TensorFlow/Keras, HuggingFace Transformers
- Dataset: Custom e-commerce intents (21 classes, ~500 patterns)

## Setup & Run

```bash
cd chatbot-ml
pip install -r requirements.txt

# 1. Train all models (LR, RF, LSTM, BERT)
python train.py

# 2. Evaluate & generate plots/reports
python evaluate.py

# 3. Start Flask API (port 5000)
python app.py
```

## Models
| Model | Type | File |
|---|---|---|
| Logistic Regression | ML Baseline | models/logistic_regression.pkl |
| Random Forest | ML Ensemble | models/random_forest.pkl |
| Bidirectional LSTM | Deep Learning | models/lstm_model.keras |
| BERT fine-tuned | Transformer DL | models/bert_model/ |

## API Endpoints
- `GET  /health` — ML API status
- `POST /predict` — `{ "message": "...", "model": "best|lr|rf|lstm|bert" }`
- `POST /predict/all` — run all models, return comparison
- `GET  /models/performance` — saved metrics from training
- `GET  /intents` — list all intent tags

## Integration
PHP chatbot (`api/chatbot.php`) calls `http://localhost:5000/predict` before Gemini.
Flow: PHP intent engine → ML model → Gemini API → fallback response

## Outputs for Book Report
- `plots/all_metrics_comparison.png` — Accuracy, Precision, Recall, F1 for all models
- `plots/dataset_distribution.png` — samples per intent
- `plots/cross_validation.png` — 5-fold CV boxplot
- `plots/cm_*.png` — confusion matrices per model
- `plots/lstm_training_curves.png` — LSTM accuracy/loss curves
- `plots/bert_training_curves.png` — BERT accuracy/loss curves
- `reports/full_evaluation_report.txt` — complete text report
