"""
Job management API routes.
Handles file upload, job status, results, and report generation.
"""
import os
import json
import uuid
import shutil
from datetime import datetime
from pathlib import Path
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File, Form, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from sqlalchemy.orm import Session

from ..database import get_db
from ..models import User, Job
from ..schemas import (
    JobCreate, JobStatus, JobSummary, JobResults, CSVPreview,
    ColumnMapping, ClusterSummary, RFMDistribution
)
from ..auth import get_current_user
from ..config import UPLOAD_DIR, OUTPUT_DIR, MAX_FILE_SIZE_MB, ALLOWED_EXTENSIONS
from ..analytics.preprocessing import get_csv_preview
from ..analytics.pipeline import run_pipeline
from ..report import generate_report

router = APIRouter()


def validate_file(file: UploadFile) -> None:
    """Validate uploaded file."""
    # Check extension
    ext = Path(file.filename).suffix.lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid file type. Allowed: {', '.join(ALLOWED_EXTENSIONS)}"
        )


def run_segmentation_job(
    job_id: str,
    file_path: str,
    output_dir: str,
    column_mapping: Optional[dict],
    clustering_method: str,
    include_comparison: bool,
    db_url: str
):
    """
    Background task to run the segmentation pipeline.
    """
    from sqlalchemy import create_engine
    from sqlalchemy.orm import sessionmaker
    
    # Create a new database session for background task
    engine = create_engine(db_url, connect_args={"check_same_thread": False})
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    db = SessionLocal()
    
    try:
        # Get the job
        job = db.query(Job).filter(Job.job_id == job_id).first()
        if not job:
            return
        
        # Update status to processing
        job.status = "processing"
        db.commit()
        
        # Run the pipeline
        results = run_pipeline(
            file_path=file_path,
            output_dir=output_dir,
            job_id=job_id,
            column_mapping=column_mapping,
            clustering_method=clustering_method,
            include_comparison=include_comparison
        )
        
        # Update job with results
        meta = results.get('meta', {})
        job.status = "completed"
        job.completed_at = datetime.utcnow()
        job.num_customers = meta.get('num_customers')
        job.num_transactions = meta.get('num_transactions')
        job.total_revenue = meta.get('total_revenue')
        job.num_clusters = meta.get('num_clusters')
        job.silhouette_score = meta.get('silhouette_score')
        job.output_path = output_dir
        
        db.commit()
        
    except Exception as e:
        # Update job with error
        job = db.query(Job).filter(Job.job_id == job_id).first()
        if job:
            job.status = "failed"
            job.error_message = str(e)
            db.commit()
    finally:
        db.close()


@router.post("/upload", response_model=JobStatus)
async def upload_file(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    clustering_method: str = Form(default="kmeans"),
    include_comparison: bool = Form(default=False),
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Upload a CSV file and start segmentation analysis.
    
    - **file**: CSV file with transaction data
    - **clustering_method**: Algorithm to use (kmeans, gmm, hierarchical)
    - **include_comparison**: Run all methods for comparison
    """
    # Validate file
    validate_file(file)
    
    # Generate job ID
    job_id = str(uuid.uuid4())
    
    # Create upload and output directories for this job
    job_upload_dir = UPLOAD_DIR / job_id
    job_output_dir = OUTPUT_DIR / job_id
    job_upload_dir.mkdir(parents=True, exist_ok=True)
    job_output_dir.mkdir(parents=True, exist_ok=True)
    
    # Save uploaded file
    file_path = job_upload_dir / file.filename
    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)
    
    # Create job record
    job = Job(
        job_id=job_id,
        user_id=current_user.id,
        original_filename=file.filename,
        upload_path=str(file_path),
        output_path=str(job_output_dir),
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        status="pending"
    )
    
    db.add(job)
    db.commit()
    db.refresh(job)
    
    # Start background processing
    from ..config import DATABASE_URL
    background_tasks.add_task(
        run_segmentation_job,
        job_id=job_id,
        file_path=str(file_path),
        output_dir=str(job_output_dir),
        column_mapping=None,
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        db_url=DATABASE_URL
    )
    
    return JobStatus(
        job_id=job.job_id,
        status=job.status,
        created_at=job.created_at
    )


@router.post("/upload/preview", response_model=CSVPreview)
async def preview_upload(
    file: UploadFile = File(...),
    current_user: User = Depends(get_current_user)
):
    """
    Upload a CSV file and get a preview for column mapping.
    Returns column names, sample data, and suggested mapping.
    """
    validate_file(file)
    
    # Save to temporary location
    temp_path = UPLOAD_DIR / f"temp_{uuid.uuid4()}_{file.filename}"
    temp_path.parent.mkdir(parents=True, exist_ok=True)
    
    try:
        with open(temp_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
        
        # Get preview
        preview = get_csv_preview(str(temp_path))
        
        # Build suggested mapping
        suggested = preview.get('suggested_mapping', {})
        suggested_mapping = None
        
        if all(suggested.get(k) for k in ['customer_id', 'invoice_date', 'invoice_id', 'amount']):
            suggested_mapping = ColumnMapping(
                customer_id=suggested['customer_id'],
                invoice_date=suggested['invoice_date'],
                invoice_id=suggested['invoice_id'],
                amount=suggested['amount'],
                product=suggested.get('product'),
                category=suggested.get('category')
            )
        
        return CSVPreview(
            columns=preview['columns'],
            sample_rows=preview['sample_rows'],
            suggested_mapping=suggested_mapping
        )
        
    finally:
        # Clean up temp file
        if temp_path.exists():
            os.remove(temp_path)


@router.post("/upload/with-mapping", response_model=JobStatus)
async def upload_with_mapping(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    customer_id_col: str = Form(...),
    invoice_date_col: str = Form(...),
    invoice_id_col: str = Form(...),
    amount_col: str = Form(...),
    product_col: Optional[str] = Form(default=None),
    category_col: Optional[str] = Form(default=None),
    clustering_method: str = Form(default="kmeans"),
    include_comparison: bool = Form(default=False),
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Upload a CSV file with explicit column mapping.
    """
    validate_file(file)
    
    # Build column mapping
    column_mapping = {
        'customer_id': customer_id_col,
        'invoice_date': invoice_date_col,
        'invoice_id': invoice_id_col,
        'amount': amount_col
    }
    if product_col:
        column_mapping['product'] = product_col
    if category_col:
        column_mapping['category'] = category_col
    
    # Generate job ID
    job_id = str(uuid.uuid4())
    
    # Create directories
    job_upload_dir = UPLOAD_DIR / job_id
    job_output_dir = OUTPUT_DIR / job_id
    job_upload_dir.mkdir(parents=True, exist_ok=True)
    job_output_dir.mkdir(parents=True, exist_ok=True)
    
    # Save file
    file_path = job_upload_dir / file.filename
    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)
    
    # Create job
    job = Job(
        job_id=job_id,
        user_id=current_user.id,
        original_filename=file.filename,
        upload_path=str(file_path),
        output_path=str(job_output_dir),
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        column_mapping=json.dumps(column_mapping),
        status="pending"
    )
    
    db.add(job)
    db.commit()
    db.refresh(job)
    
    # Start background processing
    from ..config import DATABASE_URL
    background_tasks.add_task(
        run_segmentation_job,
        job_id=job_id,
        file_path=str(file_path),
        output_dir=str(job_output_dir),
        column_mapping=column_mapping,
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        db_url=DATABASE_URL
    )
    
    return JobStatus(
        job_id=job.job_id,
        status=job.status,
        created_at=job.created_at
    )


