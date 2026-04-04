"""
Configuration settings for Customer360 application.
"""
import os
from pathlib import Path
from dotenv import load_dotenv

# Base directories
BASE_DIR = Path(__file__).resolve().parent.parent
DATA_DIR = BASE_DIR / "data"
UPLOAD_DIR = DATA_DIR / "uploads"
OUTPUT_DIR = DATA_DIR / "outputs"
MODELS_DIR = BASE_DIR.parent / "models"

# Load backend environment file if present
load_dotenv(BASE_DIR / ".env")


def _parse_bool(value: str | None, default: bool = False) -> bool:
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def _parse_csv(value: str | None) -> list[str]:
    if not value:
        return []
    return [item.strip() for item in value.split(",") if item.strip()]

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
DEBUG = _parse_bool(os.getenv("DEBUG"), default=False)
ENVIRONMENT = os.getenv("ENVIRONMENT", "development").strip().lower() or "development"
ALLOWED_ORIGINS = _parse_csv(os.getenv("ALLOWED_ORIGINS"))
ALLOW_CREDENTIALS = _parse_bool(os.getenv("ALLOW_CREDENTIALS"), default=True)

# File upload settings
MAX_FILE_SIZE_MB = 50
ALLOWED_EXTENSIONS = {".csv"}

# Supabase Storage settings for uploaded source files
SUPABASE_URL = os.getenv("SUPABASE_URL", "https://bsacwgdxmnuifvhfasof.supabase.co").rstrip("/")
SUPABASE_STORAGE_BUCKET = os.getenv("SUPABASE_STORAGE_BUCKET", "user_uploads")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY", "")
SUPABASE_STORAGE_PUBLIC_BASE = os.getenv(
    "SUPABASE_STORAGE_PUBLIC_BASE",
    f"{SUPABASE_URL}/storage/v1/object/public/{SUPABASE_STORAGE_BUCKET}"
).rstrip("/")
SUPABASE_STORAGE_OBJECT_BASE = f"{SUPABASE_URL}/storage/v1/object/{SUPABASE_STORAGE_BUCKET}"

# AI integration
GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile")
GROQ_ENABLED = _parse_bool(os.getenv("GROQ_ENABLED"), default=bool(GROQ_API_KEY))
GROQ_REQUESTS_PER_MINUTE = int(os.getenv("GROQ_REQUESTS_PER_MINUTE", "20"))
GROQ_DAILY_REQUEST_LIMIT = int(os.getenv("GROQ_DAILY_REQUEST_LIMIT", "800"))
GROQ_USER_COOLDOWN_SECONDS = int(os.getenv("GROQ_USER_COOLDOWN_SECONDS", "60"))
GROQ_USER_DAILY_LIMIT = int(os.getenv("GROQ_USER_DAILY_LIMIT", "10"))
GROQ_MAX_INPUT_CHARS = int(os.getenv("GROQ_MAX_INPUT_CHARS", "12000"))
GROQ_MAX_OUTPUT_TOKENS = int(os.getenv("GROQ_MAX_OUTPUT_TOKENS", "2000"))
GROQ_RETRY_ATTEMPTS = int(os.getenv("GROQ_RETRY_ATTEMPTS", "3"))
GROQ_TIMEOUT_SECONDS = float(os.getenv("GROQ_TIMEOUT_SECONDS", "90"))

# Clustering settings
DEFAULT_K_RANGE = (2, 8)  # Range of k values to try for K-Means (reduced from 10 for speed)
RANDOM_STATE = 42

# Pipeline performance settings (tuneable via environment variables)
MAX_ROWS = int(os.getenv("MAX_ROWS", "100000"))  # Reject files above this row count
PIPELINE_TIMEOUT_SECONDS = int(os.getenv("PIPELINE_TIMEOUT_SECONDS", "600"))  # 10 min default
OPTIMAL_K_SUBSAMPLE = int(os.getenv("OPTIMAL_K_SUBSAMPLE", "5000"))  # Subsample for k-sweep
SHAP_MAX_SAMPLES = int(os.getenv("SHAP_MAX_SAMPLES", "2000"))  # Subsample for SHAP
CHART_DPI = int(os.getenv("CHART_DPI", "96"))  # Lower DPI = faster chart generation
