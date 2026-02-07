"""
Configuration settings for Customer360 application.
"""
import os
from pathlib import Path

# Base directories
BASE_DIR = Path(__file__).resolve().parent.parent
DATA_DIR = BASE_DIR / "data"
UPLOAD_DIR = DATA_DIR / "uploads"
OUTPUT_DIR = DATA_DIR / "outputs"
DB_DIR = DATA_DIR / "db"

# Create directories if they don't exist
for directory in [DATA_DIR, UPLOAD_DIR, OUTPUT_DIR, DB_DIR]:
    directory.mkdir(parents=True, exist_ok=True)

# Database
DATABASE_URL = f"sqlite:///{DB_DIR}/customer360.db"

# JWT Settings
SECRET_KEY = os.getenv("SECRET_KEY", "customer360-secret-key-change-in-production-2026")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 60 * 24  # 24 hours

# File upload settings
MAX_FILE_SIZE_MB = 50
ALLOWED_EXTENSIONS = {".csv"}

# Clustering settings
DEFAULT_K_RANGE = (2, 10)  # Range of k values to try for K-Means
RANDOM_STATE = 42
