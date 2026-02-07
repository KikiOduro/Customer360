"""
SQLAlchemy models for Customer360.
Defines User and Job tables.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, DateTime, ForeignKey, Boolean, Text, Float
from sqlalchemy.orm import relationship

from .database import Base


class User(Base):
    """
    User model for authentication and job ownership.
    """
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    email = Column(String(255), unique=True, index=True, nullable=False)
    company_name = Column(String(255), nullable=True)
    hashed_password = Column(String(255), nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    is_active = Column(Boolean, default=True)

    # Relationship to jobs
    jobs = relationship("Job", back_populates="owner", cascade="all, delete-orphan")


class Job(Base):
    """
    Job model for tracking segmentation analysis jobs.
    Each job represents one file upload and analysis run.
    """
    __tablename__ = "jobs"

    id = Column(Integer, primary_key=True, index=True)
    job_id = Column(String(36), unique=True, index=True, nullable=False)  # UUID
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    
    # Job status: pending, processing, completed, failed
    status = Column(String(50), default="pending")
    error_message = Column(Text, nullable=True)
    
    # File information
    original_filename = Column(String(255), nullable=False)
    upload_path = Column(String(500), nullable=False)
    output_path = Column(String(500), nullable=True)
    
    # Job configuration
    clustering_method = Column(String(50), default="kmeans")  # kmeans, gmm, hierarchical
    include_comparison = Column(Boolean, default=False)  # Run all methods for comparison
    
    # Column mapping (JSON stored as text)
    column_mapping = Column(Text, nullable=True)
    
    # Results summary
    num_customers = Column(Integer, nullable=True)
    num_transactions = Column(Integer, nullable=True)
    total_revenue = Column(Float, nullable=True)
    num_clusters = Column(Integer, nullable=True)
    silhouette_score = Column(Float, nullable=True)
    
    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow)
    completed_at = Column(DateTime, nullable=True)
    
    # Persistence flag
    is_saved = Column(Boolean, default=False)  # If False, delete after generating outputs
    
    # Relationship to user
    owner = relationship("User", back_populates="jobs")
