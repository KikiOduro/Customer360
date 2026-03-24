# Component Testing - Copy-Paste Terminal Commands

## Navigation
```bash
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend
```

---

## Run All 16 API Component Tests
```bash
pytest tests/test_api.py -v
```

---

## Run All 51 Tests (Unit + Component)
```bash
pytest tests/test_rfm.py tests/test_clustering.py tests/test_preprocessing.py tests/test_api.py -v
```

---

## Run Tests by Category

### RFM Tests (13 tests)
```bash
pytest tests/test_rfm.py -v
```

### Clustering Tests (13 tests)
```bash
pytest tests/test_clustering.py -v
```

### Preprocessing Tests (9 tests)
```bash
pytest tests/test_preprocessing.py -v
```

### API Tests (16 tests)
```bash
pytest tests/test_api.py -v
```

---

## Run Specific Test Classes

### Authentication Tests
```bash
pytest tests/test_api.py::TestAuthEndpoints -v
```

### Job Management Tests
```bash
pytest tests/test_api.py::TestJobEndpoints -v
```

### Health Endpoint Tests
```bash
pytest tests/test_api.py::TestHealthEndpoint -v
```

---

## Run Individual Tests

### Test: User Registration Success
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_register_user_success -v
```

### Test: Duplicate Email Prevention
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_register_user_duplicate_email -v
```

### Test: Invalid Email Format
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_register_user_invalid_email -v
```

### Test: Short Password Validation
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_register_user_short_password -v
```

### Test: Successful Login
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_login_success -v
```

### Test: Wrong Password Rejection
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_login_wrong_password -v
```

### Test: Non-existent User
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_login_nonexistent_user -v
```

### Test: Get Current User
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_get_current_user -v
```

### Test: No Token Provided
```bash
pytest tests/test_api.py::TestAuthEndpoints::test_get_current_user_no_token -v
```

### Test: List Empty Jobs
```bash
pytest tests/test_api.py::TestJobEndpoints::test_list_jobs_empty -v
```

### Test: Unauthorized List Jobs
```bash
pytest tests/test_api.py::TestJobEndpoints::test_list_jobs_unauthorized -v
```

### Test: Upload Without Auth
```bash
pytest tests/test_api.py::TestJobEndpoints::test_upload_file_no_auth -v
```

### Test: Invalid File Type
```bash
pytest tests/test_api.py::TestJobEndpoints::test_upload_invalid_file_type -v
```

### Test: Job Status Not Found
```bash
pytest tests/test_api.py::TestJobEndpoints::test_get_job_status_not_found -v
```

### Test: Health Check
```bash
pytest tests/test_api.py::TestHealthEndpoint::test_health_check -v
```

### Test: Root Endpoint
```bash
pytest tests/test_api.py::TestHealthEndpoint::test_root_endpoint -v
```

---

## Advanced Options

### Run with Coverage Report
```bash
pytest tests/test_api.py --cov=app --cov-report=html
```

### Run with Short Traceback
```bash
pytest tests/test_api.py -v --tb=short
```

### Run with Long Traceback
```bash
pytest tests/test_api.py -v --tb=long
```

### Run and Stop on First Failure
```bash
pytest tests/test_api.py -x -v
```

### Run Tests Matching Pattern
```bash
pytest tests/test_api.py -k "register" -v
pytest tests/test_api.py -k "login" -v
pytest tests/test_api.py -k "auth" -v
```

### Run in Parallel (Speed Up)
```bash
pip install pytest-xdist
pytest tests/test_api.py -n auto -v
```

---

## Manual Testing - Start FastAPI Server
```bash
uvicorn app.main:app --reload
```

---

## Manual Testing - API Endpoints with curl

### Register User
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","company_name":"Test Co","password":"SecurePass123!"}'
```

### Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'
```

### Health Check
```bash
curl http://localhost:8000/health
```

---

## Expected Results

### All Tests Pass
```
============================== 51 passed in 6.72s ==============================
```

### API Tests Only Pass
```
===================== 16 passed in 44.75s =====================
```

### Single Test Pass
```
============================== 1 passed in 0.25s ==============================
```

---

**Notes:**
- Make sure you're in the backend directory before running commands
- Install dependencies first: `pip install -r requirements.txt`
- All tests should pass (100% success rate)
- See COMPONENT_TESTING_COMMANDS.md for detailed explanations
