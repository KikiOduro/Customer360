-- Customer360 schema hardening migration
-- Target: MySQL 8.0+
-- Date: 2026-04-01
--
-- What this migration does:
-- 1. Brings `jobs` closer to the live application model
-- 2. Improves data integrity with stricter types and checks
-- 3. Adds normalized tables for job artifacts and job events
-- 4. Backfills existing file-path data into `job_artifacts`
-- 5. Adds indexes that match real query patterns

USE customer360;

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- Remove accidental / non-domain table if it exists.
-- Keep this first so the rest of the schema reflects the actual product model.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS items;

-- ---------------------------------------------------------------------------
-- Harden the jobs table in place while preserving compatibility with the app.
-- ---------------------------------------------------------------------------
ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS local_upload_path VARCHAR(500) NULL AFTER upload_path,
    ADD COLUMN IF NOT EXISTS storage_provider VARCHAR(50) NULL AFTER local_upload_path,
    ADD COLUMN IF NOT EXISTS storage_bucket VARCHAR(255) NULL AFTER storage_provider,
    ADD COLUMN IF NOT EXISTS storage_object_path VARCHAR(500) NULL AFTER storage_bucket,
    ADD COLUMN IF NOT EXISTS storage_public_url VARCHAR(1000) NULL AFTER storage_object_path,
    MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending',
    MODIFY COLUMN clustering_method VARCHAR(50) NOT NULL DEFAULT 'kmeans',
    MODIFY COLUMN total_revenue DECIMAL(18,2) NULL,
    MODIFY COLUMN silhouette_score DECIMAL(6,4) NULL,
    MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN include_comparison TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY COLUMN is_saved TINYINT(1) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------------
-- Constraints for controlled enums / domain values.
-- ---------------------------------------------------------------------------
ALTER TABLE jobs
    ADD CONSTRAINT chk_jobs_status
        CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
    ADD CONSTRAINT chk_jobs_clustering_method
        CHECK (clustering_method IN ('kmeans', 'gmm', 'hierarchical')),
    ADD CONSTRAINT chk_jobs_counts_nonnegative
        CHECK (
            (num_customers IS NULL OR num_customers >= 0) AND
            (num_transactions IS NULL OR num_transactions >= 0) AND
            (num_clusters IS NULL OR num_clusters >= 0)
        ),
    ADD CONSTRAINT chk_jobs_scores_valid
        CHECK (
            silhouette_score IS NULL OR
            (silhouette_score >= -1.0000 AND silhouette_score <= 1.0000)
        ),
    ADD CONSTRAINT chk_jobs_revenue_nonnegative
        CHECK (total_revenue IS NULL OR total_revenue >= 0.00);

-- ---------------------------------------------------------------------------
-- Indexes that better match application access patterns.
-- App commonly fetches jobs by user ordered by created_at desc.
-- ---------------------------------------------------------------------------
CREATE INDEX idx_jobs_user_created_at ON jobs (user_id, created_at DESC);
CREATE INDEX idx_jobs_user_status_created_at ON jobs (user_id, status, created_at DESC);
CREATE INDEX idx_jobs_storage_object_path ON jobs (storage_object_path);

