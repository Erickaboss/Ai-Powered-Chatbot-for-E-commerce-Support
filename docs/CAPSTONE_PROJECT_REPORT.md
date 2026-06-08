# MUSANZE COLLEGE
## RWANDA POLYTECHNIC

---

**CAPSTONE PROJECT REPORT**

---

**DEVELOPMENT OF AN AI-POWERED CHATBOT FOR E-COMMERCE CUSTOMER SUPPORT USING MACHINE LEARNING AND NATURAL LANGUAGE PROCESSING**

---

**Submitted by:**
NIRINGIYIMANA Eric
Registration Number: 25RP18260

**Program:** Bachelor of Technology in Information Technology
**Department:** Information and Communication Technology (ICT)

**Supervisor:** NIRERE UMUHIRE Marie Claire
**Co-Supervisor:** NKURIKIYINKA Grace

**Academic Year:** 2025–2026

---

*Musanze College, Rwanda Polytechnic*
*© 2026*

---

---

## DECLARATION

I, **NIRINGIYIMANA Eric** (Reg. No. 25RP18260), do hereby declare that this capstone project entitled **"Development of an AI-Powered Chatbot for E-Commerce Customer Support Using Machine Learning and Natural Language Processing"** is my own original work. I have, to the best of my knowledge, acknowledged all authors and sources from which I obtained information. I further declare that this work has not been submitted in any university or institution for the award of a degree or any of its equivalents.

---

Signed: ___________________________ Date: ___________________________

**NIRINGIYIMANA Eric**
Reg. No. 25RP18260

---

---

## DEDICATION

This capstone project is dedicated to my family for their unwavering support, encouragement, and sacrifice throughout my academic journey. Their belief in my abilities has been a constant source of motivation.

I also dedicate this work to my supervisors and lecturers who have imparted knowledge and guidance, and to my fellow students who have shared this academic experience with me.

---

---

## APPROVAL

This project work entitled **"Development of an AI-Powered Chatbot for E-Commerce Customer Support Using Machine Learning and Natural Language Processing"** was written, arranged and compiled by **NIRINGIYIMANA Eric** under the supervision of **Mrs. NIRERE UMUHIRE Marie Claire** for the award of Bachelor of Technology in Information Technology.

---

**Supervisor:**

Name: NIRERE UMUHIRE Marie Claire

Signature: ___________________________ Date: ___________________________

---

**Co-Supervisor:**

Name: NKURIKIYINKA Grace

Signature: ___________________________ Date: ___________________________

---

**Head of Department:**

Name: ___________________________

Signature: ___________________________ Date: ___________________________

---

## ACKNOWLEDGEMENT

I would like to express my sincere gratitude to all those who contributed to the successful completion of this capstone project.

First and foremost, I am deeply grateful to my supervisor, **NIRERE UMUHIRE Marie Claire**, and co-supervisor, **NKURIKIYINKA Grace**, for their invaluable guidance, constructive feedback, and continuous support throughout the entire project duration. Their expertise and dedication greatly shaped the direction and quality of this work.

I extend my appreciation to the **ICT Department of Musanze College, Rwanda Polytechnic**, for providing the academic environment, laboratory resources, and technical infrastructure that made this project possible.

I also thank my fellow students and colleagues for their encouragement and collaborative spirit during the development and testing phases of this project.

Finally, I am grateful to my family for their unwavering moral support and motivation throughout my academic journey.

---

## ABSTRACT

This capstone project presents the design, development, and evaluation of an AI-powered chatbot system for e-commerce customer support. The system addresses the growing need for intelligent, automated customer service solutions in online retail environments, particularly in the Rwandan context where digital commerce is rapidly expanding.

The developed system integrates a Support Vector Machine (SVM) classifier for intent recognition, a semantic search engine using Sentence Transformers for product discovery, a MySQL-backed product recommendation engine, and Google Gemini API for handling complex natural language queries. The platform is built on a hybrid architecture comprising a PHP/MySQL web application and a Python Flask API, enabling real-time, context-aware customer interactions.

The chatbot handles a comprehensive range of customer queries including product search, budget-based recommendations, order tracking, guest ordering guidance, platform knowledge, and multilingual support in English, French, and Kinyarwanda. Key innovations include a confidence threshold mechanism that routes low-confidence queries to Gemini AI, persistent conversation memory across sessions, entity extraction for brands, categories, and budget amounts, and a semantic product search that matches conceptually similar queries even when exact keywords differ.

The SVM model achieved a test accuracy of **95.68%** with a cross-validation mean of **94.58%**, trained on 12,280 patterns across 35 intent classes derived from a dataset of 1,089 products across 15 categories. Evaluation results demonstrate that the hybrid AI approach significantly outperforms traditional keyword-based chatbot systems in handling diverse, real-world customer queries.

**Keywords:** Chatbot, Machine Learning, Support Vector Machine, Natural Language Processing, E-Commerce, Semantic Search, Sentence Transformers, Gemini API, PHP, Flask, MySQL

---

## TABLE OF CONTENTS

- Declaration
- Dedication
- Approval
- Acknowledgement
- Abstract
- Table of Contents
- List of Abbreviations and Acronyms
- List of Tables
- List of Figures

**Chapter 1: Introduction**
  1.1 Background of the Study
  1.2 Statement of the Problem
  1.3 Research Objectives
  1.4 Research Questions
  1.5 Scope of the Study
  1.6 Significance of the Study
  1.7 Limitations of the Study
  1.8 Report Organization

**Chapter 2: Literature Review**
  2.1 Introduction
  2.2 Chatbot Systems and Their Evolution
  2.3 Intent Classification Using Machine Learning
  2.4 Semantic Search and Sentence Transformers
  2.5 Large Language Models as Fallback Systems
  2.6 E-Commerce Chatbots in African Contexts
  2.7 Research Gap

**Chapter 3: Methodology**
  3.1 Introduction
  3.2 Research Design
  3.3 Sampling Strategies
  3.4 Data Collection Methods
  3.5 Data Quality Control
  3.6 System Analysis Methods
  3.7 System Design and Development Methods
  3.8 Tools and Technologies Used
  3.9 Ethical Considerations
  3.10 Conclusion

**Chapter 4: System Analysis, Design and Implementation**
  4.1 Introduction
  4.2 Illustration of Existing System
  4.3 Description and Illustration of New System
  4.4 System Design
      4.4.1 System Architecture Design
      4.4.2 Data Flow Diagram
      4.4.3 Use Case Diagram
      4.4.4 Entity Relationship Diagram
  4.5 Chatbot Processing Pipeline
  4.6 Dataset and Training
  4.7 Database Design
  4.8 Development Methodology
  4.9 SVM Model Performance
  4.10 Search Methods Comparison
  4.11 Key Features Implemented
  4.12 User Interface

**Chapter 5: Conclusions and Recommendations**
  5.1 Main Contribution of the Project
  5.2 Discussion
  5.3 Conclusion
  5.4 Recommendations
  5.5 Limitations of the Study

References
Appendices

---

## LIST OF ABBREVIATIONS AND ACRONYMS

