# Bachelor of Technology Capstone Project Report

**DEPARTMENT:** ICT  
**PROGRAM:** INFORMATION TECHNOLOGY  
**RQF LEVEL:** 8  

**Project Topic:** AI-Powered E-Commerce Chatbot with Machine Learning Integration  

**Submitted by:** [Your Name]  
**Reg NO:** [Your Registration Number]  

**Supervisor:** [Supervisor Name]  

Musanze, Month 2025  

---

## DECLARATION

I [Your Full Name] do declare that this capstone project is my own work. I have to the best of my knowledge acknowledged all authors or sources from where I got information. I further declare that this work has not been submitted in any university or institution for the award of a degree or any of its equivalents.  

Signed: ___________________________ Date: ___________________________  

---

## APPROVAL

This project work was written, arranged and compiled by [Your Name] under the supervision of [Supervisor Name] for the award of Bachelor degree in Information Technology.  

[Supervisor Name] ___________________________ Date & Signature  

[Head of Department Name] ___________________________ Date & Signature  

---

## TABLE OF CONTENTS

1. INTRODUCTION  
   1.1 Background of the study  
   1.2 Statement of the problem  
   1.3 Research Objective  
   1.4 Research questions  
   1.5 Hypotheses / Postulates / Research Questions  
   1.6 Significance/Importance/Contribution  
   1.7 Scope  
   1.8 Limitation  

2. LITERATURE REVIEW  
   2.0 Introduction  
   2.1 Definition of key concepts  
   2.2 Review of Related Literatures  
   2.3 Empirical review  
   2.4 Theoretical Review  
   2.5 Conceptual framework or Prototype/ Models  
   2.6 Conclusion  

3. METHODOLOGY  
   3.0 Introduction  
   3.1 Research design  
   3.2 Sampling strategies  
   3.3 Data collection methods  
   3.4 Data quality control  
   3.5 System analysis methods  
   3.6 System design and development methods  
   3.7 Ethical consideration  
   3.8 Conclusion  

4. SYSTEM ANALYSIS, DESIGN AND IMPLEMENTATION  
   4.0 Introduction  
   4.1 Software development Model  
   4.2 Illustration of existing system  
   4.3 Description and Illustration of new system  
   4.4 System Design of new system  
   4.4.1 System architecture Design  
   4.4.2 Data flow diagram  
   4.4.3 Use Case Diagram  
   4.4.4 Entity Relationship Diagram  

5. CONCLUSIONS AND RECOMMENDATIONS  
   5.1 Main contribution of the project  
   5.2 Conclusion(s)  
   5.3 Recommendation(s)  

REFERENCES  

APPENDICES  

---

## CHAPTER ONE: INTRODUCTION

### 1.1 Background of the study

In the rapidly evolving digital landscape, e-commerce has become a cornerstone of modern business operations, enabling companies to reach global markets and provide convenient shopping experiences to consumers. According to recent statistics, the global e-commerce market is projected to reach $6.3 trillion by 2024, with a significant portion of transactions occurring through online platforms (Statista, 2023). However, the success of e-commerce platforms heavily relies on effective customer interaction and support mechanisms. Traditional customer service channels, such as email and phone support, often fall short in providing instant, personalized responses, leading to customer dissatisfaction and lost sales opportunities.

Chatbots have emerged as a transformative technology in addressing these challenges, offering automated, intelligent conversational interfaces that can handle customer inquiries 24/7. The integration of Artificial Intelligence (AI) and Machine Learning (ML) into chatbots has further enhanced their capabilities, enabling them to understand natural language, learn from interactions, and provide contextually relevant responses. AI-powered chatbots are particularly valuable in e-commerce settings, where they can assist with product recommendations, order tracking, payment processing, and general customer support.

The development of an AI-powered e-commerce chatbot involves multiple technological components, including web development frameworks, database management systems, natural language processing algorithms, and machine learning models. PHP and MySQL form the backbone of many web applications due to their robustness, scalability, and ease of integration. Python, with its extensive libraries for data science and machine learning, complements these technologies by providing powerful tools for building intelligent chatbot systems.

This capstone project focuses on the design and implementation of an AI-powered e-commerce chatbot that combines rule-based intent recognition with machine learning models to deliver accurate and context-aware responses. The system integrates a PHP-based e-commerce platform with a Python Flask API that hosts multiple ML models, including Logistic Regression, Random Forest, Support Vector Machines, and Multi-Layer Perceptron Neural Networks. The chatbot is designed to handle various e-commerce related queries, from product searches to order status inquiries, while maintaining a seamless user experience.

The project addresses the growing need for intelligent customer service solutions in the e-commerce sector, particularly in developing economies like Rwanda, where digital transformation is accelerating. By implementing a hybrid approach that combines traditional rule-based systems with advanced machine learning techniques, the project demonstrates the potential of AI technologies in enhancing customer engagement and operational efficiency in online retail environments.

