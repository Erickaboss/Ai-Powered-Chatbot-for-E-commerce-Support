# Automated Testing Suite

## PHPUnit Tests (PHP Backend)

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run with code coverage
./vendor/bin/phpunit --coverage-html coverage

# Run specific test file
./vendor/bin/phpunit tests/ChatbotTest.php
```

## Pytest Tests (Python ML API)

```bash
# Navigate to ML directory
cd chatbot-ml

# Install dependencies
pip install -r requirements.txt
pip install pytest pytest-cov

# Run all tests
pytest

# Run with coverage
pytest --cov=. --cov-report=html

# Run specific test file
pytest tests/test_app.py
```

## Running Tests in Docker

```bash
# Build and run tests in containers
docker-compose -f docker-compose.test.yml up --abort-on-container-exit

# View test results
docker-compose logs php-tests
docker-compose logs ml-tests
```

## Test Coverage Requirements

- **Minimum Coverage**: 80%
- **Critical Paths**: 100% (authentication, payments, database operations)
- **API Endpoints**: All endpoints must have tests

## Continuous Integration

Tests automatically run on:
- Every push to `main` or `develop` branches
- All pull requests
- Scheduled nightly builds

Coverage reports are uploaded to Codecov for tracking.
