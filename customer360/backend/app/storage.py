"""
Supabase Storage helpers for uploaded source files.
"""
import re
from pathlib import Path
from typing import Dict
from urllib.parse import quote

import httpx

from .config import (
    SUPABASE_SERVICE_ROLE_KEY,
    SUPABASE_STORAGE_BUCKET,
    SUPABASE_STORAGE_OBJECT_BASE,
    SUPABASE_STORAGE_PUBLIC_BASE,
    SUPABASE_URL,
)


def supabase_storage_enabled() -> bool:
    return bool(SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY and SUPABASE_STORAGE_BUCKET)


def sanitize_storage_filename(filename: str) -> str:
    name = Path(filename).name or "upload.csv"
    sanitized = re.sub(r"[^A-Za-z0-9._-]+", "_", name).strip("._")
    return sanitized or "upload.csv"


def build_storage_object_path(user_id: int, job_id: str, filename: str) -> str:
    safe_filename = sanitize_storage_filename(filename)
    return f"users/{user_id}/jobs/{job_id}/{safe_filename}"


def upload_file_to_supabase(local_path: Path, object_path: str, content_type: str = "text/csv") -> Dict[str, str]:
    if not supabase_storage_enabled():
        raise RuntimeError("Supabase storage is not configured")

    upload_url = f"{SUPABASE_STORAGE_OBJECT_BASE}/{quote(object_path, safe='/')}"

    with local_path.open("rb") as file_handle:
        response = httpx.post(
            upload_url,
            content=file_handle.read(),
            headers={
                "Authorization": f"Bearer {SUPABASE_SERVICE_ROLE_KEY}",
                "apikey": SUPABASE_SERVICE_ROLE_KEY,
                "Content-Type": content_type or "application/octet-stream",
                "x-upsert": "true",
            },
            timeout=60.0,
        )

    response.raise_for_status()

    return {
        "storage_provider": "supabase",
        "storage_bucket": SUPABASE_STORAGE_BUCKET,
        "storage_object_path": object_path,
        "storage_public_url": f"{SUPABASE_STORAGE_PUBLIC_BASE}/{quote(object_path, safe='/')}",
    }


def delete_supabase_object(object_path: str) -> None:
    if not supabase_storage_enabled() or not object_path:
        return

    delete_url = f"{SUPABASE_STORAGE_OBJECT_BASE}/{quote(object_path, safe='/')}"
    response = httpx.delete(
        delete_url,
        headers={
            "Authorization": f"Bearer {SUPABASE_SERVICE_ROLE_KEY}",
            "apikey": SUPABASE_SERVICE_ROLE_KEY,
        },
        timeout=30.0,
    )

    if response.status_code not in (200, 204, 404):
        response.raise_for_status()
