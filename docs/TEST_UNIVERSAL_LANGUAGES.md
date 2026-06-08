# Testing Universal Language Support

## Quick Test Guide

### Test 1: Arabic
```
Message: "مرحبا، كم سعر الهاتف؟"
Translation: "Hello, what is the price of the phone?"
Expected: Response in Arabic
```

### Test 2: Chinese (Simplified)
```
Message: "你好，这个多少钱？"
Translation: "Hello, how much is this?"
Expected: Response in Chinese
```

### Test 3: Japanese
```
Message: "こんにちは、これはいくらですか？"
Translation: "Hello, how much is this?"
Expected: Response in Japanese
```

### Test 4: Korean
```
Message: "안녕하세요, 이것은 얼마입니까?"
Translation: "Hello, how much is this?"
Expected: Response in Korean
```

### Test 5: Russian
```
Message: "Привет, сколько это стоит?"
Translation: "Hello, how much does this cost?"
Expected: Response in Russian
```

### Test 6: Spanish
```
Message: "Hola, ¿cuánto cuesta esto?"
Translation: "Hello, how much does this cost?"
Expected: Response in Spanish
```

### Test 7: Portuguese
```
Message: "Olá, quanto custa isso?"
Translation: "Hello, how much does this cost?"
Expected: Response in Portuguese
```

### Test 8: German
```
Message: "Hallo, wie viel kostet das?"
Translation: "Hello, how much does this cost?"
Expected: Response in German
```

### Test 9: Italian
```
Message: "Ciao, quanto costa questo?"
Translation: "Hello, how much does this cost?"
Expected: Response in Italian
```

### Test 10: Turkish
```
Message: "Merhaba, bu ne kadar?"
Translation: "Hello, how much is this?"
Expected: Response in Turkish
```

### Test 11: Vietnamese
```
Message: "Xin chào, cái này bao nhiêu tiền?"
Translation: "Hello, how much is this?"
Expected: Response in Vietnamese
```

### Test 12: Thai
```
Message: "สวัสดี นี่ราคาเท่าไหร่?"
Translation: "Hello, what is the price of this?"
Expected: Response in Thai
```

### Test 13: Hindi
```
Message: "नमस्ते, यह कितना है?"
Translation: "Hello, how much is this?"
Expected: Response in Hindi
```

### Test 14: Swahili
```
Message: "Habari, hii ni bei gani?"
Translation: "Hello, what is the price of this?"
Expected: Response in Swahili
```

### Test 15: Amharic
```
Message: "ሰላም, ይህ ስንት ነው?"
Translation: "Hello, how much is this?"
Expected: Response in Amharic
```

### Test 16: Kinyarwanda
```
Message: "Mwaramutse, zingahe?"
Translation: "Hello, how much?"
Expected: Response in Kinyarwanda
```

### Test 17: French
```
Message: "Bonjour, combien coûte ceci?"
Translation: "Hello, how much does this cost?"
Expected: Response in French
```

### Test 18: English
```
Message: "Hello, how much is this?"
Translation: "Hello, how much is this?"
Expected: Response in English
```

### Test 19: Dutch
```
Message: "Hallo, hoeveel kost dit?"
Translation: "Hello, how much does this cost?"
Expected: Response in Dutch
```

### Test 20: Polish
```
Message: "Cześć, ile to kosztuje?"
Translation: "Hello, how much does this cost?"
Expected: Response in Polish
```

## Testing via API

### Using cURL

```bash
# Test Arabic
curl -X POST http://localhost:5001/predict \
  -H "Content-Type: application/json" \
  -d '{"message": "مرحبا، كم سعر الهاتف؟"}'

# Test Chinese
curl -X POST http://localhost:5001/predict \
  -H "Content-Type: application/json" \
  -d '{"message": "你好，这个多少钱？"}'

# Test Spanish
curl -X POST http://localhost:5001/predict \
  -H "Content-Type: application/json" \
  -d '{"message": "Hola, ¿cuánto cuesta esto?"}'
```

### Expected Response Format

```json
{
  "intent": "greeting",
  "confidence": 0.95,
  "response": "Hello! Welcome to our store. How can I help you today?",
  "model_used": "MLP Neural Network",
  "language": "ar",
  "language_name": "Arabic"
}
```

## Testing via Web Interface

1. Open your chatbot in a web browser
2. Type a message in any language
3. Verify the chatbot responds in the same language
4. Check the browser console for language detection info

## Verification Checklist

- [ ] Arabic messages detected and responded to in Arabic
- [ ] Chinese messages detected and responded to in Chinese
- [ ] Japanese messages detected and responded to in Japanese
- [ ] Korean messages detected and responded to in Korean
- [ ] Russian messages detected and responded to in Russian
- [ ] Spanish messages detected and responded to in Spanish
- [ ] Portuguese messages detected and responded to in Portuguese
- [ ] German messages detected and responded to in German
- [ ] Italian messages detected and responded to in Italian
- [ ] Turkish messages detected and responded to in Turkish
- [ ] Vietnamese messages detected and responded to in Vietnamese
- [ ] Thai messages detected and responded to in Thai
- [ ] Hindi messages detected and responded to in Hindi
- [ ] Swahili messages detected and responded to in Swahili
- [ ] Amharic messages detected and responded to in Amharic
- [ ] Kinyarwanda messages detected and responded to in Kinyarwanda
- [ ] French messages detected and responded to in French
- [ ] English messages detected and responded to in English
- [ ] Dutch messages detected and responded to in Dutch
- [ ] Polish messages detected and responded to in Polish

## Troubleshooting

### Issue: Language not detected correctly

**Solution**:
1. Check if the message contains language-specific characters
2. Verify the language is in the supported list
3. Try a message with more common words
4. Check the language detection logs

### Issue: Response in wrong language

**Solution**:
1. Verify the language was detected correctly
2. Check if the response template exists for that language
3. Ensure the language code is correct (e.g., 'ar' for Arabic)
4. Check for encoding issues (UTF-8)

### Issue: Performance is slow

**Solution**:
1. Language detection should be < 5ms
2. Check server load
3. Verify database connection
4. Monitor network latency

## Performance Metrics

Expected results:
- **Detection Accuracy**: 95%+
- **Detection Speed**: < 5ms
- **Response Time**: < 500ms
- **Supported Languages**: 20+

## Notes

- All tests should use UTF-8 encoding
- Messages should be in a single language (not mixed)
- Short messages may have lower detection accuracy
- The system defaults to English if unsure

---

**Test Date**: April 13, 2026
**Status**: Ready for Testing