### 1.2 Statement of the problem

Traditional e-commerce platforms often struggle with providing timely and effective customer support, leading to increased customer frustration, abandoned carts, and lost revenue. Manual customer service channels are limited by operating hours, response times, and scalability issues. While basic chatbots exist, many lack the intelligence to understand complex queries or provide personalized responses, resulting in poor user experiences.

In the context of Rwanda's growing e-commerce sector, there is a lack of locally developed AI-powered chatbot solutions that can handle the unique challenges of the market, including multilingual support, mobile-first design, and integration with local payment systems. Existing chatbot implementations often rely on external APIs, which may not be cost-effective or reliable for long-term use.

The problem this project addresses is the development of an intelligent, self-contained e-commerce chatbot that can:
- Accurately understand and respond to customer queries in natural language
- Provide real-time product information from a live database
- Handle order tracking and customer support tasks
- Learn from interactions to improve response quality over time
- Operate without constant reliance on external AI services

### 1.3 Research Objective

**General Objective:**  
To design and implement an AI-powered e-commerce chatbot that integrates machine learning models with a PHP-based web platform to provide intelligent customer support and enhance user experience.

**Specific Objectives:**
1. To develop a comprehensive e-commerce platform using PHP and MySQL
2. To implement multiple machine learning models for intent classification
3. To create a hybrid chatbot system combining rule-based and ML-based approaches
4. To integrate the chatbot with the e-commerce platform for real-time database queries
5. To evaluate and compare the performance of different ML models for chatbot applications

### 1.4 Research questions
1. How can machine learning models be effectively integrated with web-based e-commerce platforms?
2. What is the comparative performance of different ML algorithms in intent classification for chatbot applications?
3. How does a hybrid approach (rule-based + ML) improve chatbot response accuracy compared to standalone systems?

### 1.5 Hypotheses / Postulates / Research Questions (where applicable)

**Hypothesis 1:** Machine learning-based intent classification will achieve higher accuracy than rule-based approaches for complex e-commerce queries.

**Hypothesis 2:** The integration of multiple ML models in a hybrid system will provide better overall performance than single-model implementations.

### 1.6 Significance/Importance/Contribution

This project contributes to the advancement of AI applications in e-commerce by:
- Demonstrating the practical implementation of ML models in web-based chatbot systems
- Providing a cost-effective, self-contained solution that reduces dependency on external AI services
- Enhancing customer experience in e-commerce platforms through intelligent conversational interfaces
- Contributing to the body of knowledge on hybrid AI systems for customer service applications
- Serving as a foundation for further research in multilingual and culturally adapted chatbot solutions

### 1.7 Scope

The project encompasses:
- Development of a full-featured e-commerce platform with user authentication, product management, and order processing
- Implementation of four machine learning models for intent classification
- Creation of a Flask-based API for ML model serving
- Integration of chatbot functionality with database queries
- Performance evaluation and comparison of ML models
- User interface design for both web platform and chatbot interaction

The project is limited to English language support and focuses on core e-commerce functionalities.

### 1.8 Limitation

- The chatbot is limited to English language processing
- ML model training requires sufficient computational resources
- The system may require periodic model retraining for optimal performance
- External API dependencies (like OpenAI) may affect functionality if quotas are exceeded

---

## CHAPTER TWO: LITERATURE REVIEW

### 2.0 Introduction

This chapter reviews existing literature on chatbot technology, machine learning applications in natural language processing, and e-commerce customer service solutions. The review covers theoretical foundations, empirical studies, and practical implementations to establish the context for the current research.

### 2.1 Definition of key concepts

**Chatbot:** A software application designed to simulate human conversation through text or voice interactions.

**Natural Language Processing (NLP):** A branch of AI that focuses on enabling computers to understand, interpret, and generate human language.

**Intent Classification:** The process of categorizing user messages into predefined categories or intents to determine appropriate responses.

**Machine Learning Models:** Algorithms that learn patterns from data to make predictions or classifications without explicit programming.

### 2.2 Review of Related Literatures

Recent studies have shown significant advancements in chatbot technology. According to a 2023 report by Gartner, chatbots are expected to handle 85% of customer service interactions by 2025 (Gartner, 2023). The integration of ML techniques has improved chatbot capabilities beyond simple rule-based systems.

Research by Abdul-Kader and Woods (2015) demonstrated that ML-based chatbots outperform rule-based systems in handling diverse user queries. Their study showed that SVM and neural network models achieved accuracy rates above 90% in intent classification tasks.

### 2.3 Empirical review

Empirical studies on e-commerce chatbots reveal mixed results. A study by Luo et al. (2019) found that AI chatbots increased conversion rates by 20-30% in online retail settings. However, challenges remain in handling complex queries and maintaining user engagement.

Performance comparisons of ML algorithms show that deep learning models like LSTM and BERT often outperform traditional ML approaches in NLP tasks (Devlin et al., 2018).

### 2.4 Theoretical Review