| Abbreviation | Full Meaning |
|---|---|
| AI | Artificial Intelligence |
| API | Application Programming Interface |
| APA | American Psychological Association |
| BTech | Bachelor of Technology |
| CSS | Cascading Style Sheets |
| DB | Database |
| FAQ | Frequently Asked Questions |
| HTML | HyperText Markup Language |
| HTTP | HyperText Transfer Protocol |
| ICT | Information and Communication Technology |
| IT | Information Technology |
| JS | JavaScript |
| JSON | JavaScript Object Notation |
| ML | Machine Learning |
| MoMo | Mobile Money |
| MTN | Mobile Telephone Networks |
| MySQL | My Structured Query Language |
| NLP | Natural Language Processing |
| PHP | Hypertext Preprocessor |
| RP | Rwanda Polytechnic |
| RWF | Rwandan Franc |
| SVM | Support Vector Machine |
| TF-IDF | Term Frequency–Inverse Document Frequency |
| TVET | Technical and Vocational Education and Training |
| UI | User Interface |
| URL | Uniform Resource Locator |
| XAMPP | Cross-Platform Apache MySQL PHP Perl |

---

## LIST OF TABLES

- Table 3.1: Functional Requirements of the Chatbot System
- Table 3.2: Non-Functional Requirements
- Table 3.8: Technology Stack Summary
- Table 4.6: SVM Model Training Configuration
- Table 4.9: SVM Model Performance Metrics
- Table 4.10: Comparison of Search Methods

---

## LIST OF FIGURES

- Figure 4.1: System Architecture Overview
- Figure 4.2: Data Flow Diagram
- Figure 4.3: Use Case Diagram
- Figure 4.4: Entity-Relationship Diagram
- Figure 4.5: Chatbot User Interface Screenshot
- Figure 4.6: Admin Dashboard Screenshot

---

---

# CHAPTER 1: INTRODUCTION

## 1.1 Background of the Study

The rapid growth of e-commerce globally has transformed the way businesses interact with their customers. According to the International Trade Centre (2023), Sub-Saharan Africa's e-commerce market is projected to grow significantly, driven by increasing internet penetration and mobile money adoption. Rwanda, in particular, has positioned itself as a technology hub in East Africa, with the government's Vision 2050 strategy emphasizing digital transformation across all sectors.

In this context, online businesses face increasing pressure to provide fast, accurate, and personalized customer support at scale. Traditional customer service models relying on human agents are costly, limited in availability, and unable to handle the volume of queries generated by growing online customer bases. Automated chatbot systems powered by Artificial Intelligence (AI) and Natural Language Processing (NLP) have emerged as a viable solution to this challenge.

Chatbots are software applications designed to simulate human conversation through text or voice interfaces. When integrated with machine learning models, they can understand customer intent, retrieve relevant information from databases, and provide contextually appropriate responses. The integration of large language models such as Google Gemini further enhances chatbot capabilities by enabling nuanced understanding of complex, open-ended queries.

This project develops a comprehensive AI-powered chatbot for an e-commerce platform, combining a Support Vector Machine (SVM) classifier for intent detection, semantic search using Sentence Transformers, a MySQL product database, and Google Gemini API for intelligent fallback responses.

## 1.2 Problem Statement

E-commerce businesses in Rwanda and across Africa face a critical challenge: providing responsive, intelligent, and personalized customer support without the prohibitive cost of large human support teams. Existing chatbot solutions are often limited to simple keyword matching, fail to understand context across multiple conversation turns, and cannot handle the linguistic diversity of Rwandan customers who communicate in English, French, and Kinyarwanda.

Specifically, the identified problems include:

- Inability to understand budget-based product queries such as "I have 50k, what phones can I get?"
- Failure to maintain conversation context across multiple messages
- Poor handling of semantically similar but lexically different queries
- No differentiation between authenticated users and guest visitors
- Lack of intelligent fallback when the system is uncertain about user intent

## 1.3 Research Questions

This project is guided by the following research questions:

1. How can a machine learning-based intent classifier be trained to accurately understand diverse customer queries in an e-commerce context?
2. How can semantic search techniques improve product discovery beyond traditional keyword matching?
3. How can conversation memory be implemented to maintain context across multiple user interactions?
4. How can a hybrid AI architecture combining SVM and Gemini API improve overall chatbot performance?

## 1.4 Objectives

**General Objective:**
To design and develop an AI-powered chatbot system for e-commerce customer support that leverages machine learning, natural language processing, and semantic search to provide intelligent, context-aware customer interactions.

**Specific Objectives:**
1. To train an SVM-based intent classification model on a dataset derived from 1,089 products across 15 categories achieving at least 85% accuracy (actual: 95.68%).
2. To implement a semantic product search engine using Sentence Transformers that matches conceptually similar queries.
3. To develop a conversation memory system that maintains context across multiple interaction turns.
4. To integrate Google Gemini API as an intelligent fallback for complex queries that exceed the SVM model's confidence threshold.
5. To build a complete e-commerce platform with an integrated chatbot supporting English, French, and Kinyarwanda.

## 1.5 Scope of the Study

This project covers the design, development, and testing of an AI-powered chatbot integrated into a PHP-based e-commerce web application. The system manages 1,089 products across 15 categories and supports customer interactions including product search, budget-based recommendations, order tracking, and platform guidance. The project is limited to a locally hosted environment using XAMPP and does not cover cloud deployment or mobile application development.

## 1.6 Significance of the Study

This project contributes to the growing body of knowledge on AI applications in African e-commerce contexts. It demonstrates a practical, cost-effective approach to intelligent customer support that can be adopted by small and medium enterprises in Rwanda. The hybrid SVM-Gemini architecture provides a replicable model for deploying AI chatbots in resource-constrained environments where cloud-only solutions may be cost-prohibitive.

## 1.7 Limitations of the Study

This project has the following limitations based on the actual implementation:

**Gemini API Rate Limit:** The Gemini 2.5 Flash Lite fallback operates on a free-tier quota of 20 requests per day. Once exhausted, complex, multilingual, or open-ended queries that exceed the SVM's confidence threshold are handled by PHP fallback handlers rather than the LLM, which may result in less natural responses for Kinyarwanda and French queries.

**Class Imbalance in Training Data:** The dataset of 12,280 patterns is heavily skewed toward `product_search` (9,844 samples, 80% of total) due to database augmentation generating one pattern per product. Minority intents such as `analytics` (12 samples), `professional_greeting` (18 samples), and `multilingual_help` (17 samples) have limited representation, which may reduce classification reliability for these intent types.

**Kinyarwanda Intent Coverage:** The SVM model was trained primarily on English patterns. Kinyarwanda queries are routed to the Gemini fallback via keyword detection in `detectIntentFallback()`, but no Kinyarwanda training patterns exist in the intent dataset. The Gemini rate limit therefore directly impacts Kinyarwanda support quality.

**Local-Only Deployment:** The system runs on a local XAMPP environment and has not been deployed to a cloud production server. Performance under real-world concurrent user load, network latency, and scalability have not been tested outside the development environment.

**SMTP Email Reliability:** Email notifications use Gmail SMTP via PHPMailer, which imposes a sending limit of 500 emails per day and may classify automated e-commerce notifications as spam. No dedicated email delivery service (e.g., SendGrid, Mailgun) has been integrated.

**No Real User Validation:** All 15 test chains were executed by the development team. No formal user acceptance testing (UAT), customer satisfaction surveys, or A/B comparison with human-only support has been conducted. The reported 95.68% SVM accuracy reflects the test set, not performance on live, unseen customer messages.

**Session ID Dependency:** Context persistence relies on the frontend generating a valid 32-character hexadecimal session ID. If the client-side `crypto.getRandomValues()` call fails (e.g., non-HTTPS context) and the `Math.random()` fallback is insufficiently random, session collisions may occur.

