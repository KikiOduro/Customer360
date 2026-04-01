"""
Database connection and session management for Customer360.
Supports both MySQL and SQLite with automatic fallback.
"""
import os
import logging
from sqlalchemy import create_engine, event, inspect, text
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import QueuePool, StaticPool

from .config import DATABASE_URL

logger = logging.getLogger(__name__)

# Attempt to use DATABASE_URL; fall back to SQLite if MySQL fails
database_url = DATABASE_URL
is_sqlite = False

# Check if we're trying to use MySQL but it's not available
if "mysql" in database_url.lower():
    try:
        # Try to create a test connection
        from sqlalchemy import text
        test_engine = create_engine(database_url, pool_pre_ping=True, connect_args={"timeout": 2})
        with test_engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        logger.info("✅ MySQL connection successful")
        engine = create_engine(
            database_url,
            poolclass=QueuePool,
            pool_size=10,
            max_overflow=20,
            pool_pre_ping=True,
            pool_recycle=3600,
            echo=False
        )
    except Exception as e:
        logger.warning(f"⚠️  MySQL connection failed: {str(e)}")
        logger.warning("Falling back to SQLite for development/testing...")
        # Fall back to SQLite
        sqlite_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), "customer360.db")
        database_url = f"sqlite:///{sqlite_path}"
        is_sqlite = True
        engine = create_engine(
            database_url,
            connect_args={"check_same_thread": False},
            poolclass=StaticPool,
            echo=False
        )
else:
    # SQLite configuration
    is_sqlite = True
    engine = create_engine(
        database_url,
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
        echo=False
    )

# Log which database is being used
logger.info(f"Using database: {'SQLite (development mode)' if is_sqlite else 'MySQL (production)'}")

# Session factory
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Base class for models
Base = declarative_base()


def get_db():
    """
    Dependency that provides a database session.
    Ensures proper cleanup after request completion.
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def init_db():
    """
    Initialize database tables.
    Call this on application startup.
    """
    try:
        from . import models  # noqa: F401
        Base.metadata.create_all(bind=engine)
        ensure_job_storage_columns()
        logger.info("✅ Database tables initialized successfully")
    except Exception as e:
        logger.error(f"❌ Failed to initialize database: {str(e)}")
        raise


def ensure_job_storage_columns():
    """
    Add storage-tracking columns to existing jobs tables when needed.
    """
    inspector = inspect(engine)
    if "jobs" not in inspector.get_table_names():
        return

    existing_columns = {column["name"] for column in inspector.get_columns("jobs")}
    if "sqlite" in str(engine.url).lower():
        column_types = {
            "local_upload_path": "TEXT",
            "storage_provider": "TEXT",
            "storage_bucket": "TEXT",
            "storage_object_path": "TEXT",
            "storage_public_url": "TEXT",
        }
    else:
        column_types = {
            "local_upload_path": "VARCHAR(500)",
            "storage_provider": "VARCHAR(50)",
            "storage_bucket": "VARCHAR(255)",
            "storage_object_path": "VARCHAR(500)",
            "storage_public_url": "VARCHAR(1000)",
        }

    with engine.begin() as connection:
        for column_name, column_type in column_types.items():
            if column_name not in existing_columns:
                connection.execute(text(f"ALTER TABLE jobs ADD COLUMN {column_name} {column_type}"))