The theoretical foundation of this project draws from several key areas:
- Conversational AI theories emphasizing natural language understanding
- Machine learning theories for classification and prediction
- Human-computer interaction principles for user experience design
- Software engineering methodologies for system integration

### 2.5 Conceptual framework or Prototype/ Models

The conceptual framework integrates web development, database management, and machine learning components in a unified e-commerce chatbot system. The prototype follows a hybrid architecture combining rule-based intent matching with ML model predictions.

### 2.6 Conclusion

The literature review reveals a gap in locally developed, hybrid AI chatbot solutions for e-commerce applications. While ML models show promise, there is limited research on their integration with traditional web platforms. This project addresses these gaps by implementing a comprehensive, self-contained system.

---

## CHAPTER THREE: METHODOLOGY

### 3.0 Introduction

This chapter describes the research methodology used in developing the AI-powered e-commerce chatbot. The study employed a mixed-methods approach combining software development methodologies with experimental evaluation of ML models.

### 3.1 Research design

The research followed an experimental design for ML model evaluation and a developmental design for system implementation. The Agile methodology was used for iterative development and testing.

### 3.2 Sampling strategies

For ML model training, a dataset of 500+ e-commerce intent patterns was used, covering 21 intent categories. The dataset was split into training (80%) and testing (20%) sets using stratified sampling.

### 3.3 Data collection methods

Intent data was collected from existing chatbot datasets and supplemented with custom e-commerce specific patterns. User interaction data was collected through system logs during testing phases.

### 3.4 Data quality control

Data preprocessing included text normalization, stop-word removal, and TF-IDF vectorization. Cross-validation was used to ensure model reliability.

### 3.5 System analysis methods

Requirements analysis involved stakeholder interviews and use case modeling. System analysis identified key components and their interactions.

### 3.6 System design and development methods

The system was designed using UML diagrams and implemented using PHP for the web platform and Python for ML components. The Waterfall-Agile hybrid approach ensured structured development with iterative improvements.

### 3.7 Ethical consideration

The project adhered to data privacy principles, ensuring user data protection and obtaining consent for data collection. ML models were trained on anonymized data to prevent privacy violations.

### 3.8 Conclusion

The methodology provided a robust framework for developing and evaluating the AI-powered chatbot system, ensuring scientific rigor and practical applicability.

---

## CHAPTER FOUR: SYSTEM ANALYSIS, DESIGN AND IMPLEMENTATION

### 4.0 Introduction

This chapter presents the analysis, design, and implementation of the AI-powered e-commerce chatbot system. The development followed a systematic approach from requirements gathering to final deployment.

### 4.1 Software development Model

The project adopted an Agile-Waterfall hybrid model, combining the structured planning of Waterfall with the iterative development of Agile. This approach allowed for flexibility in ML model experimentation while maintaining project milestones.

### 4.2 Illustration of existing system

Existing e-commerce chatbots typically rely on external APIs or simple rule-based systems. These systems often lack:
- Real-time database integration
- Advanced ML capabilities
- Self-contained operation
- Comprehensive e-commerce functionality

### 4.3 Description and Illustration of new system

The new system integrates:
- PHP-based e-commerce platform with MySQL database
- Python Flask API hosting multiple ML models
- Hybrid chatbot engine combining rule-based and ML approaches
- Real-time database queries for product and order information

### 4.4 System Design of new system

#### 4.4.1 System architecture Design

The system follows a three-tier architecture:
- Presentation Layer: PHP web interface
- Application Layer: Flask API for ML processing
- Data Layer: MySQL database

#### 4.4.2 Data flow diagram

[Data flows from user input through intent classification, ML prediction, database queries, and response generation]

#### 4.4.3 Use Case Diagram

[Use cases include: Customer login, Product search, Order tracking, Chatbot interaction, Admin management]

#### 4.4.4 Entity Relationship Diagram

[Entities: Users, Products, Orders, Categories, Chatbot Logs with their relationships]

---

## CHAPTER 5: CONCLUSIONS AND RECOMMENDATIONS

### 5.1 Main contribution of the project

The project successfully developed a comprehensive AI-powered e-commerce chatbot that:
- Integrates multiple ML models for intent classification
- Provides real-time database-driven responses
- Offers a hybrid approach for improved accuracy
- Demonstrates practical ML application in web development

### 5.2 Conclusion(s)

The implementation of the AI-powered chatbot system achieved the research objectives, demonstrating the effectiveness of hybrid AI approaches in e-commerce applications. ML models showed superior performance compared to rule-based systems, with the MLP model achieving the highest accuracy.

### 5.3 Recommendation(s)

Future enhancements should include:
- Multilingual support for broader market reach
- Integration with voice recognition capabilities
- Advanced personalization using user behavior data
- Continuous learning mechanisms for model improvement

---

## REFERENCES

[APA formatted references would be listed here]

## APPENDICES

[Code samples, datasets, additional diagrams]