-- ---------------------------------------------------------------------------
-- Normalize file storage concerns.
-- One job can own multiple artifacts: source upload, results JSON, PDF, CSV, etc.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_artifacts (
    id BIGINT NOT NULL AUTO_INCREMENT,
    job_id INT NOT NULL,
    artifact_type VARCHAR(50) NOT NULL,
    storage_provider VARCHAR(50) NULL,
    storage_bucket VARCHAR(255) NULL,
    object_path VARCHAR(1000) NULL,
    public_url VARCHAR(1000) NULL,
    local_path VARCHAR(1000) NULL,
    mime_type VARCHAR(255) NULL,
    file_size_bytes BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_job_artifacts_job
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT chk_job_artifacts_type
        CHECK (
            artifact_type IN (
                'source_upload',
                'output_directory',
                'results_json',
                'report_pdf',
                'segmented_customers_csv',
                'other'
            )
        ),
    INDEX idx_job_artifacts_job_id (job_id),
    INDEX idx_job_artifacts_type (artifact_type),
    INDEX idx_job_artifacts_provider_bucket (storage_provider, storage_bucket),
    INDEX idx_job_artifacts_object_path (object_path(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Normalize state transitions / audit trail.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_events (
    id BIGINT NOT NULL AUTO_INCREMENT,
    job_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_job_events_job
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT chk_job_events_type
        CHECK (
            event_type IN (
                'created',
                'queued',
                'processing_started',
                'processing_completed',
                'processing_failed',
                'artifact_registered',
                'deleted',
                'saved',
                'other'
            )
        ),
    INDEX idx_job_events_job_created_at (job_id, created_at DESC),
    INDEX idx_job_events_type_created_at (event_type, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Backfill existing path-based data into normalized artifacts.
-- This is intentionally conservative and only inserts when a value exists.
-- ---------------------------------------------------------------------------
INSERT INTO job_artifacts (
    job_id,
    artifact_type,
    storage_provider,
    object_path,
    local_path,
    created_at
)
SELECT
    j.id,
    'source_upload',
    CASE
        WHEN j.storage_provider IS NOT NULL AND j.storage_provider <> '' THEN j.storage_provider
        WHEN j.upload_path LIKE 'users/%' THEN 'supabase'
        ELSE 'local'
    END,
    CASE
        WHEN j.storage_object_path IS NOT NULL AND j.storage_object_path <> '' THEN j.storage_object_path
        WHEN j.upload_path LIKE 'users/%' THEN j.upload_path
        ELSE NULL
    END,
    COALESCE(j.local_upload_path, CASE WHEN j.upload_path LIKE '/%' THEN j.upload_path ELSE NULL END),
    j.created_at
FROM jobs j
WHERE j.upload_path IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM job_artifacts a
      WHERE a.job_id = j.id
        AND a.artifact_type = 'source_upload'
  );

INSERT INTO job_artifacts (
    job_id,
    artifact_type,
    storage_provider,
    local_path,
    created_at
)
SELECT
    j.id,
    'output_directory',
    'local',
    j.output_path,
    j.created_at
FROM jobs j
WHERE j.output_path IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM job_artifacts a
      WHERE a.job_id = j.id
        AND a.artifact_type = 'output_directory'
  );

-- ---------------------------------------------------------------------------
-- Seed a basic audit trail from the current state of each job.
-- ---------------------------------------------------------------------------
INSERT INTO job_events (job_id, event_type, event_payload, created_at)
SELECT
    j.id,
    'created',
    JSON_OBJECT(
        'status', j.status,
        'original_filename', j.original_filename,
        'clustering_method', j.clustering_method
    ),
    j.created_at
FROM jobs j
WHERE NOT EXISTS (
    SELECT 1
    FROM job_events e
    WHERE e.job_id = j.id
      AND e.event_type = 'created'
);

INSERT INTO job_events (job_id, event_type, event_payload, created_at)
SELECT
    j.id,
    CASE
        WHEN j.status = 'completed' THEN 'processing_completed'
        WHEN j.status = 'failed' THEN 'processing_failed'
        WHEN j.status = 'processing' THEN 'processing_started'
        ELSE 'queued'
    END,
    JSON_OBJECT(
        'status', j.status,
        'error_message', j.error_message
    ),
    COALESCE(j.completed_at, j.created_at)
FROM jobs j
WHERE NOT EXISTS (
    SELECT 1
    FROM job_events e
    WHERE e.job_id = j.id
      AND e.event_type IN ('queued', 'processing_started', 'processing_completed', 'processing_failed')
);

COMMIT;

-- Optional follow-up after the app is migrated fully to `job_artifacts`:
-- 1. Stop writing direct file paths into jobs.upload_path / jobs.output_path
-- 2. Rename `jobs.upload_path` to `primary_upload_object_path` or remove it
-- 3. Replace `column_mapping TEXT` with `column_mapping JSON`