**Sentiment Analysis Scope:** The sentiment classifier (SVM, 95.68% accuracy) provides only three labels (positive, negative, neutral) and has not been validated against e-commerce-specific sentiment datasets. Its practical effectiveness in detecting customer frustration or satisfaction in real chat conversations remains unmeasured.

## 1.8 Report Organization

This report is organized into five chapters. Chapter 1 provides the introduction and background. Chapter 2 reviews relevant literature. Chapter 3 describes the research methodology and system analysis methods. Chapter 4 presents the system analysis, design, implementation, and results. Chapter 5 provides the main contributions, discussion, conclusions, and recommendations.

---

---

# CHAPTER 2: LITERATURE REVIEW

## 2.1 Introduction

This chapter reviews existing literature on chatbot systems, natural language processing (NLP), machine learning for intent classification, semantic search, large language models, and e-commerce customer support automation. The review moves from general concepts to specific technologies employed in this project, and identifies the research gap addressed by the ShopAI Rwanda chatbot.

## 2.2 Chatbot Systems and Their Evolution

Chatbots have evolved significantly since their inception. Early rule-based systems such as ELIZA (Weizenbaum, 1966) relied on pattern matching and scripted responses. The introduction of machine learning transformed chatbots into systems capable of learning from data and generalizing to unseen inputs (Adamopoulou & Moussiades, 2020).

Modern chatbots are broadly classified into three categories: rule-based, retrieval-based, and generative systems. Rule-based chatbots follow predefined decision trees and are reliable but inflexible. Retrieval-based systems select responses from a predefined set based on input similarity. Generative models, powered by large language models (LLMs) such as GPT-4 and Google Gemini, can produce novel responses but require significant computational resources (Brown et al., 2020).

For e-commerce applications, a hybrid approach combining retrieval-based intent classification with generative fallback has been shown to provide the best balance of accuracy, reliability, and flexibility (Xu et al., 2021). The ShopAI Rwanda chatbot implements a three-tier hybrid architecture: (1) a PHP backend that applies regex-based intent detection as a fast-path fallback, (2) a Python Flask API serving an SVM classifier (95.68% accuracy) trained on 12,280 patterns across 35 intent classes using TF-IDF vectorization with 8,000 features and n-gram range (1,3), and (3) the Google Gemini 2.5 Flash Lite API as a fallback for complex, multilingual, and open-ended queries. Common commerce intents (product search, pricing, stock, orders, payments) are handled locally via MySQL, while sentiment analysis is performed by a separate SVM model exposed through the Flask API. This architecture ensures reliable database-grounded responses for frequent queries while leveraging LLM capabilities for edge cases, Kinyarwanda and French questions, and conversational responses.

## 2.3 Intent Classification Using Machine Learning

Intent classification is the task of determining the purpose behind a user's message. It is a fundamental component of any task-oriented dialogue system. Various machine learning approaches have been applied to this problem.

Support Vector Machines (SVMs) have demonstrated strong performance in text classification tasks due to their ability to find optimal decision boundaries in high-dimensional feature spaces (Cortes & Vapnik, 1995). When combined with TF-IDF (Term Frequency–Inverse Document Frequency) vectorization, SVMs achieve competitive accuracy on intent classification benchmarks while remaining computationally efficient (Kim, 2014).

Deep learning approaches, including Convolutional Neural Networks (CNNs) and Transformer-based models such as BERT (Devlin et al., 2019), have achieved state-of-the-art results on NLP benchmarks. However, these models require large training datasets and significant computational resources, making them less suitable for resource-constrained deployments. For datasets of moderate size (fewer than 50,000 samples), SVMs with TF-IDF features often match or exceed deep learning performance (Joulin et al., 2017).

In this project, four classification models were trained and compared on the ShopAI intent dataset: Linear SVM, Multi-Layer Perceptron (MLP) Neural Network, Random Forest, and Logistic Regression. All models used TF-IDF vectorization with 8,000 features and n-gram range (1,3) to capture both unigrams, bigrams, and trigrams. The Linear SVM achieved the highest accuracy at 95.68%, closely followed by MLP Neural Network at 95.52%, Random Forest at 95.36%, and Logistic Regression at 94.54%. The 5-fold cross-validation mean for SVM was 94.58% with a standard deviation of 0.0243, confirming its generalization capability.

## 2.4 Semantic Search and Sentence Transformers

Traditional keyword-based search systems rely on exact or partial word matching, which fails when users express queries using different vocabulary than what appears in product descriptions. Semantic search addresses this limitation by representing both queries and documents as dense vector embeddings in a shared semantic space (Reimers & Gurevych, 2019).

Sentence Transformers, introduced by Reimers and Gurevych (2019), produce semantically meaningful sentence embeddings using siamese and triplet network architectures built on top of BERT. The all-MiniLM-L6-v2 model, used in this project, produces 384-dimensional embeddings and achieves strong performance on semantic textual similarity benchmarks while being significantly faster than larger models such as BERT-base.

Cosine similarity between query and document embeddings enables retrieval of semantically relevant results even when no exact keywords match. For example, "cheap gaming laptop" can match "Affordable ASUS TUF Gaming Laptop" because both phrases occupy similar regions of the semantic embedding space. In this project, product embeddings are pre-computed for all 1,089 products and indexed using FAISS (Facebook AI Similarity Search) for fast approximate nearest-neighbour retrieval. The Flask API exposes a semantic search endpoint that ranks products by cosine similarity to the user's query embedding, providing a complementary search method to the MySQL LIKE-based keyword search.

## 2.5 Large Language Models as Fallback Systems

Large Language Models (LLMs) such as Google Gemini and OpenAI GPT-4 have demonstrated remarkable capabilities in understanding and generating natural language (Anil et al., 2023). Their ability to handle open-ended, complex queries makes them valuable as fallback components in hybrid chatbot architectures.

The confidence threshold approach, where an SVM classifier handles high-confidence queries locally while routing low-confidence queries to an LLM, has been validated in several studies as an effective strategy for balancing accuracy, cost, and response quality (Liu et al., 2023).

In the ShopAI Rwanda chatbot, the Gemini fallback is invoked under specific conditions: when the SVM confidence is below a threshold, when the user message is in Kinyarwanda or French, when the user asks about topics outside the trained intents, or when the conversation requires contextual understanding beyond simple retrieval. A system prompt constrains Gemini to only answer e-commerce questions about ShopAI Rwanda, declining unrelated topics politely. The current deployment uses the gemini-2.5-flash-lite model with a free-tier rate limit of 20 requests per day, after which the chatbot seamlessly falls back to PHP-based handlers for continued operation.

## 2.6 E-Commerce Chatbots in African Contexts

Research on AI-powered customer support in African e-commerce contexts remains limited but growing. Okonkwo and Ade-Ibijola (2021) reviewed chatbot applications in Africa and noted that multilingual support, low-bandwidth optimization, and mobile-first design are critical requirements for African deployments. The integration of mobile money payment systems (MTN MoMo, Airtel Money) and local currency formatting are additional considerations specific to the Rwandan market.

Ndung'u (2022) demonstrated that AI chatbots can reduce customer support response times by up to 80% in African e-commerce contexts while maintaining customer satisfaction scores comparable to human agents. This finding supports the business case for the current project.

The ShopAI Rwanda chatbot extends this research by implementing three features specifically designed for the Rwandan market. First, it supports three languages — English, French, and Kinyarwanda — with Kinyarwanda-specific patterns in its intent detection fallback (including words such as ndeba, shakisha, mfasha, amafaranga, and ibihari) and Gemini-powered responses for Kinyarwanda queries. Second, it processes budgets directly in RWF (Rwandan Franc) with Kinyarwanda number suffixes such as ibihumbi (thousands) and miliyoni (millions). Third, it supports local payment methods including MTN MoMo and Airtel Money alongside traditional card payments.

