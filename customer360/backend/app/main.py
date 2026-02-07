"""
Customer360 - FastAPI Application Entry Point
A web system for Ghanaian SMEs to analyze customer transaction data
and receive customer segments with actionable insights.
"""
import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from pathlib import Path

from .database import init_db
from .routes import api_router

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
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify actual origins
    allow_credentials=True,
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
        "version": "1.0.0"
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
