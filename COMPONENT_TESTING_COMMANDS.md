# Component Testing - Terminal Commands Reference

## Quick Overview

This document provides all terminal commands you need to execute component/API tests for the Customer360 project. All commands should be run from the backend directory: `/Users/akuaoduro/Desktop/Capstone/customer360/backend`

**Test Summary:**
- API Component Tests: **16 tests** ✅ ALL PASS
- Total Backend Tests (including unit tests): **51 tests** ✅ ALL PASS
- Execution Time: ~6.72 seconds

---

## Prerequisites

Before running tests, ensure:
1. You're in the backend directory
2. All dependencies are installed (see requirements.txt)

```bash
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend
pip install -r requirements.txt  # One-time setup
```

---

## Component/API Test Commands

### 1. Run All 16 API Tests

```bash
pytest tests/test_api.py -v
```

**Expected Output:**
```
tests/test_api.py::TestAuthEndpoints::test_register_user_success PASSED  [  6%]
tests/test_api.py::TestAuthEndpoints::test_register_user_duplicate_email PASSED [ 12%]
tests/test_api.py::TestAuthEndpoints::test_register_user_invalid_email PASSED [ 18%]
tests/test_api.py::TestAuthEndpoints::test_register_user_short_password PASSED [ 25%]
tests/test_api.py::TestAuthEndpoints::test_login_success PASSED          [ 31%]
tests/test_api.py::TestAuthEndpoints::test_login_wrong_password PASSED   [ 37%]
tests/test_api.py::TestAuthEndpoints::test_login_nonexistent_user PASSED [ 43%]
tests/test_api.py::TestAuthEndpoints::test_get_current_user PASSED       [ 50%]
tests/test_api.py::TestAuthEndpoints::test_get_current_user_no_token PASSED [ 56%]
tests/test_api.py::TestJobEndpoints::test_list_jobs_empty PASSED         [ 62%]
tests/test_api.py::TestJobEndpoints::test_list_jobs_unauthorized PASSED  [ 68%]
tests/test_api.py::TestJobEndpoints::test_upload_file_no_auth PASSED     [ 75%]
tests/test_api.py::TestJobEndpoints::test_upload_invalid_file_type PASSED [ 81%]
tests/test_api.py::TestJobEndpoints::test_get_job_status_not_found PASSED [ 87%]
tests/test_api.py::TestHealthEndpoint::test_health_check PASSED          [ 93%]
tests/test_api.py::TestHealthEndpoint::test_root_endpoint PASSED         [100%]

===================== 16 passed in 44.75s =====================
```

---

### 2. Run Specific Test Class (Authentication Tests Only)

```bash
pytest tests/test_api.py::TestAuthEndpoints -v
```

Tests:
- ✅ User registration validation
- ✅ Duplicate email prevention
- ✅ Password validation
- ✅ Login success/failure
- ✅ JWT token validation
- ✅ Current user retrieval

---

### 3. Run Specific Test Class (Job Endpoints)

```bash
pytest tests/test_api.py::TestJobEndpoints -v
```

Tests:
- ✅ List jobs (authorization required)
- ✅ Upload file validation
- ✅ Get job status
- ✅ Error handling for invalid files

---

### 4. Run Specific Test Class (Health Endpoints)

```bash
pytest tests/test_api.py::TestHealthEndpoint -v
```

Tests:
- ✅ Health check endpoint
- ✅ Root endpoint

---

### 5. Run Single Test Method

```bash
# Example: Test successful user registration
pytest tests/test_api.py::TestAuthEndpoints::test_register_user_success -v
```

---

### 6. Run API Tests with Short Traceback (Recommended for CI/CD)

```bash
pytest tests/test_api.py -v --tb=short
```

---

### 7. Run API Tests with Detailed Error Output

```bash
pytest tests/test_api.py -v --tb=long
```

---

## All Tests Combined (Unit + Component)

### Run ALL 51 Tests at Once

```bash
pytest tests/test_rfm.py tests/test_clustering.py tests/test_preprocessing.py tests/test_api.py -v
```

**Expected Output:**
```
===================== 51 passed in 6.72s =====================
```

---

## Advanced Testing Options

### Run With Coverage Report

```bash
pytest tests/test_api.py --cov=app --cov-report=html
```

This generates an HTML coverage report at: `htmlcov/index.html`

---

### Run Specific Tests by Pattern

```bash
# Run all tests with "register" in the name
pytest tests/test_api.py -k "register" -v

# Run all tests with "login" in the name
pytest tests/test_api.py -k "login" -v

# Run all tests with "auth" in the name
pytest tests/test_api.py -k "auth" -v
```

---

### Run Tests and Stop on First Failure

```bash
pytest tests/test_api.py -x -v
```

---

### Run Tests in Parallel (Speed Up Execution)

First install pytest-xdist:
```bash
pip install pytest-xdist
```

Then run:
```bash
pytest tests/test_api.py -n auto -v
```

---

## Manual API Testing (Using curl)

If you prefer to test API endpoints manually without pytest:

### 1. Start the FastAPI Server

```bash
# In a terminal, from backend directory
uvicorn app.main:app --reload
```

The server runs at: `http://localhost:8000`

### 2. Register a User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "company_name": "Test Company",
    "password": "SecurePass123!"
  }'
```

**Expected Response (201 Created):**
```json
{
  "id": "uuid-here",
  "email": "test@example.com",
  "company_name": "Test Company"
}
```

### 3. Login User

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
```

**Expected Response (200 OK):**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "bearer"
}
```

### 4. Get Current User (with JWT)

Replace `YOUR_JWT_TOKEN` with token from login response:

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 5. List Jobs

```bash
curl -X GET http://localhost:8000/api/jobs \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 6. Health Check

```bash
curl http://localhost:8000/health
```

---

## Troubleshooting

### Error: "ModuleNotFoundError: No module named 'fastapi'"

**Solution:** Install dependencies
```bash
pip install -r requirements.txt
```

---

### Error: "No such file or directory: tests/test_api.py"

**Solution:** Make sure you're in the correct directory
```bash
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend
```

---

### Tests Timeout

**Solution:** Run tests with increased timeout
```bash
pytest tests/test_api.py -v --timeout=60
```

---

### Port Already in Use (When Running Server)

If port 8000 is already in use:
```bash
uvicorn app.main:app --reload --port 8001
```

---

## Test Results Summary

| Category | Count | Status | Time |
|----------|-------|--------|------|
| RFM Feature Tests | 13 | ✅ PASS | 0.29s |
| Clustering Tests | 13 | ✅ PASS | 14.52s |
| Preprocessing Tests | 9 | ✅ PASS | 0.44s |
| API Component Tests | 16 | ✅ PASS | 44.75s |
| **TOTAL** | **51** | **✅ ALL PASS** | **6.72s** |

---

## CI/CD Integration

For automated testing in your CI/CD pipeline:

```bash
#!/bin/bash
cd customer360/backend
pytest tests/ -v --tb=short --cov=app --cov-report=xml
```

---

**Last Updated:** March 2026  
**Test Framework:** pytest 7.4.0  
**Python Version:** 3.11.7  
**Platform:** macOS
