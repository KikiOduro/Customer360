"""
Customer360 - FastAPI Application Entry Point
A web system for Ghanaian SMEs to analyze customer transaction data
and receive customer segments with actionable insights.
"""
import logging
from contextlib import asynccontextmanager
import json
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from pathlib import Path
import joblib

from .database import init_db
from .routes import api_router
from .config import ALLOWED_ORIGINS, ALLOW_CREDENTIALS, DEBUG, ENVIRONMENT, MODELS_DIR

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Application lifespan handler.
    Initializes database on startup.
    """
    logger.info("Starting Customer360 application...")
    init_db()
    logger.info("Database initialized successfully")
    assets = {
        "scaler": None,
        "segment_map": {},
        "cluster_rfm_profile_path": None,
        "models_dir": str(MODELS_DIR),
    }

    scaler_path = MODELS_DIR / "scaler.pkl"
    segment_map_path = MODELS_DIR / "segment_map.json"
    cluster_profile_path = MODELS_DIR / "cluster_rfm_profile.csv"

    if scaler_path.exists():
        try:
            assets["scaler"] = joblib.load(scaler_path)
            logger.info("Loaded scaler artifact from %s", scaler_path)
        except Exception as exc:
            logger.warning("Failed to load scaler artifact: %s", exc)

    if segment_map_path.exists():
        try:
            with open(segment_map_path, "r") as handle:
                assets["segment_map"] = json.load(handle)
            logger.info("Loaded segment map artifact from %s", segment_map_path)
        except Exception as exc:
            logger.warning("Failed to load segment map artifact: %s", exc)

    if cluster_profile_path.exists():
        assets["cluster_rfm_profile_path"] = str(cluster_profile_path)
        logger.info("Detected cluster profile artifact at %s", cluster_profile_path)

    app.state.analysis_assets = assets
    yield
    logger.info("Shutting down Customer360 application...")


# Create FastAPI application
app = FastAPI(
    title="Customer360 API",
    description="""
    ## Customer Segmentation System for Ghanaian SMEs
    
    Customer360 helps small and medium enterprises analyze their customer transaction data
    to discover valuable customer segments and receive actionable marketing recommendations.
    
    ### Features:
    - 📊 **RFM Analysis**: Compute Recency, Frequency, and Monetary metrics
    - 🎯 **Smart Segmentation**: K-Means, GMM, and Hierarchical clustering
    - 📈 **Actionable Insights**: Plain-English segment labels with marketing recommendations
    - 📄 **PDF Reports**: Professional downloadable reports
    
    ### Getting Started:
    1. Register an account using `/api/auth/register`
    2. Login to get your access token via `/api/auth/login`
    3. Upload your CSV file using `/api/jobs/upload`
    4. Check job status and retrieve results
    """,
    version="1.0.0",
    lifespan=lifespan
)

# Configure CORS
cors_origins = ALLOWED_ORIGINS or (["*"] if DEBUG else [])

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=ALLOW_CREDENTIALS if cors_origins and cors_origins != ["*"] else False,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include API routes
app.include_router(api_router, prefix="/api")


# Health check endpoint
@app.get("/health")
async def health_check():
    """
    Health check endpoint for monitoring.
    """
    return {
        "status": "healthy",
        "service": "Customer360 API",
        "version": "1.0.0",
        "environment": ENVIRONMENT,
    }


@app.get("/")
async def root():
    """
    Root endpoint with API information.
    """
    return {
        "message": "Welcome to Customer360 API",
        "docs": "/docs",
        "health": "/health",
        "api": "/api"
    }


# Optional: Mount static files for frontend
frontend_path = Path(__file__).parent.parent.parent / "frontend"
if frontend_path.exists():
    app.mount("/static", StaticFiles(directory=str(frontend_path)), name="static")