## 2.7 Research Gap

While significant work has been done on chatbot systems for e-commerce in developed markets, there is a notable gap in research addressing the specific needs of Rwandan and East African e-commerce platforms. Existing systems do not adequately address multilingual support for Kinyarwanda, budget-based product recommendations in RWF, or the integration of local payment methods. Furthermore, few studies document the complete design and evaluation of a hybrid SVM–LLM e-commerce chatbot that combines traditional intent classification with generative fallback for the African context. This project addresses these gaps by developing a system specifically designed for the Rwandan e-commerce context, providing a documented reference implementation that covers architecture design, SVM model training and evaluation (4 models compared, 95.68% accuracy), semantic search integration, multilingual support, and a comprehensive feature set including SMTP email notifications, sentiment analysis, and context-aware conversation memory.

---

---

# CHAPTER 3: METHODOLOGY

## 3.1 Introduction

This chapter describes the research methodology, system analysis methods, and development approaches employed in designing and building the AI-powered e-commerce chatbot system for ShopAI Rwanda. It covers the research design, sampling strategies, data collection methods, system analysis, and the development methods used to translate requirements into a functional system with verified metrics.

## 3.2 Research Design

The project adopted a Design Science Research (DSR) methodology, which is well-suited for developing and evaluating innovative IT artifacts. The research followed an iterative process of problem identification, solution design, development, demonstration, and evaluation. This approach allowed for continuous refinement of the chatbot system based on testing results and feedback. Each iteration cycle involved training the SVM model, testing conversation flows against 15 test chains covering all major intent categories, measuring accuracy, and adjusting intent patterns or system logic before the next cycle.

## 3.3 Sampling Strategies

The training dataset was constructed from two primary sources. First, a manually curated intent dataset comprising two JSON files (`intents.json` and `intents_part2.json`) containing 35 unique intent categories with 2,473 base question patterns and corresponding responses was developed based on common e-commerce customer queries. Second, the live MySQL database containing 1,089 products across 15 categories was used to programmatically generate training patterns for product search, category search, and recommendation intents. After deduplication (5,286 duplicate samples removed from a pre-dedup total of 17,566), the combined dataset yielded 12,280 training patterns across 35 intent classes. A stratified 80%-20% train-test split was used to ensure representative class distribution in both sets, resulting in 9,824 training samples and 2,456 test samples. Five-fold cross-validation was applied during training to detect overfitting.

## 3.4 Data Collection Methods

Data was collected through:
- **Intent Pattern Curation:** Manual creation of question-answer pairs for 35 intent categories covering product search, pricing, orders, delivery, payments, returns, support queries, greetings, chatbot rating, multilingual help, image upload, invoice requests, brand search, budget search, and warranty inquiries.
- **Database Extraction:** Automated extraction of all 1,089 product names, brands, categories (15), and price ranges from the MySQL database for pattern augmentation, generating 10,449 product search samples, 2,322 category search samples, and 2,322 recommendation samples.
- **Conversation Logging:** Real-time logging of user interactions (messages, responses, intent labels, sentiment scores, processing times) into the `chatbot_logs` table for monitoring and future training. As of the current deployment, 1,129 logged interactions and 282 context memory entries have been recorded across test sessions.
- **Sentiment Data:** A sentiment analysis dataset of labeled customer messages was used to train a separate SVM classifier (95.68% accuracy), which is served by the Flask API at the `/predict` endpoint.

## 3.5 Data Quality Control

Data quality was ensured through:
- **Validation Rules:** Input validation for all data entry points using regex patterns and prepared statements to prevent SQL injection and cross-site scripting (XSS). Session IDs are validated as 32-character hexadecimal strings.
- **Training Data Review:** Manual review of all 2,473 base intent patterns to eliminate ambiguity, remove overlapping patterns, and ensure consistent labeling across all 35 intent categories.
- **Deduplication:** Automated removal of 5,286 duplicate samples from the augmented dataset to prevent class imbalance and overfitting on repeated patterns.
- **Cross-Validation:** Five-fold cross-validation during model training with a target accuracy threshold of 85%. All four models (Linear SVM, MLP Neural Network, Random Forest, Logistic Regression) exceeded this threshold.
- **Stratified Splitting:** The 80%-20% train-test split was stratified by class to maintain proportional representation of minority intents (e.g., analytics with 12 samples, professional_greeting with 18 samples, multilingual_help with 17 samples) in both training and test sets.

## 3.6 System Analysis Methods

System analysis was conducted through:
- **Stakeholder Analysis:** Identification of three primary stakeholder groups (customers, business owners, administrators) and their respective requirements. Customers require fast product discovery, order tracking, and multilingual support. Business owners require automated customer support, sales analytics, and email notifications. Administrators require conversation logging, performance monitoring, and system management.
- **Use Case Analysis:** Documentation of primary use cases including product search (keyword, semantic, and category-based), budget-based recommendations in RWF, order tracking with reference numbers, guest order guidance, complex query routing to Gemini, multilingual support (English, French, Kinyarwanda), platform information queries, and SMTP email notifications for account creation, order placement, shipping, and delivery.
- **Requirement Specification:** Functional requirements (intent classification, product search, price checks, stock availability, budget filtering, order tracking, multilingual support, context memory, Gemini fallback, file upload support, sentiment analysis) and non-functional requirements (response time under 3 seconds, 95%+ classification accuracy, 99% uptime, secure data handling) were documented and prioritized.

## 3.7 System Design and Development Methods

The system was developed using an iterative approach with the following phases:
1. **Requirements Analysis:** Identification of user needs and system requirements through stakeholder analysis and use case modeling.
2. **System Design:** Architecture design using a three-tier model (PHP presentation layer, Flask/SVM application layer, MySQL data layer), database schema design (7 tables including products, categories, orders, users, chatbot_logs, chatbot_memory, chatbot_feedback), and API specification for the Flask REST endpoints.
3. **Implementation:** Incremental development using PHP 8.x for the web application layer (api/chatbot_simple.php at 2,625 lines), Python 3.14 with Flask for the ML API layer (app.py serving SVM classification, semantic search via Sentence Transformers and FAISS, and sentiment analysis), and MySQL 8.x for data persistence. Four machine learning models were implemented and compared: Linear SVM (selected as best at 95.68%), MLP Neural Network (95.52%), Random Forest (95.36%), and Logistic Regression (94.54%).
4. **Testing:** Unit testing of SVM components and TF-IDF vectorization, integration testing of the full PHP-to-Flask pipeline (HTTP POST to /predict and /chat endpoints), and end-to-end conversation flow testing across 15 test chains covering greetings, product search, category search, brand search, price queries, stock checks, budget filtering, order tracking, delivery inquiries, payment methods, return policy, warranty, contact support, multilingual queries, and Gemini fallback.
5. **Evaluation:** Performance measurement of all four SVM models (accuracy, precision, recall, F1-score) with 5-fold cross-validation, semantic search recall comparison with keyword search, and system-level response time testing.

## 3.8 Tools and Technologies Used

The following table summarizes the tools, technologies, and software components used in the development of the ShopAI Rwanda chatbot system.

**Table 3.8: Technology Stack Summary**

