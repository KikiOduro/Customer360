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

# Create directories if they don't exist
for directory in [DATA_DIR, UPLOAD_DIR, OUTPUT_DIR]:
    directory.mkdir(parents=True, exist_ok=True)

# Database (MySQL)
# Format: mysql+pymysql://user:password@host:port/database
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_PORT = os.getenv("DB_PORT", "3306")
DB_USER = os.getenv("DB_USER", "root")
DB_PASSWORD = os.getenv("DB_PASSWORD", "")
DB_NAME = os.getenv("DB_NAME", "customer360")

DATABASE_URL = os.getenv(
    "DATABASE_URL",
    f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
)

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
