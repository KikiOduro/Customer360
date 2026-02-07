"""
Pydantic schemas for request/response validation.
"""
from datetime import datetime
from typing import Optional, List, Dict, Any
from pydantic import BaseModel, EmailStr, Field


# ============== Auth Schemas ==============

class UserCreate(BaseModel):
    """Schema for user registration."""
    email: EmailStr
    password: str = Field(..., min_length=6)
    company_name: Optional[str] = None


class UserLogin(BaseModel):
    """Schema for user login."""
    email: EmailStr
    password: str


class UserResponse(BaseModel):
    """Schema for user response (excludes password)."""
    id: int
    email: str
    company_name: Optional[str]
    created_at: datetime

    class Config:
        from_attributes = True


class Token(BaseModel):
    """Schema for JWT token response."""
    access_token: str
    token_type: str = "bearer"


class TokenData(BaseModel):
    """Schema for token payload data."""
    user_id: Optional[int] = None
    email: Optional[str] = None


# ============== Job Schemas ==============

class ColumnMapping(BaseModel):
    """Schema for mapping CSV columns to required fields."""
    customer_id: str
    invoice_date: str
    invoice_id: str
    amount: str
    product: Optional[str] = None
    category: Optional[str] = None


class JobCreate(BaseModel):
    """Schema for creating a new job."""
    clustering_method: str = "kmeans"  # kmeans, gmm, hierarchical
    include_comparison: bool = False
    column_mapping: Optional[ColumnMapping] = None


class JobStatus(BaseModel):
    """Schema for job status response."""
    job_id: str
    status: str
    error_message: Optional[str] = None
    created_at: datetime
    completed_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class JobSummary(BaseModel):
    """Schema for job summary in list views."""
    job_id: str
    original_filename: str
    status: str
    num_customers: Optional[int]
    num_transactions: Optional[int]
    total_revenue: Optional[float]
    created_at: datetime
    is_saved: bool

    class Config:
        from_attributes = True


# ============== Results Schemas ==============

class ClusterSummary(BaseModel):
    """Summary statistics for a single cluster/segment."""
    cluster_id: int
    segment_label: str
    num_customers: int
    percentage: float
    avg_recency: float
    avg_frequency: float
    avg_monetary: float
    total_revenue: float
    recommended_actions: List[str]


class RFMDistribution(BaseModel):
    """RFM distribution data for visualization."""
    recency: Dict[str, Any]  # histogram data
    frequency: Dict[str, Any]
    monetary: Dict[str, Any]


class JobResults(BaseModel):
    """Full results for a completed job."""
    job_id: str
    status: str
    
    # Overview metrics
    num_customers: int
    num_transactions: int
    total_revenue: float
    date_range: Dict[str, str]
    
    # Clustering results
    clustering_method: str
    num_clusters: int
    silhouette_score: float
    
    # Segment details
    segments: List[ClusterSummary]
    
    # Distribution data for charts
    rfm_distributions: RFMDistribution
    cluster_sizes: Dict[str, int]
    
    # Comparison results (if include_comparison was True)
    comparison_results: Optional[Dict[str, Any]] = None


class CSVPreview(BaseModel):
    """Preview of uploaded CSV for column mapping."""
    columns: List[str]
    sample_rows: List[Dict[str, Any]]
    suggested_mapping: Optional[ColumnMapping] = None


# ============== Report Schemas ==============

class ReportRequest(BaseModel):
    """Request schema for generating a report."""
    include_charts: bool = True
    include_recommendations: bool = True