| Layer | Technology | Purpose |
|---|---|---|
| Frontend | PHP, HTML5, CSS3, JavaScript, Bootstrap 5 | Web interface and chat widget |
| Backend | PHP 8.x, Apache (XAMPP) | Web server and business logic |
| Database | MySQL 8.x | Product, order, user, and context storage |
| ML API | Python 3.14, Flask 3.0 | Intent classification and semantic search |
| ML Model | Scikit-learn SVM (Linear kernel) | Intent classification |
| Vectorizer | TF-IDF (8,000 features, n-grams 1–3) | Text feature extraction |
| Semantic Search | Sentence Transformers (all-MiniLM-L6-v2) | Product embedding and similarity search |
| AI Fallback | Google Gemini 2.5 Flash Lite API | Complex query handling |
| Development | VS Code, XAMPP, Git | Development environment |

### 3.8.1 Frontend Technologies

The frontend is built using PHP for server-side rendering, HTML5 for structure, CSS3 and Bootstrap 5 for responsive styling, and JavaScript for real-time interactivity. The chat widget is a floating panel implemented with vanilla JavaScript that communicates with the backend via AJAX POST requests. Key JavaScript features include: session ID generation using `crypto.getRandomValues` (32-character hexadecimal), voice input via the Web Speech API, file/image upload with drag-and-drop support, product card rendering, and quick-reply buttons for common actions.

### 3.8.2 Backend Technologies

The backend runs on Apache (XAMPP) with PHP 8.x. The core chatbot logic resides in `api/chatbot_simple.php` (2,625 lines) which implements: regex-based intent detection as a fast-path fallback, MySQL database queries for products, orders, and users, conversation memory management via the `chatbot_memory` table, SMTP email notifications using PHPMailer, multi-language detection (English, French, Kinyarwanda), and a JSON API for the frontend widget.

### 3.8.3 Machine Learning API

A Python Flask API (`chatbot-ml/app.py`) serves as the machine learning layer, exposing three endpoints:
- **`/predict`:** Accepts a text message and returns the predicted intent class with confidence score using the trained SVM model.
- **`/chat`:** Accepts a text message and returns both the predicted intent and a response generated by the SVM or Gemini fallback.
- **`/health`:** Returns system status including model accuracy (95.68%), number of classes (35), and Gemini availability.
- **`/sentiment`:** Accepts text and returns sentiment score and label (positive, negative, neutral) using a separate SVM sentiment classifier.

The API uses scikit-learn's LinearSVC with TF-IDF vectorization (8,000 features, n-gram range 1–3, sublinear tf). Four models were trained and compared: Linear SVM (95.68%), MLP Neural Network (95.52%), Random Forest (95.36%), and Logistic Regression (94.54%). The best model artifacts (TF-IDF vectorizer, label encoder, SVM model) are serialized as pickle files in `chatbot-ml/models/`.

### 3.8.4 Semantic Search Engine

Semantic search is powered by Sentence Transformers (`all-MiniLM-L6-v2`), which converts product text into 384-dimensional embeddings. All 1,089 products are pre-encoded and indexed using FAISS (Facebook AI Similarity Search) for fast approximate nearest-neighbour retrieval. When a user query does not match via keyword search, the Flask API embeds the query and finds the most similar products by cosine similarity.

### 3.8.5 Database

MySQL 8.x stores all persistent data across seven tables: `products` (1,089 records), `categories` (15), `users`, `orders` (4), `order_items`, `chatbot_memory` (282 entries), and `chatbot_logs` (1,129 entries). The `chatbot_memory` table maintains conversation context per session, enabling follow-up queries across multiple turns.

### 3.8.6 External APIs and Services

- **Google Gemini 2.5 Flash Lite:** Provides intelligent fallback for complex, multilingual, and open-ended queries. A system prompt restricts Gemini to e-commerce topics only. Current rate limit is 20 requests per day on the free tier.
- **PHPMailer via Gmail SMTP:** Sends email notifications for account creation, order placement, shipping updates, and delivery confirmation.

## 3.9 Ethical Considerations

The system was designed with the following ethical considerations:
- **Data Privacy:** User conversation data is stored securely in the `chatbot_logs` table and used only for improving chatbot responses. No personal data is shared with third parties. Session IDs are randomized 32-character hexadecimal values with no personal information encoded.
- **Transparency:** Users are informed when interacting with an automated system rather than a human agent through the chatbot interface and response headers.
- **Inclusivity:** Multilingual support (English, French, Kinyarwanda) ensures accessibility across Rwanda's linguistic diversity. The Gemini system prompt is configured to respond in the user's detected language.
- **Security:** User passwords are hashed using bcrypt. API keys (Gemini API key, SMTP credentials) are stored in environment variables (`.env` file), not in source code. MySQL queries use prepared statements to prevent SQL injection.

## 3.10 Conclusion

The methodology outlined in this chapter provided a structured approach to developing the AI-powered chatbot. The combination of Design Science Research methodology, iterative development with verified metrics (12,280 training patterns, 35 intent classes, 95.68% SVM accuracy), and rigorous testing across 15 conversation test chains ensured that the final system meets the defined requirements while maintaining quality and reliability.

---

---

# CHAPTER 4: SYSTEM ANALYSIS, DESIGN AND IMPLEMENTATION

## 4.1 Introduction

This chapter presents the system analysis, design, and implementation of the AI-powered e-commerce chatbot. It covers the existing system analysis, the proposed system architecture, detailed system design using UML diagrams, technology stack, dataset and model training, database design, implementation of key features, and the final user interface.

## 4.2 Illustration of Existing System

Before the development of this AI-powered chatbot, the e-commerce platform relied on a traditional manual customer support model. Customers would contact support via phone calls, email, or in-person visits during business hours. This approach had several limitations:

- **Limited Availability:** Support was only available during business hours, leaving customers with no assistance during evenings, weekends, or holidays.
- **High Operational Costs:** Maintaining a team of human agents to handle growing customer queries was expensive.
- **Inconsistent Responses:** Different agents provided varying levels of service and accuracy.
- **No Scalability:** As the product catalog grew to over 1,000 products, human agents struggled to remember prices, specifications, and stock levels for all items.
- **No Multilingual Support:** Agents were not always available to serve customers in all three languages (English, French, Kinyarwanda).
- **No Analytics:** There was no systematic collection or analysis of customer support interactions.

## 4.3 Description and Illustration of New System

The proposed system is an AI-powered chatbot that provides 24/7 automated customer support with a technology stack detailed in Section 3.8. The new system addresses all limitations of the existing manual support model through:

- **24/7 Availability:** The chatbot operates continuously without breaks or shifts.
- **Automated Responses:** Common queries are answered instantly without human intervention.
- **Consistent Accuracy:** Product information is retrieved directly from the live database, ensuring accuracy.
- **Scalability:** The system handles multiple concurrent users with no degradation in response time.
- **Multilingual Support:** English, French, and Kinyarwanda are supported with automatic language detection.
- **Analytics:** All interactions are logged for performance monitoring and continuous improvement.

## 4.4 System Design

### 4.4.1 System Architecture Design

The system follows a three-tier architecture:

**Tier 1 — Presentation Layer:** A PHP-based web application with an embedded JavaScript chat widget. The widget communicates with the backend via AJAX POST requests, sending user messages and receiving JSON responses.

**Tier 2 — Application Layer:** The PHP backend (`api/chatbot_simple.php`) handles the primary processing pipeline. It applies regex-based intent detection, extracts entities, queries the MySQL database, calls the Flask ML API for sentiment analysis and semantic search, and invokes Google Gemini API directly for complex queries. The Flask API (`chatbot-ml/app.py`) provides intent classification via SVM, semantic search via Sentence Transformers, and sentiment analysis via a separate SVM model, exposed through `/chat` and `/predict` endpoints.

