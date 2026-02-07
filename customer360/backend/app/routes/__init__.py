"""
API Routes for Customer360.
Main router aggregating all endpoint modules.
"""
from fastapi import APIRouter

from .auth import router as auth_router
from .jobs import router as jobs_router

# Create main API router
api_router = APIRouter()

# Include sub-routers
api_router.include_router(auth_router, prefix="/auth", tags=["Authentication"])
api_router.include_router(jobs_router, prefix="/jobs", tags=["Jobs"])
