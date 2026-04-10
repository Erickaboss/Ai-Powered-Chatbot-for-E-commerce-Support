"""
Shared helpers for loading and merging chatbot intent datasets.
"""

from __future__ import annotations

import json
import os
from typing import Iterable

DEFAULT_DATASET_PATHS = (
    'dataset/intents.json',
    'dataset/intents_part2.json',
)


def _extract_intents(payload: dict) -> list[dict]:
    for key in ('intents', 'intents_part2'):
        intents = payload.get(key)
        if isinstance(intents, list):
            return intents
    return []


def _dedupe_strings(values: Iterable[str]) -> list[str]:
    seen: dict[str, bool] = {}
    ordered: list[str] = []

    for value in values:
        if not isinstance(value, str):
            continue
        cleaned = value.strip()
        if not cleaned or cleaned in seen:
            continue
        seen[cleaned] = True
        ordered.append(cleaned)

    return ordered


def load_merged_intents(paths: Iterable[str] = DEFAULT_DATASET_PATHS) -> dict:
    merged: dict[str, dict] = {}
    order: list[str] = []

    for path in paths:
        if not os.path.exists(path):
            continue

        with open(path, encoding='utf-8') as handle:
            payload = json.load(handle)

        for intent in _extract_intents(payload):
            tag = intent.get('tag')
            if not tag:
                continue

            if tag not in merged:
                merged[tag] = {'tag': tag, 'patterns': [], 'responses': []}
                order.append(tag)

            merged[tag]['patterns'].extend(intent.get('patterns', []))
            merged[tag]['responses'].extend(intent.get('responses', []))

    intents = []
    for tag in order:
        item = merged[tag]
        intents.append({
            'tag': tag,
            'patterns': _dedupe_strings(item['patterns']),
            'responses': _dedupe_strings(item['responses']),
        })

    return {'intents': intents}


def build_training_samples(intents_payload: dict) -> tuple[list[str], list[str]]:
    sentences: list[str] = []
    labels: list[str] = []

    for intent in intents_payload.get('intents', []):
        tag = intent.get('tag')
        for pattern in intent.get('patterns', []):
            if not isinstance(pattern, str):
                continue
            cleaned = pattern.strip()
            if cleaned and tag:
                sentences.append(cleaned.lower())
                labels.append(tag)

    return sentences, labels
