"""
Unit tests for API endpoints.
"""
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool
import tempfile
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))

from app.main import app
from app.database import Base, get_db
from app.models import User, Job


# Create test database
SQLALCHEMY_DATABASE_URL = "sqlite://"

engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    """Override database dependency for testing."""
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()


# Override the dependency
app.dependency_overrides[get_db] = override_get_db


class TestAuthEndpoints:
    """Test cases for authentication endpoints."""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Set up test database before each test."""
        Base.metadata.create_all(bind=engine)
        yield
        Base.metadata.drop_all(bind=engine)
    
    @pytest.fixture
    def client(self):
        """Create test client."""
        return TestClient(app)
    
    def test_register_user_success(self, client):
        """Test successful user registration."""
        response = client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123",
                "company_name": "Test Company"
            }
        )
        
        assert response.status_code == 201
        data = response.json()
        assert data["email"] == "test@example.com"
        assert data["company_name"] == "Test Company"
        assert "id" in data
    
    def test_register_user_duplicate_email(self, client):
        """Test registration with duplicate email."""
        # Register first user
        client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        # Try to register with same email
        response = client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password456"
            }
        )
        
        assert response.status_code == 400
        assert "already registered" in response.json()["detail"].lower()
    
    def test_register_user_invalid_email(self, client):
        """Test registration with invalid email."""
        response = client.post(
            "/api/auth/register",
            json={
                "email": "invalid-email",
                "password": "password123"
            }
        )
        
        assert response.status_code == 422
    
    def test_register_user_short_password(self, client):
        """Test registration with too short password."""
        response = client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "12345"  # Too short
            }
        )
        
        assert response.status_code == 422
    
    def test_login_success(self, client):
        """Test successful login."""
        # Register user first
        client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        # Login
        response = client.post(
            "/api/auth/login/json",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        assert "access_token" in data
        assert data["token_type"] == "bearer"
    
    def test_login_wrong_password(self, client):
        """Test login with wrong password."""
        # Register user first
        client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        # Login with wrong password
        response = client.post(
            "/api/auth/login/json",
            json={
                "email": "test@example.com",
                "password": "wrongpassword"
            }
        )
        
        assert response.status_code == 401
    
    def test_login_nonexistent_user(self, client):
        """Test login with non-existent user."""
        response = client.post(
            "/api/auth/login/json",
            json={
                "email": "nonexistent@example.com",
                "password": "password123"
            }
        )
        
        assert response.status_code == 401
    
    def test_get_current_user(self, client):
        """Test getting current user info."""
        # Register and login
        client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123",
                "company_name": "Test Co"
            }
        )
        
        login_response = client.post(
            "/api/auth/login/json",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        token = login_response.json()["access_token"]
        
        # Get current user
        response = client.get(
            "/api/auth/me",
            headers={"Authorization": f"Bearer {token}"}
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["email"] == "test@example.com"
        assert data["company_name"] == "Test Co"
    
    def test_get_current_user_no_token(self, client):
        """Test getting current user without token."""
        response = client.get("/api/auth/me")
        
        assert response.status_code == 401


class TestJobEndpoints:
    """Test cases for job management endpoints."""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Set up test database before each test."""
        Base.metadata.create_all(bind=engine)
        yield
        Base.metadata.drop_all(bind=engine)
    
    @pytest.fixture
    def client(self):
        """Create test client."""
        return TestClient(app)
    
    @pytest.fixture
    def auth_headers(self, client):
        """Create authenticated user and return headers."""
        # Register
        client.post(
            "/api/auth/register",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        # Login
        login_response = client.post(
            "/api/auth/login/json",
            json={
                "email": "test@example.com",
                "password": "password123"
            }
        )
        
        token = login_response.json()["access_token"]
        return {"Authorization": f"Bearer {token}"}
    
    def test_list_jobs_empty(self, client, auth_headers):
        """Test listing jobs when none exist."""
        response = client.get("/api/jobs/", headers=auth_headers)
        
        assert response.status_code == 200
        assert response.json() == []
    
    def test_list_jobs_unauthorized(self, client):
        """Test listing jobs without authentication."""
        response = client.get("/api/jobs/")
        
        assert response.status_code == 401
    
    def test_upload_file_no_auth(self, client):
        """Test file upload without authentication."""
        with tempfile.NamedTemporaryFile(suffix=".csv", delete=False) as f:
            f.write(b"customer_id,invoice_date,invoice_id,amount\n")
            f.write(b"C001,2025-01-01,INV001,100\n")
            temp_path = f.name
        
        try:
            with open(temp_path, "rb") as f:
                response = client.post(
                    "/api/jobs/upload",
                    files={"file": ("test.csv", f, "text/csv")}
                )
            
            assert response.status_code == 401
        finally:
            os.unlink(temp_path)
    
    def test_upload_invalid_file_type(self, client, auth_headers):
        """Test uploading non-CSV file."""
        with tempfile.NamedTemporaryFile(suffix=".txt", delete=False) as f:
            f.write(b"This is not a CSV file")
            temp_path = f.name
        
        try:
            with open(temp_path, "rb") as f:
                response = client.post(
                    "/api/jobs/upload",
                    files={"file": ("test.txt", f, "text/plain")},
                    headers=auth_headers
                )
            
            assert response.status_code == 400
            assert "Invalid file type" in response.json()["detail"]
        finally:
            os.unlink(temp_path)
    
    def test_get_job_status_not_found(self, client, auth_headers):
        """Test getting status of non-existent job."""
        response = client.get(
            "/api/jobs/status/non-existent-job-id",
            headers=auth_headers
        )
        
        assert response.status_code == 404


class TestHealthEndpoint:
    """Test cases for health check endpoint."""
    
    @pytest.fixture
    def client(self):
        """Create test client."""
        return TestClient(app)
    
    def test_health_check(self, client):
        """Test health check endpoint."""
        response = client.get("/health")
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert "version" in data
    
    def test_root_endpoint(self, client):
        """Test root endpoint."""
        response = client.get("/")
        
        assert response.status_code == 200
        data = response.json()
        assert "message" in data
        assert "docs" in data


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
