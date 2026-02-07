<?php
/**
 * Upload API Endpoint
 * Handles file upload and column detection by proxying to Python backend
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'upload';

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    case 'preview':
        handlePreview();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Handle file upload - save locally and get column preview
 */
function handleUpload() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['file']['error'] ?? 'No file uploaded';
        jsonResponse(['error' => 'Upload failed: ' . getUploadError($error)], 400);
    }
    
    $file = $_FILES['file'];
    
    // Validate file type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        jsonResponse(['error' => 'Invalid file type. Allowed: CSV, XLSX, XLS'], 400);
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(['error' => 'File too large. Maximum: 25MB'], 400);
    }
    
    // Generate unique filename
    $uploadId = uniqid('upload_', true);
    $filename = $uploadId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $filepath = UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonResponse(['error' => 'Failed to save file'], 500);
    }
    
    // Store upload info in session
    $_SESSION['current_upload'] = [
        'id' => $uploadId,
        'filename' => $file['name'],
        'filepath' => $filepath,
        'size' => $file['size'],
        'uploaded_at' => date('Y-m-d H:i:s')
    ];
    
    // Try to get column preview from Python backend
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest('/jobs/upload/preview', 'POST', null, $token, ['file' => $filepath]);
        
        if ($result['success']) {
            $_SESSION['current_upload']['columns'] = $result['data']['columns'];
            $_SESSION['current_upload']['sample_rows'] = $result['data']['sample_rows'];
            $_SESSION['current_upload']['suggested_mapping'] = $result['data']['suggested_mapping'];
            
            jsonResponse([
                'success' => true,
                'upload_id' => $uploadId,
                'filename' => $file['name'],
                'columns' => $result['data']['columns'],
                'sample_rows' => $result['data']['sample_rows'],
                'suggested_mapping' => $result['data']['suggested_mapping'],
                'redirect' => 'column-mapping.php'
            ]);
        }
    }
    
    // Fallback: parse CSV locally for columns
    $columns = [];
    $sampleRows = [];
    
    if ($ext === 'csv') {
        $handle = fopen($filepath, 'r');
        if ($handle) {
            // Read header
            $header = fgetcsv($handle);
            if ($header) {
                $columns = array_map('trim', $header);
                
                // Read sample rows (up to 10)
                $count = 0;
                while (($row = fgetcsv($handle)) !== false && $count < 10) {
                    $sampleRow = [];
                    foreach ($columns as $i => $col) {
                        $sampleRow[$col] = $row[$i] ?? '';
                    }
                    $sampleRows[] = $sampleRow;
                    $count++;
                }
            }
            fclose($handle);
        }
    }
    
    $_SESSION['current_upload']['columns'] = $columns;
    $_SESSION['current_upload']['sample_rows'] = $sampleRows;
    
    // Suggest column mapping based on common names
    $suggestedMapping = suggestColumnMapping($columns);
    $_SESSION['current_upload']['suggested_mapping'] = $suggestedMapping;
    
    jsonResponse([
        'success' => true,
        'upload_id' => $uploadId,
        'filename' => $file['name'],
        'columns' => $columns,
        'sample_rows' => $sampleRows,
        'suggested_mapping' => $suggestedMapping,
        'redirect' => 'column-mapping.php'
    ]);
}

/**
 * Get preview of current upload
 */
function handlePreview() {
    if (!isset($_SESSION['current_upload'])) {
        jsonResponse(['error' => 'No upload in progress'], 404);
    }
    
    $upload = $_SESSION['current_upload'];
    
    jsonResponse([
        'success' => true,
        'upload_id' => $upload['id'],
        'filename' => $upload['filename'],
        'columns' => $upload['columns'] ?? [],
        'sample_rows' => $upload['sample_rows'] ?? [],
        'suggested_mapping' => $upload['suggested_mapping'] ?? null
    ]);
}

/**
 * Suggest column mapping based on common column names
 */
function suggestColumnMapping($columns) {
    $mapping = [
        'customer_id' => null,
        'invoice_date' => null,
        'invoice_id' => null,
        'amount' => null
    ];
    
    $patterns = [
        'customer_id' => ['customer_id', 'cust_id', 'customerid', 'customer', 'cust_ref', 'cust_ref_id', 'client_id'],
        'invoice_date' => ['date', 'invoice_date', 'transaction_date', 'order_date', 'purchase_date', 'trans_date'],
        'invoice_id' => ['invoice_id', 'inv_id', 'invoice', 'inv_num', 'order_id', 'transaction_id', 'trans_id'],
        'amount' => ['amount', 'total', 'total_amount', 'total_ghs', 'price', 'value', 'revenue', 'sum']
    ];
    
    foreach ($columns as $col) {
        $colLower = strtolower(str_replace([' ', '-', '_'], '', $col));
        
        foreach ($patterns as $field => $fieldPatterns) {
            if ($mapping[$field] !== null) continue;
            
            foreach ($fieldPatterns as $pattern) {
                $patternClean = str_replace([' ', '-', '_'], '', $pattern);
                if (strpos($colLower, $patternClean) !== false || $colLower === $patternClean) {
                    $mapping[$field] = $col;
                    break 2;
                }
            }
        }
    }
    
    return $mapping;
}

/**
 * Get human-readable upload error message
 */
function getUploadError($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    
    return $errors[$code] ?? 'Unknown error';
}
