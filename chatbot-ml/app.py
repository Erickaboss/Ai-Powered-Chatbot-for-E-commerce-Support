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

app = Flask(__name__)
CORS(app)

# ── Load models & artifacts ──────────────────────────────────
print("Loading models...")

with open('dataset/intents.json') as f:
    intents_data = json.load(f)

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
    best_model_name = model_results.get('best_model', 'MLP Neural Network')
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

def predict_best(text: str):
    """Use MLP as primary (best performing), fallback to SVM."""
    try:
        return predict_mlp(text)
    except Exception:
        return predict_svm(text)

# ── Routes ───────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status':     'ok',
        'models':     ['Logistic Regression', 'Random Forest', 'SVM (RBF Kernel)', 'MLP Neural Network'],
        'best_model': best_model_name,
        'intents':    len(intents_data['intents']),
        'uptime':     'running',
        'version':    '1.0.0',
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
        'svm':  (predict_svm,  'SVM (RBF Kernel)'),
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
            'SVM (RBF Kernel)':    {'intent': i_svm, 'confidence': round(c_svm, 4)},
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
    print("Starting Flask ML API on http://localhost:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