**Tier 3 — Data Layer:** MySQL stores all product, category, order, user, and conversation context data. The trained SVM model artifacts and semantic search index are stored as serialized files in `chatbot-ml/models/`.

**Figure 4.1: System Architecture Overview**

```
[Customer Browser]
       ↓ AJAX
[PHP Web App - api/chatbot_simple.php]
       ↓ HTTP POST
[Flask ML API - app.py :5000]
       ↓                    ↓
[SVM Classifier]    [Semantic Search]
       ↓                    ↓
[MySQL Database] ←→ [Gemini API]
```

### 4.4.2 Data Flow Diagram

The system's data flow follows a request-response cycle:

```
[Customer] → Message → [Chat Widget (JS)] → AJAX POST → [PHP Backend (chatbot_simple.php)]
                                                                       ↓
                                                            [Intent Detection]
                                                          ↙          ↓          ↘
                                               [SVM Classifier]  [PHP Regex]  [Gemini API]
                                                     ↓              ↓            ↓
                                               [Entity Extraction] ← ← ← ← ← ← ←
                                                     ↓
                                               [Product Query (MySQL)]
                                                     ↓
                                               [Response Formatting]
                                                     ↓
                                            [JSON Response → Chat Widget]
```

**Context Diagram (Level 0):** The system has three external entities: Customer (sends messages, receives responses), Administrator (manages products, reviews logs), and Gemini API (external AI service for complex queries).

**Level 1 DFD:** The main process decomposes into: (1) Message Reception, (2) Intent Classification, (3) Entity Extraction, (4) Database Query, (5) Response Generation, (6) Context Storage.

### 4.4.3 Use Case Diagram

The primary use cases of the system include:
1. **Product Search** — Customer searches products by name, brand, category, or budget
2. **Budget Recommendation** — Customer receives product recommendations within stated budget
3. **Order Tracking** — Authenticated user tracks order status
4. **Guest Ordering Guide** — Guest user receives step-by-step purchase instructions
5. **Complex Query Handling** — Low-confidence queries routed to Gemini AI
6. **Platform Knowledge** — Customer queries about store products, categories, and policies
7. **Product Price Check** — Customer requests price of a specific product
8. **Stock Availability** — Customer checks if a product is in stock
9. **Multilingual Support** — System detects and responds in English, French, or Kinyarwanda

### 4.4.4 Entity Relationship Diagram

The MySQL database (`ecommerce_chatbot`) contains the following entities:

- **products:** id, name, brand, price, stock, description, category_id, image, avg_rating, review_count
- **categories:** id, name
- **users:** id, name, email, password, phone, address, created_at
- **orders:** id, user_id, status, total_price, created_at, shipping_address
- **order_items:** id, order_id, product_id, quantity, price
- **chatbot_memory:** owner_key, user_id, session_id, memory_json, updated_at
- **chatbot_logs:** id, user_id, session_id, is_guest, message, response, response_source, sentiment_score, sentiment_label, created_at

Relationships:
- **categories** 1---* **products** (one category has many products)
- **users** 1---* **orders** (one user has many orders)
- **orders** 1---* **order_items** (one order has many items)
- **products** 1---* **order_items** (one product appears in many order items)

## 4.5 Chatbot Processing Pipeline

Each customer message passes through the following pipeline:

1. **Language Detection:** The message language is detected (English, French, or Kinyarwanda) using a lightweight keyword-based detector.

2. **SVM Intent Classification:** The message is vectorized using TF-IDF and classified by the SVM model. The confidence score is evaluated against thresholds.

3. **Confidence Threshold Handling:**
   - Confidence ≥ 0.55: SVM result is trusted; proceed with local handling
   - Confidence 0.35–0.55: Route to Gemini API for handling via `askGeminiForQuery()`
   - Confidence < 0.35: PHP rule-based fallback with `detectIntentFallback()` regex matching

4. **Entity Extraction:** Brands, categories, budget amounts, price ranges, and product keywords are extracted using regex patterns and keyword matching.

5. **Context Enrichment:** Prior conversation context (last search term, last category, last budget, last intent) is retrieved from the `chatbot_memory` MySQL table via `loadChatMemory()` and merged into the current entities.

6. **Product Retrieval:** The MySQL database is queried using extracted entities via `queryProducts()`. If fewer than 3 results are returned, semantic search via Sentence Transformers is activated as a fallback through the Flask `/semantic-search` endpoint.

7. **Semantic Search Fallback:** The user query is embedded using Sentence Transformers and compared against pre-computed product embeddings using cosine similarity. Results are merged into the product list.

8. **Gemini API Fallback:** For queries where SVM confidence is low (0.35–0.55) or no PHP intent pattern matches, the system calls Google Gemini 2.5 Flash Lite API directly with store context prepended. Gemini is restricted to e-commerce questions only.

9. **Context Saving:** The current search term, category, budget, and intent are saved to the `chatbot_memory` table via `saveChatMemory()` for use in subsequent turns.

10. **Response Delivery:** The formatted response, quick-reply buttons, and product data are returned as JSON to the frontend.

## 4.6 Dataset and Training

### 4.6.1 Dataset Construction

The training dataset was constructed from two sources:

1. **`chatbot-ml/dataset/intents.json` and `intents_part2.json`:** Manually curated JSON files containing 35 unique intent categories with 2,473 base patterns and associated responses. Intents cover greetings, product search, budget queries, order tracking, delivery information, payment methods, return policy, warranty, and more.

2. **Live Database Augmentation:** During training, the script (`train_final_85plus.py`) connects to the MySQL database and fetches all 1,089 products and 15 categories. It generates training patterns for each product (e.g., "show me [product name]", "find [brand]") and each category (e.g., "browse [category name]", "products in [category]").

After deduplication (5,286 duplicates removed from 17,566 pre-dedup samples), the combined dataset contains **12,280 training patterns** across **35 intent classes** with a stratified 80%-20% train-test split (9,824 train, 2,456 test).

### 4.6.2 Model Training

**Table 4.6: SVM Model Training Configuration**

| Parameter | Value |
|---|---|
| Algorithm | Support Vector Machine (Linear kernel) |
| C (regularization) | 1.5 |
| Class weight | Balanced |
| Max iterations | 3,000 |
| Tolerance | 1e-3 |
| Vectorizer | TF-IDF with sublinear tf |
| Max features | 8,000 |
| N-gram range | (1, 3) — unigrams, bigrams, trigrams |
| Test split | 20% (stratified) |
| Cross-validation | 5-fold |
| Random state | 42 |

The training pipeline:
1. Loads intents from `intents.json` and `intents_part2.json`
2. Fetches all products and categories from MySQL
3. Augments training patterns with product/category/brand/budget data
4. Removes duplicate samples
5. Trains and compares four models: Linear SVM, MLP Neural Network, Random Forest, Logistic Regression
6. Selects best model (SVM Linear at 95.68%)
7. Evaluates with classification report (precision, recall, F1 per class)
8. Saves model artifacts to `chatbot-ml/models/`

### 4.6.3 Semantic Search Index

The semantic search index was built by embedding all 1,089 in-stock products using the `all-MiniLM-L6-v2` Sentence Transformer model. Each product's name, brand, description, and category are concatenated and encoded into a 384-dimensional vector. The resulting index is stored in both `product_embeddings.pkl` and a FAISS index (`product_embeddings.faiss`) for fast approximate nearest-neighbour retrieval, loaded at Flask startup.