@router.get("/", response_model=List[JobSummary])
async def list_jobs(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    List all jobs for the current user.
    """
    jobs = db.query(Job).filter(Job.user_id == current_user.id).order_by(Job.created_at.desc()).all()
    
    return [
        JobSummary(
            job_id=job.job_id,
            original_filename=job.original_filename,
            status=job.status,
            num_customers=job.num_customers,
            num_transactions=job.num_transactions,
            total_revenue=job.total_revenue,
            created_at=job.created_at,
            is_saved=job.is_saved
        )
        for job in jobs
    ]


@router.get("/status/{job_id}", response_model=JobStatus)
async def get_job_status(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Get the status of a specific job.
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    return JobStatus(
        job_id=job.job_id,
        status=job.status,
        error_message=job.error_message,
        created_at=job.created_at,
        completed_at=job.completed_at
    )


@router.get("/results/{job_id}")
async def get_job_results(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Get full results for a completed job.
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    if job.status != "completed":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Job is not completed. Current status: {job.status}"
        )
    
    # Load results from file
    results_path = Path(job.output_path) / f"{job_id}_results.json"
    
    if not results_path.exists():
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Results file not found"
        )
    
    with open(results_path, 'r') as f:
        results = json.load(f)
    
    return results


@router.get("/report/{job_id}")
async def download_report(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Generate and download PDF report for a completed job.
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    if job.status != "completed":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Job is not completed. Current status: {job.status}"
        )
    
    # Load results
    results_path = Path(job.output_path) / f"{job_id}_results.json"
    
    if not results_path.exists():
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Results file not found"
        )
    
    with open(results_path, 'r') as f:
        results = json.load(f)
    
    # Generate PDF
    pdf_path = Path(job.output_path) / f"{job_id}_report.pdf"
    
    generate_report(
        results=results,
        output_path=str(pdf_path),
        company_name=current_user.company_name or ""
    )
    
    return FileResponse(
        path=str(pdf_path),
        filename=f"customer360_report_{job_id[:8]}.pdf",
        media_type="application/pdf"
    )


@router.get("/download/{job_id}/customers")
async def download_customers_csv(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Download customer segmentation CSV for a completed job.
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    if job.status != "completed":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Job is not completed. Current status: {job.status}"
        )
    
    csv_path = Path(job.output_path) / f"{job_id}_customers.csv"
    
    if not csv_path.exists():
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Customers file not found"
        )
    
    return FileResponse(
        path=str(csv_path),
        filename=f"customers_segmented_{job_id[:8]}.csv",
        media_type="text/csv"
    )


@router.post("/{job_id}/save")
async def save_job(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Mark a job as saved (prevent automatic deletion).
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    job.is_saved = True
    db.commit()
    
    return {"message": "Job saved successfully"}


@router.delete("/{job_id}")
async def delete_job(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Delete a job and its associated files.
    """
    job = db.query(Job).filter(
        Job.job_id == job_id,
        Job.user_id == current_user.id
    ).first()
    
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Job not found"
        )
    
    # Delete files
    upload_dir = UPLOAD_DIR / job_id
    output_dir = OUTPUT_DIR / job_id
    
    if upload_dir.exists():
        shutil.rmtree(upload_dir)
    if output_dir.exists():
        shutil.rmtree(output_dir)
    
    # Delete job record
    db.delete(job)
    db.commit()
    
    return {"message": "Job deleted successfully"}
