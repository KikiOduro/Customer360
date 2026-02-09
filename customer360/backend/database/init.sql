-- Customer360 Database Initialization Script for MySQL
-- Run this script to create the database and tables

-- Create database (run as root)
CREATE DATABASE IF NOT EXISTS customer360
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE customer360;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    company_name VARCHAR(255),
    hashed_password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs table
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(36) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    original_filename VARCHAR(255) NOT NULL,
    upload_path VARCHAR(500) NOT NULL,
    output_path VARCHAR(500),
    clustering_method VARCHAR(50) DEFAULT 'kmeans',
    include_comparison BOOLEAN DEFAULT FALSE,
    column_mapping TEXT,
    num_customers INT,
    num_transactions INT,
    total_revenue FLOAT,
    num_clusters INT,
    silhouette_score FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    is_saved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_job_id (job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Create a dedicated application user (recommended for production)
-- CREATE USER IF NOT EXISTS 'customer360_app'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON customer360.* TO 'customer360_app'@'localhost';
-- FLUSH PRIVILEGES;

-- Verify tables were created
SHOW TABLES;
DESCRIBE users;
DESCRIBE jobs;