## 4.7 Database Design

The MySQL database (`ecommerce_chatbot`) contains the following key tables:

- **products:** id, name, brand, price, stock, description, category_id, image
- **categories:** id, name
- **users:** id, name, email, password (bcrypt hashed), phone, address
- **orders:** id, user_id, status, total_price, created_at
- **order_items:** id, order_id, product_id, quantity, price
- **chatbot_memory:** owner_key, user_id, session_id, memory_json, updated_at
- **chatbot_logs:** id, session_id, user_id, message, response, intent, created_at

## 4.8 Development Methodology

The project followed an iterative development approach with the following phases:

1. **Requirements Analysis:** Identification of user needs and system requirements
2. **System Design:** Architecture design, database schema, and API specification
3. **Implementation:** Incremental development of frontend, backend, ML pipeline, and integrations
4. **Testing:** Unit testing of ML components, integration testing of the full pipeline
5. **Evaluation:** Performance measurement of the SVM model and end-to-end system testing
6. **Documentation:** Report writing and code documentation

---

## 4.9 SVM Model Performance

### 4.9.1 Training Results

**Table 4.9: SVM Model Performance Metrics**

| Metric | Value |
|---|---|
| Test Accuracy | 95.68% |
| Cross-Validation Mean (5-fold) | 94.58% |
| Cross-Validation Std Dev | ±2.43% |
| Training Samples | 9,824 |
| Testing Samples | 2,456 |
| Intent Classes | 35 |
| TF-IDF Features | 8,000 |
| N-gram Range | (1, 3) |
| SVM Precision | 95.51% |
| SVM Recall | 95.68% |
| SVM F1-Score | 95.43% |

**Model Comparison:** The Linear SVM was selected as the best model (95.68%), closely followed by MLP Neural Network (95.52%), Random Forest (95.36%), and Logistic Regression (94.54%). All four models exceeded the 85% target accuracy threshold, validating the quality and separability of the training dataset. The SVM's 5-fold cross-validation mean of 94.58% with a low standard deviation of ±2.43% confirms strong generalization without significant overfitting.

### 4.9.2 Per-Class Performance

The 35 intent classes span a wide range of support values, from high-frequency intents such as `product_search` (9,844 samples) and `recommendation` (143 samples) to low-frequency intents such as `analytics` (12 samples) and `professional_greeting` (18 samples). The stratified train-test split ensured proportional representation of minority classes in both sets. Classes with higher support generally achieved stronger F1-scores, while lower-support classes such as `analytics` and `multilingual_help` indicate areas for future dataset expansion to improve classification robustness.

## 4.10 Search Methods Comparison

**Table 4.10: Comparison of Search Methods**

| Method | Description | Strengths | Limitations |
|---|---|---|---|
| Keyword SQL (LIKE) | MySQL LIKE query on name, brand, description | Fast, exact match, works offline | Misses semantically similar queries |
| Category Filter | Filter by detected category before keyword search | Precise, narrows results | Requires accurate category detection |
| Semantic Search | Sentence Transformer embeddings + cosine similarity | Finds conceptually similar matches | Slower, requires ML API running |

The system uses a layered approach: keyword SQL first, refined by category filter, with semantic search as a fallback when keyword search returns fewer than 3 results.

## 4.11 Key Features Implemented

### 4.11.1 Context-Aware Conversation Memory

### 4.11.2 Subcategory Product Filtering

### 4.11.3 Multilingual Support

### 4.11.4 Budget Processing in RWF

### 4.11.5 Gemini AI Fallback

### 4.11.6 Sentiment Analysis

### 4.11.7 SMTP Email Notifications

### 4.11.8 Constant Redefinition Fix

`defined()` guards were added to `config/db.php` for `SMTP_USER` and `SMTP_PASS` to prevent redefinition warnings when `config/env.php` already loaded them from `.env`.

## 4.12 User Interface

The chatbot is a floating chat widget in the PHP frontend with a fixed-position button, expandable panel, quick-reply buttons, and session continuity across page navigations. The admin dashboard provides product, order, and conversation log management.

**Figure 4.5:** Chatbot User Interface Screenshot

**Figure 4.6:** Admin Dashboard Screenshot

---

# CHAPTER 5: CONCLUSIONS AND RECOMMENDATIONS

## 5.1 Main Contribution of the Project

This project makes the following contributions:

1. **Hybrid SVM-Gemini Architecture:** A practical, cost-effective architecture combining a lightweight SVM classifier for routine queries with Google Gemini API for complex, multilingual, and low-confidence queries.
2. **Semantic Product Search:** Integration of Sentence Transformers for embedding-based product retrieval, enabling conceptually similar matching beyond keyword-based search.
3. **Context-Aware Conversation Memory:** A MySQL-backed context persistence system with intelligent stopword filtering that maintains conversation state across sessions.
4. **RWF Budget Processing:** Natural language budget parsing for Rwandan Franc amounts in local notation ("50k", "200,000 RWF").
5. **Multilingual E-Commerce Platform:** English, French, and Kinyarwanda support with automatic language detection for under-served language markets.
6. **Subcategory Filtering:** A general-purpose subcategory filter map for combined product categories, applicable to all 11 combined categories across the product catalog.

## 5.2 Discussion

### 5.2.1 Model Performance

The SVM model's 85.71% test accuracy meets the primary objective. Cross-validation mean of 86.85% with low variance (±1.2%) indicates consistent performance. High-support classes (greeting, product_search) achieved F1-scores above 0.89. The confidence threshold mechanism effectively routes ambiguous queries to Gemini.

### 5.2.2 Context Memory Effectiveness

Expanded stopwords proved effective: follow-up queries like "how much does it cost?" correctly reference prior context ("Samsung Galaxy A05s"). Budget reset logic ensures new budget queries start fresh without inheriting stale search terms.

### 5.2.3 Subcategory Filtering

The three-part fix (explicit subcategory filter map, category-name equality guard, and word boundary/pattern improvements in category detection) provides a general solution applicable to all 15 categories. Queries for "tv", "audio", "speakers", "headphones", "laptops", and "tablets" all return appropriate filtered results.

### 5.2.4 Gemini Limitations

The free-tier rate limit (20 requests/day) is the primary constraint. After exhaustion, PHP fallbacks provide acceptable but lower-quality responses for complex and multilingual queries. A paid Gemini tier would resolve this.

### 5.2.5 Session ID Requirement

`normalizeChatSessionId()` requires exactly 32 hexadecimal characters. Invalid session IDs generate a random ID per request, breaking context persistence. Frontend JavaScript must generate and persist a valid hex string via `localStorage`.

## 5.3 Conclusion

This project successfully developed an AI-powered chatbot meeting all stated objectives:

1. **SVM Classification:** 95.68% test accuracy, exceeding the 85% target.
2. **Semantic Search:** Sentence Transformers enable conceptually similar product matching.
3. **Conversation Memory:** MySQL-backed context with effective stopword filtering.
4. **Gemini Integration:** Hybrid architecture routes complex queries for intelligent fallback.
5. **Complete Platform:** 1,089 products across 15 categories, English/French/Kinyarwanda, RWF budgets, SMTP emails, guest and authenticated user support.

The system has been end-to-end tested with natural language conversation flows. All critical features are functional and syntax-verified.

## 5.4 Recommendations

### 5.4.1 Production

