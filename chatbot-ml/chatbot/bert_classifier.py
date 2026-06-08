"""
BERT intent classifier – fallback model for low-confidence SVM predictions.

Loaded lazily on first use. Chain: SVM (primary) → BERT (fallback when SVM < 35%).

Requires transformers + torch. Returns (intent, confidence) just like SVM.
Gracefully degrades to SVM-only if torch/transformers are unavailable.
"""

import os
import pickle
from typing import Optional

import numpy as np

try:
    import torch
    from transformers import DistilBertTokenizerFast, DistilBertForSequenceClassification
    _BERT_AVAILABLE = True
except ImportError:
    _BERT_AVAILABLE = False
    DistilBertForSequenceClassification = None  # type: ignore
    DistilBertTokenizerFast = None  # type: ignore

MODEL_DIR = os.path.join(os.path.dirname(__file__), "..", "models", "bert_intent_classifier")
LABEL_ENCODER_PATH = os.path.join(os.path.dirname(__file__), "..", "models", "bert_label_encoder.pkl")

_model: Optional[DistilBertForSequenceClassification] = None
_tokenizer: Optional[DistilBertTokenizerFast] = None
_label_encoder: Optional[object] = None


def _load():
    """Lazy-load model, tokenizer, and label encoder."""
    global _model, _tokenizer, _label_encoder
    if _model is not None:
        return

    if not _BERT_AVAILABLE:
        print("[bert_classifier] transformers/torch not installed — BERT unavailable")
        return

    if not os.path.exists(MODEL_DIR):
        print(f"[bert_classifier] No fine-tuned model found at {MODEL_DIR}. Run train_bert.py first.")
        return

    try:
        print("[bert_classifier] Loading DistilBERT …")
        _tokenizer = DistilBertTokenizerFast.from_pretrained(MODEL_DIR)
        _model = DistilBertForSequenceClassification.from_pretrained(MODEL_DIR)
        _model.eval()

        with open(LABEL_ENCODER_PATH, "rb") as f:
            _label_encoder = pickle.load(f)
        print(f"[bert_classifier] BERT loaded ({len(_label_encoder.classes_)} classes)")
    except Exception as exc:
        print(f"[bert_classifier] Load error: {exc}")


def predict_bert(message: str) -> tuple[str, float]:
    """
    Predict intent using fine-tuned DistilBERT.

    Returns (intent_tag, confidence).
    If BERT is unavailable or model not found, returns ("unknown", 0.0).
    """
    if _model is None:
        _load()
    if _model is None or _tokenizer is None or _label_encoder is None:
        return "unknown", 0.0

    try:
        inputs = _tokenizer(
            [message.strip()],
            truncation=True,
            padding=True,
            max_length=64,
            return_tensors="pt",
        )
        with torch.no_grad():
            outputs = _model(**inputs)
            logits = outputs.logits
            probs = torch.softmax(logits, dim=-1)
            confidence, pred_idx = torch.max(probs, dim=-1)
            confidence = float(confidence.item())
            pred_idx = int(pred_idx.item())

        intent = _label_encoder.inverse_transform([pred_idx])[0]
        return intent, confidence
    except Exception as exc:
        print(f"[bert_classifier] Inference error: {exc}")
        return "unknown", 0.0


def is_available() -> bool:
    """Check if BERT model is loaded and ready."""
    if _model is None:
        _load()
    return _model is not None
