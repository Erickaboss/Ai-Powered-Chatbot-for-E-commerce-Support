"""
=============================================================
  Flask API — AI Chatbot ML Backend
  Models: Logistic Regression, Random Forest, SVM, MLP
  Compatible with Python 3.14+ (scikit-learn only)
=============================================================
"""

import json, pickle, os, random
import numpy as np
from flask import Flask, request, jsonify
from flask_cors import CORS

from dataset_utils import load_merged_intents

app = Flask(__name__)
CORS(app)
ML_API_PORT = int(os.environ.get('CHATBOT_ML_PORT', '5001'))

# ── Load models & artifacts ──────────────────────────────────
print("Loading models...")

def _load_intents_merged():
    """Load merged intents from all dataset files."""
    return load_merged_intents(('dataset/intents.json', 'dataset/intents_part2.json'))


intents_data = _load_intents_merged()

le       = pickle.load(open('models/label_encoder.pkl',      'rb'))
tfidf    = pickle.load(open('models/tfidf_vectorizer.pkl',   'rb'))
lr_model = pickle.load(open('models/logistic_regression.pkl','rb'))
rf_model = pickle.load(open('models/random_forest.pkl',      'rb'))
svm_model= pickle.load(open('models/svm.pkl',                'rb'))
mlp_model= pickle.load(open('models/mlp_neural_network.pkl', 'rb'))

# Load results summary
try:
    with open('models/model_results.json') as f:
        model_results = json.load(f)
    best_model_name = (
        model_results.get('best_model')
        or model_results.get('summary', {}).get('best_model')
        or 'MLP Neural Network'
    )
except Exception:
    model_results   = {}
    best_model_name = 'MLP Neural Network'

print(f"  Best model: {best_model_name}")
print("  All models ready.\n")

# ── Helpers ──────────────────────────────────────────────────
def get_response_for_tag(tag: str) -> str:
    for intent in intents_data['intents']:
        if intent['tag'] == tag:
            return random.choice(intent['responses'])
    return "I'm not sure how to respond to that."

def _predict(model, text: str):
    vec  = tfidf.transform([text.lower()])
    pred = model.predict(vec)[0]
    prob = model.predict_proba(vec)[0].max()
    return le.inverse_transform([pred])[0], float(prob)

def predict_lr(text):  return _predict(lr_model,  text)
def predict_rf(text):  return _predict(rf_model,  text)
def predict_svm(text): return _predict(svm_model, text)
def predict_mlp(text): return _predict(mlp_model, text)

PREDICTORS = {
    'Logistic Regression': predict_lr,
    'Random Forest': predict_rf,
    'SVM (Linear)': predict_svm,
    'MLP Neural Network': predict_mlp,
}

def predict_best(text: str):
    """Use the artifact-selected best model first, then fall back safely."""
    ordered_models = []

    if best_model_name in PREDICTORS:
        ordered_models.append(best_model_name)

    for fallback_name in ('SVM (Linear)', 'MLP Neural Network', 'Logistic Regression', 'Random Forest'):
        if fallback_name not in ordered_models:
            ordered_models.append(fallback_name)

    last_error = None
    for model_name in ordered_models:
        try:
            return PREDICTORS[model_name](text)
        except Exception as exc:
            last_error = exc
            continue

    if last_error is not None:
        raise last_error

    raise RuntimeError('No prediction models are available')

# ── Routes ───────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status':     'ok',
        'models':     ['Logistic Regression', 'Random Forest', 'SVM (Linear)', 'MLP Neural Network'],
        'best_model': best_model_name,
        'intents':    len(intents_data['intents']),
        'target_accuracy': model_results.get('summary', {}).get('target_accuracy'),
        'all_models_above_target': model_results.get('summary', {}).get('all_models_above_target'),
        'uptime':     'running',
        'version':    model_results.get('summary', {}).get('model_version', '1.0.0'),
        'python':     __import__('sys').version.split()[0],
    })

@app.route('/predict', methods=['POST'])
def predict():
    """
    Body: { "message": "...", "model": "best|lr|rf|svm|mlp" }
    Returns: { "intent", "confidence", "response", "model_used" }
    """
    body    = request.get_json(force=True)
    message = body.get('message', '').strip()
    model   = body.get('model', 'best').lower()

    if not message:
        return jsonify({'error': 'No message provided'}), 400

    dispatch = {
        'lr':   (predict_lr,   'Logistic Regression'),
        'rf':   (predict_rf,   'Random Forest'),
        'svm':  (predict_svm,  'SVM (Linear)'),
        'mlp':  (predict_mlp,  'MLP Neural Network'),
    }

    if model in dispatch:
        fn, model_used = dispatch[model]
        intent, conf   = fn(message)
    else:
        intent, conf = predict_best(message)
        model_used   = best_model_name

    return jsonify({
        'intent':     intent,
        'confidence': round(conf, 4),
        'response':   get_response_for_tag(intent),
        'model_used': model_used
    })

@app.route('/predict/all', methods=['POST'])
def predict_all():
    """Run all 4 models and return comparison."""
    body    = request.get_json(force=True)
    message = body.get('message', '').strip()
    if not message:
        return jsonify({'error': 'No message provided'}), 400

    i_lr,  c_lr  = predict_lr(message)
    i_rf,  c_rf  = predict_rf(message)
    i_svm, c_svm = predict_svm(message)
    i_mlp, c_mlp = predict_mlp(message)

    return jsonify({
        'message': message,
        'predictions': {
            'Logistic Regression': {'intent': i_lr,  'confidence': round(c_lr,  4)},
            'Random Forest':       {'intent': i_rf,  'confidence': round(c_rf,  4)},
            'SVM (Linear)':        {'intent': i_svm, 'confidence': round(c_svm, 4)},
            'MLP Neural Network':  {'intent': i_mlp, 'confidence': round(c_mlp, 4)},
        }
    })

@app.route('/models/performance', methods=['GET'])
def performance():
    return jsonify(model_results)

@app.route('/intents', methods=['GET'])
def intents():
    return jsonify([
        {'tag': i['tag'], 'patterns': i['patterns'][:3]}
        for i in intents_data['intents']
    ])

if __name__ == '__main__':
    print(f"Starting Flask ML API on http://localhost:{ML_API_PORT}")
    app.run(debug=True, host='0.0.0.0', port=ML_API_PORT)