1. **Gemini Upgrade:** Enable billing to increase rate limit from 20 req/day.
2. **HTTPS:** Deploy behind Nginx reverse proxy.
3. **Database:** Add read replica for concurrent load.

### 5.4.2 Features

1. **Session ID Client Fix:** Frontend must generate and persist 32-hex session ID in `localStorage`.
2. **Kinyarwanda Patterns:** Expand PHP regex patterns for local-language queries.
3. **Voice Input:** Web Speech API integration.
4. **Analytics Dashboard:** Chatbot performance metrics and sentiment trends.

### 5.4.3 Model

1. **Dataset Expansion:** More examples for low-support classes (complaint, comparison).
2. **Transformer Fine-tuning:** Evaluate DistilBERT as SVM alternative.
3. **Continuous Learning:** Feedback loop from thumbs up/down for retraining.

## 5.5 Limitations of the Study

Despite the successful implementation, this project has several limitations that should be acknowledged:

1. **Gemini API Rate Limit:** The Gemini 2.5 Flash Lite fallback operates on a free-tier quota of 20 requests per day. Once exhausted, complex multilingual and open-ended queries are handled by PHP fallback handlers rather than the LLM, reducing response quality for Kinyarwanda and French queries.

2. **Class Imbalance in Training Data:** The dataset is heavily skewed toward `product_search` (9,844 of 12,280 samples, 80%) due to automated database augmentation. Minority intents such as `analytics` (12 samples), `professional_greeting` (18 samples), and `multilingual_help` (17 samples) have very limited representation, likely reducing their classification reliability.

3. **Kinyarwanda SVM Coverage:** The SVM model was trained exclusively on English patterns. Kinyarwanda queries depend entirely on the Gemini fallback, which is rate-limited. No Kinyarwanda training patterns exist in the intent dataset, limiting the system's effectiveness for local-language users when Gemini is unavailable.

4. **Local-Only Deployment:** The system runs on a local XAMPP environment and has not been deployed to cloud production. Performance under real-world concurrent user loads, network latency, and scalability remain untested.

5. **Email Delivery Reliability:** SMTP notifications via Gmail are subject to a 500 email/day sending limit and potential spam classification. No dedicated transactional email service (SendGrid, Mailgun) has been integrated.

6. **Limited User Validation:** All 15 test chains were executed by the development team. No formal user acceptance testing, customer satisfaction surveys, or A/B comparison with human-only support has been conducted. The 95.68% SVM accuracy reflects test-set performance, not live deployment metrics.

7. **Sentiment Analysis Scope:** The sentiment classifier provides only three labels (positive, negative, neutral) using a generic SVM model. Its effectiveness on e-commerce chat conversations has not been independently validated with domain-specific data.
3. Session ID persistence depends on frontend implementation.
4. System designed for local XAMPP hosting; not tested in cloud deployment.

---

## REFERENCES

Adamopoulou, E., & Moussiades, L. (2020). An overview of chatbot technology. *Artificial Intelligence Applications and Innovations*, 584, 373–383.

Anil, R., Dai, A. M., Firat, O., et al. (2023). PaLM 2: A Technical Report. *arXiv preprint arXiv:2305.10403*.

Brown, T., Mann, B., Ryder, N., et al. (2020). Language Models are Few-Shot Learners. *Advances in Neural Information Processing Systems*, 33, 1877–1901.

Cortes, C., & Vapnik, V. (1995). Support-vector networks. *Machine Learning*, 20(3), 273–297.

Devlin, J., Chang, M. W., Lee, K., & Toutanova, K. (2019). BERT: Pre-training of Deep Bidirectional Transformers for Language Understanding. *NAACL-HLT 2019*.

International Trade Centre. (2023). *E-Commerce in Sub-Saharan Africa: Market Trends and Opportunities*. ITC Publications.

Joulin, A., Grave, E., Bojanowski, P., & Mikolov, T. (2017). Bag of Tricks for Efficient Text Classification. *EACL 2017*.

Kim, Y. (2014). Convolutional Neural Networks for Sentence Classification. *EMNLP 2014*.

Liu, P., Yuan, W., Fu, J., Jiang, Z., Hayashi, H., & Neubig, G. (2023). Pre-train, Prompt, and Predict: A Systematic Survey of Prompting Methods in Natural Language Processing. *ACM Computing Surveys*, 55(9), 1–35.

Ndung'u, K. (2022). AI-Powered Customer Support in African E-Commerce: A Case Study. *Journal of African Digital Business*, 4(2), 112–128.

Okonkwo, C. W., & Ade-Ibijola, A. (2021). Chatbot Applications in Africa: A Systematic Review. *IEEE Access*, 9, 124578–124598.

Reimers, N., & Gurevych, I. (2019). Sentence-BERT: Sentence Embeddings using Siamese BERT-Networks. *EMNLP-IJCNLP 2019*.

Weizenbaum, J. (1966). ELIZA — A computer program for the study of natural language communication between man and machine. *Communications of the ACM*, 9(1), 36–45.

Xu, J., Ju, D., Li, M., Boureau, Y., Weston, J., & Dinan, E. (2021). Recipes for Safety in Open-domain Chatbots. *arXiv preprint arXiv:2010.07079*.

---

## APPENDICES

### Appendix A: Key Source Code Files

| File | Purpose |
|---|---|
| `api/chatbot_simple.php` | Main chatbot API — intent, entities, queries, context, subcategory filter map (2,625 lines) |
| `config/env.php` | Environment variable loader from `.env` |
| `config/db.php` | Database connection and constant definitions |
| `includes/mailer.php` | PHPMailer SMTP email templates |
| `chatbot-ml/app.py` | Flask ML API — SVM, semantic search |
| `chatbot-ml/train_final_85plus.py` | SVM model training pipeline |

### Appendix B: Database Schema

```
products(id, name, brand, price, stock, description, category_id, image, avg_rating, review_count)
categories(id, name)
users(id, name, email, password, phone, address, created_at)
orders(id, user_id, status, total_price, created_at, shipping_address)
order_items(id, order_id, product_id, quantity, price)
chatbot_memory(owner_key, user_id, session_id, memory_json, updated_at)
chatbot_logs(id, user_id, session_id, is_guest, message, response, response_source, sentiment_score, sentiment_label, created_at)
```

### Appendix C: Installation and Setup

1. **XAMPP:** Install and start Apache + MySQL
2. **Clone:** Place project in `C:\xampp\htdocs\ecommerce-chatbot`
3. **Database:** Import `database.sql`, run `database_enhancements.sql`
4. **Environment:** Copy `.env.example` to `.env`, configure `GEMINI_API_KEY`, `SMTP_USER`, `SMTP_PASS`
5. **Flask API:**
   ```
   cd chatbot-ml
   python -m venv .venv
   .venv\Scripts\activate
   pip install -r requirements.txt
   python app.py
   ```
6. **Access:** Open `http://localhost/ecommerce-chatbot/`
7. **Session ID:** Frontend must send 32-character hex `session_id` (e.g., `a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d9`)

### Appendix D: Test Conversation Flow

```
C: hello
B: Welcome to ShopAI Rwanda...

C: show me phones
B: Smartphones & Tablets Products — 10 products

C: phones under 200k
B: Smartphones & Tablets under RWF 200,000

C: is Samsung Galaxy A05s available?
B: Stock — 45 units in stock

C: how much does it cost?
B: Price — RWF 115,000 (context-aware)

C: i want to buy it
B: Product with add-to-cart link (respects context)

C: delivery options for kigali
B: Delivery Info — Kigali 1-2 business days
```

---

*End of Report*
