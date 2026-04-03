"""
Job management API routes.
Handles file upload, job status, results, and report generation.
"""
import os
import json
import math
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
from ..analytics.preprocessing import build_mapping_validation_report, get_csv_preview
from ..analytics.pipeline import run_pipeline
from ..report import generate_report
router = APIRouter()


def update_job_progress(db: Session, job: Job, percent: int, stage: str, message: str) -> None:
    """Persist job progress telemetry for the processing page."""
    job.progress_percent = max(0, min(100, int(percent)))
    job.progress_stage = stage
    job.progress_message = message
    db.commit()


def sanitize_json_payload(value):
    """Recursively replace NaN/Infinity values with None so JSON responses remain valid."""
    if isinstance(value, dict):
        return {key: sanitize_json_payload(item) for key, item in value.items()}

    if isinstance(value, list):
        return [sanitize_json_payload(item) for item in value]

    if isinstance(value, float) and not math.isfinite(value):
        return None

    return value


def validate_file(file: UploadFile) -> None:
    """Validate uploaded file."""
    # Check extension
    ext = Path(file.filename).suffix.lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid file type. Allowed: {', '.join(ALLOWED_EXTENSIONS)}"
        )


def store_upload_locally(file: UploadFile, upload_dir: Path) -> dict:
    """
    Save the uploaded file locally for processing.
    """
    upload_dir.mkdir(parents=True, exist_ok=True)

    local_path = upload_dir / Path(file.filename).name
    with open(local_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    return {
        "local_path": str(local_path),
        "storage_warning": None,
        "storage_provider": None,
        "storage_bucket": None,
        "storage_object_path": None,
        "storage_public_url": None,
    }


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

    engine_kwargs = {"pool_pre_ping": True}
    if db_url.lower().startswith("sqlite"):
        engine_kwargs["connect_args"] = {"check_same_thread": False}

    # Create a new database session for background task
    engine = create_engine(db_url, **engine_kwargs)
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    db = SessionLocal()
    
    try:
        # Get the job
        job = db.query(Job).filter(Job.job_id == job_id).first()
        if not job:
            return

        if job.status == "cancelled":
            return
        
        # Update status to processing
        job.status = "processing"
        update_job_progress(
            db=db,
            job=job,
            percent=8,
            stage="starting",
            message="Backend worker started. Initializing the segmentation pipeline."
        )

        def progress_callback(percent: int, stage: str, message: str) -> None:
            tracked_job = db.query(Job).filter(Job.job_id == job_id).first()
            if not tracked_job or tracked_job.status == "cancelled":
                return
            tracked_job.status = "processing"
            update_job_progress(db, tracked_job, percent, stage, message)
        
        results = run_pipeline(
            file_path=file_path,
            output_dir=output_dir,
            job_id=job_id,
            column_mapping=column_mapping,
            clustering_method=clustering_method,
            include_comparison=include_comparison,
            progress_callback=progress_callback,
        )

        # Respect cancellation if it happened while the job was running.
        db.refresh(job)
        if job.status == "cancelled":
            return
        
        # Update job with results
        job.status = "completed"
        job.completed_at = datetime.utcnow()
        meta = results.get('meta', {})
        job.num_customers = meta.get('num_customers', 0)
        job.num_transactions = meta.get('num_transactions', 0)
        job.total_revenue = meta.get('total_revenue', 0)
        job.num_clusters = meta.get('num_clusters', 0)
        job.silhouette_score = meta.get('silhouette_score', 0)
        job.output_path = output_dir
        job.progress_percent = 100
        job.progress_stage = "completed"
        job.progress_message = "Analysis completed successfully. Opening results is now available."

        pdf_path = Path(output_dir) / f"{job_id}_report.pdf"
        generate_report(
            results=results,
            output_path=str(pdf_path),
            company_name=job.owner.company_name if job.owner and job.owner.company_name else ""
        )
        
        db.commit()
        
    except Exception as e:
        # Update job with error
        job = db.query(Job).filter(Job.job_id == job_id).first()
        if job and job.status != "cancelled":
            job.status = "failed"
            job.error_message = str(e)
            job.progress_percent = 100
            job.progress_stage = "failed"
            job.progress_message = "Analysis failed. Please inspect the error message and retry with corrected data."
            db.commit()
    finally:
        db.close()


@router.post("/upload", response_model=JobStatus)
async def upload_file(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    clustering_method: str = Form(default="kmeans"),
    include_comparison: bool = Form(default=True),
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
    
    stored_upload = store_upload_locally(
        file=file,
        upload_dir=job_upload_dir,
    )
    
    # Create job record
    job = Job(
        job_id=job_id,
        user_id=current_user.id,
        original_filename=file.filename,
        upload_path=stored_upload["storage_object_path"] or stored_upload["local_path"],
        local_upload_path=stored_upload["local_path"],
        storage_provider=stored_upload["storage_provider"],
        storage_bucket=stored_upload["storage_bucket"],
        storage_object_path=stored_upload["storage_object_path"],
        storage_public_url=stored_upload["storage_public_url"],
        output_path=str(job_output_dir),
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        status="pending",
        progress_percent=5,
        progress_stage="queued",
        progress_message="Upload received. Your analysis job is queued and waiting to start."
    )

    db.add(job)
    db.commit()
    db.refresh(job)

    # Start background processing
    from ..config import DATABASE_URL
    background_tasks.add_task(
        run_segmentation_job,
        job_id=job_id,
        file_path=stored_upload["local_path"],
        output_dir=str(job_output_dir),
        column_mapping=None,
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        db_url=DATABASE_URL
    )
    
    return JobStatus(
        job_id=job.job_id,
        status=job.status,
        progress_percent=job.progress_percent,
        progress_stage=job.progress_stage,
        progress_message=job.progress_message,
        created_at=job.created_at,
        storage_provider=job.storage_provider,
        storage_bucket=job.storage_bucket,
        storage_object_path=job.storage_object_path,
        storage_public_url=job.storage_public_url,
        storage_warning=stored_upload.get("storage_warning"),
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
        
        has_direct_amount = bool(suggested.get('amount'))
        has_derived_amount = bool(suggested.get('quantity') and suggested.get('unit_price'))

        if suggested.get('customer_id') and suggested.get('invoice_date') and suggested.get('invoice_id') and (has_direct_amount or has_derived_amount):
            suggested_mapping = ColumnMapping(
                customer_id=suggested['customer_id'],
                invoice_date=suggested['invoice_date'],
                invoice_id=suggested['invoice_id'],
                amount=suggested.get('amount'),
                quantity=suggested.get('quantity'),
                unit_price=suggested.get('unit_price'),
                product=suggested.get('product'),
                category=suggested.get('category')
            )
        
        return CSVPreview(
            columns=preview['columns'],
            sample_rows=preview['sample_rows'],
            suggested_mapping=suggested_mapping,
            column_profiles=preview.get('column_profiles'),
            total_rows=preview.get('total_rows'),
            raw_rows=preview.get('raw_rows'),
            removed_blank_rows=preview.get('removed_blank_rows'),
        )
        
    finally:
        # Clean up temp file
        if temp_path.exists():
            os.remove(temp_path)


@router.post("/upload/with-mapping", response_model=JobStatus)
async def upload_with_mapping(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    customer_id_col: Optional[str] = Form(default=None),
    invoice_date_col: Optional[str] = Form(default=None),
    invoice_id_col: str = Form(...),
    amount_col: str = Form(default=""),
    quantity_col: Optional[str] = Form(default=None),
    unit_price_col: Optional[str] = Form(default=None),
    product_col: Optional[str] = Form(default=None),
    category_col: Optional[str] = Form(default=None),
    clustering_method: str = Form(default="kmeans"),
    include_comparison: bool = Form(default=True),
    amount_source_mode: str = Form(default="direct"),
    invoice_date_format: str = Form(default=""),
    dayfirst: bool = Form(default=False),
    decimal_separator: str = Form(default="."),
    thousands_separator: str = Form(default=","),
    currency_symbol: str = Form(default=""),
    negative_amount_policy: str = Form(default="exclude"),
    allow_synthetic_customer_id: bool = Form(default=False),
    allow_synthetic_invoice_date: bool = Form(default=False),
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
        'amount': amount_col or None,
        'quantity': quantity_col,
        'unit_price': unit_price_col,
        'amount_source_mode': amount_source_mode,
        'invoice_date_format': invoice_date_format,
        'dayfirst': dayfirst,
        'decimal_separator': decimal_separator,
        'thousands_separator': thousands_separator,
        'currency_symbol': currency_symbol,
        'negative_amount_policy': negative_amount_policy,
        'allow_synthetic_customer_id': allow_synthetic_customer_id,
        'allow_synthetic_invoice_date': allow_synthetic_invoice_date,
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
    
    stored_upload = store_upload_locally(
        file=file,
        upload_dir=job_upload_dir,
    )
    
    # Create job
    job = Job(
        job_id=job_id,
        user_id=current_user.id,
        original_filename=file.filename,
        upload_path=stored_upload["storage_object_path"] or stored_upload["local_path"],
        local_upload_path=stored_upload["local_path"],
        storage_provider=stored_upload["storage_provider"],
        storage_bucket=stored_upload["storage_bucket"],
        storage_object_path=stored_upload["storage_object_path"],
        storage_public_url=stored_upload["storage_public_url"],
        output_path=str(job_output_dir),
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        column_mapping=json.dumps(column_mapping),
        status="pending",
        progress_percent=5,
        progress_stage="queued",
        progress_message="Mapped upload received. Your analysis job is queued and waiting to start."
    )
    
    db.add(job)
    db.commit()
    db.refresh(job)
    
    # Start background processing
    from ..config import DATABASE_URL
    background_tasks.add_task(
        run_segmentation_job,
        job_id=job_id,
        file_path=stored_upload["local_path"],
        output_dir=str(job_output_dir),
        column_mapping=column_mapping,
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        db_url=DATABASE_URL
    )
    
    return JobStatus(
        job_id=job.job_id,
        status=job.status,
        progress_percent=job.progress_percent,
        progress_stage=job.progress_stage,
        progress_message=job.progress_message,
        created_at=job.created_at,
        storage_provider=job.storage_provider,
        storage_bucket=job.storage_bucket,
        storage_object_path=job.storage_object_path,
        storage_public_url=job.storage_public_url,
    )


@router.post("/upload/validate-mapping")
async def validate_upload_mapping(
    file: UploadFile = File(...),
    customer_id_col: Optional[str] = Form(default=None),
    invoice_date_col: Optional[str] = Form(default=None),
    invoice_id_col: Optional[str] = Form(default=None),
    amount_col: Optional[str] = Form(default=None),
    quantity_col: Optional[str] = Form(default=None),
    unit_price_col: Optional[str] = Form(default=None),
    product_col: Optional[str] = Form(default=None),
    category_col: Optional[str] = Form(default=None),
    amount_source_mode: str = Form(default="direct"),
    invoice_date_format: str = Form(default=""),
    dayfirst: bool = Form(default=False),
    decimal_separator: str = Form(default="."),
    thousands_separator: str = Form(default=","),
    currency_symbol: str = Form(default=""),
    negative_amount_policy: str = Form(default="exclude"),
    allow_synthetic_customer_id: bool = Form(default=False),
    allow_synthetic_invoice_date: bool = Form(default=False),
    current_user: User = Depends(get_current_user),
):
    """
    Validate a mapping and parsing configuration without creating an analysis job.
    """
    _ = current_user
    validate_file(file)

    temp_path = UPLOAD_DIR / f"validate_{uuid.uuid4()}_{Path(file.filename).name}"
    temp_path.parent.mkdir(parents=True, exist_ok=True)

    column_mapping = {
        "customer_id": customer_id_col,
        "invoice_date": invoice_date_col,
        "invoice_id": invoice_id_col,
        "amount": amount_col,
        "quantity": quantity_col,
        "unit_price": unit_price_col,
        "product": product_col,
        "category": category_col,
        "amount_source_mode": amount_source_mode,
        "invoice_date_format": invoice_date_format,
        "dayfirst": dayfirst,
        "decimal_separator": decimal_separator,
        "thousands_separator": thousands_separator,
        "currency_symbol": currency_symbol,
        "negative_amount_policy": negative_amount_policy,
        "allow_synthetic_customer_id": allow_synthetic_customer_id,
        "allow_synthetic_invoice_date": allow_synthetic_invoice_date,
    }

    try:
        with open(temp_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
        return build_mapping_validation_report(str(temp_path), column_mapping)
    except Exception as exc:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(exc)) from exc
    finally:
        if temp_path.exists():
            os.remove(temp_path)


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
            progress_percent=job.progress_percent,
            progress_stage=job.progress_stage,
            progress_message=job.progress_message,
            num_customers=job.num_customers,
            num_transactions=job.num_transactions,
            total_revenue=job.total_revenue,
            created_at=job.created_at,
            is_saved=job.is_saved,
            storage_provider=job.storage_provider,
            storage_bucket=job.storage_bucket,
            storage_object_path=job.storage_object_path,
            storage_public_url=job.storage_public_url,
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
        progress_percent=job.progress_percent,
        progress_stage=job.progress_stage,
        progress_message=job.progress_message,
        error_message=job.error_message,
        created_at=job.created_at,
        completed_at=job.completed_at,
        storage_provider=job.storage_provider,
        storage_bucket=job.storage_bucket,
        storage_object_path=job.storage_object_path,
        storage_public_url=job.storage_public_url,
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
    
    return sanitize_json_payload(results)


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


@router.post("/{job_id}/cancel")
async def cancel_job(
    job_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Cancel a job that has not completed yet.
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

    if job.status == "completed":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Completed jobs cannot be cancelled"
        )

    if job.status == "failed":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Failed jobs cannot be cancelled"
        )

    if job.status == "cancelled":
        return {"message": "Job already cancelled", "job_id": job.job_id, "status": job.status}

    job.status = "cancelled"
    job.error_message = "Cancelled by user"
    job.progress_percent = 100
    job.progress_stage = "cancelled"
    job.progress_message = "This analysis job was cancelled by the user."
    job.completed_at = datetime.utcnow()
    db.commit()

    return {"message": "Job cancelled successfully", "job_id": job.job_id, "status": job.status}
