<?php
/**
 * Column Mapping API Endpoint
 * Saves column mappings and triggers analysis
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'save';

switch ($action) {
    case 'save':
        handleSaveMapping();
        break;
    case 'get':
        handleGetMapping();
        break;
    case 'columns':
        handleGetColumns();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Save column mapping and start analysis job
 */
function handleSaveMapping() {
    // Get mapping from POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        // Try form data
        $data = $_POST;
    }
    
    $mapping = $data['mapping'] ?? [];
    
    // Validate required fields
    $required = ['customer_id', 'date', 'invoice_id', 'amount'];
    foreach ($required as $field) {
        if (empty($mapping[$field])) {
            jsonResponse(['error' => "Missing required mapping: $field"], 400);
        }
    }
    
    // Check for upload
    if (!isset($_SESSION['current_upload'])) {
        jsonResponse(['error' => 'No file uploaded'], 400);
    }
    
    $upload = $_SESSION['current_upload'];
    
    // Store mapping in session
    $_SESSION['column_mapping'] = [
        'customer_id' => $mapping['customer_id'],
        'invoice_date' => $mapping['date'],
        'invoice_id' => $mapping['invoice_id'],
        'amount' => $mapping['amount'],
        'product' => $mapping['product'] ?? null,
        'category' => $mapping['category'] ?? null
    ];
    
    // Try to start job via Python backend
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token && file_exists($upload['filepath'])) {
        $formData = [
            'customer_id_col' => $mapping['customer_id'],
            'invoice_date_col' => $mapping['date'],
            'invoice_id_col' => $mapping['invoice_id'],
            'amount_col' => $mapping['amount'],
            'clustering_method' => $data['clustering_method'] ?? 'kmeans',
            'include_comparison' => $data['include_comparison'] ?? false
        ];
        
        if (!empty($mapping['product'])) {
            $formData['product_col'] = $mapping['product'];
        }
        if (!empty($mapping['category'])) {
            $formData['category_col'] = $mapping['category'];
        }
        
        $result = apiRequest('/jobs/upload/with-mapping', 'POST', $formData, $token, [
            'file' => $upload['filepath']
        ]);
        
        if ($result['success']) {
            $_SESSION['current_job'] = [
                'job_id' => $result['data']['job_id'],
                'status' => $result['data']['status'],
                'filename' => $upload['filename'],
                'created_at' => $result['data']['created_at']
            ];
            
            jsonResponse([
                'success' => true,
                'job_id' => $result['data']['job_id'],
                'status' => $result['data']['status'],
                'redirect' => 'processing.php'
            ]);
        }
    }
    
    // Fallback: create demo job
    $jobId = 'demo_' . uniqid();
    $_SESSION['current_job'] = [
        'job_id' => $jobId,
        'status' => 'pending',
        'filename' => $upload['filename'],
        'created_at' => date('c'),
        'demo_mode' => true
    ];
    
    // Store batch ID for processing page
    $_SESSION['batch_id'] = rand(1000, 9999);
    $_SESSION['uploaded_file'] = $upload['filename'];
    $_SESSION['record_count'] = count($upload['sample_rows'] ?? []) * 100; // Estimate
    
    jsonResponse([
        'success' => true,
        'job_id' => $jobId,
        'status' => 'pending',
        'redirect' => 'processing.php',
        'demo_mode' => true
    ]);
}

/**
 * Get current column mapping
 */
function handleGetMapping() {
    $mapping = $_SESSION['column_mapping'] ?? null;
    
    if ($mapping) {
        jsonResponse([
            'success' => true,
            'mapping' => $mapping
        ]);
    } else {
        jsonResponse(['error' => 'No mapping saved'], 404);
    }
}

/**
 * Get available columns from current upload
 */
function handleGetColumns() {
    if (!isset($_SESSION['current_upload'])) {
        jsonResponse(['error' => 'No file uploaded'], 404);
    }
    
    $upload = $_SESSION['current_upload'];
    
    jsonResponse([
        'success' => true,
        'filename' => $upload['filename'],
        'columns' => $upload['columns'] ?? [],
        'sample_rows' => $upload['sample_rows'] ?? [],
        'suggested_mapping' => $upload['suggested_mapping'] ?? null
    ]);
}
