<?php
/**
 * Upload API Endpoint
 * Proxies file upload from PHP frontend to FastAPI backend
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['success' => false, 'error' => 'No file provided'], 400);
}

$file      = $_FILES['file'];
$authToken = $_SESSION['auth_token'] ?? null;

// ── VALIDATE FILE ─────────────────────────────────────────────
$allowedExtensions = ['.csv', '.xlsx', '.xls'];
$ext = strtolower('.' . pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    jsonResponse(['success' => false, 'error' => 'Invalid file type. Please upload a CSV or Excel file.'], 400);
}

if ($file['size'] > 25 * 1024 * 1024) {
    jsonResponse(['success' => false, 'error' => 'File too large. Maximum size is 25MB.'], 400);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'File upload error. Please try again.'], 400);
}

if (!$authToken) {
    jsonResponse([
        'success' => false,
        'error' => 'Live uploads require an authenticated backend session. Please sign in again and retry.'
    ], 401);
}

// ── REAL MODE: forward to FastAPI ─────────────────────────────
$clustering_method  = $_POST['clustering_method']  ?? 'kmeans';
$include_comparison = $_POST['include_comparison']  ?? 'false';

// Build multipart body manually (curl is cleaner than file_get_contents for files)
if (function_exists('curl_init')) {
    $ch = curl_init(BACKEND_API_URL . '/jobs/upload');

    $postFields = [
        'file'               => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
        'clustering_method'  => $clustering_method,
        'include_comparison' => $include_comparison,
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $authToken,
            'Accept: application/json',
        ],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

} else {
    // Fallback: stream_context (no curl)
    $boundary = '----Boundary' . uniqid();
    $fileData = file_get_contents($file['tmp_name']);
    $filename = basename($file['name']);

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
    $body .= "Content-Type: {$file['type']}\r\n\r\n";
    $body .= $fileData . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"clustering_method\"\r\n\r\n";
    $body .= "{$clustering_method}\r\n";
    $body .= "--{$boundary}--\r\n";

    $context  = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            "Content-Type: multipart/form-data; boundary={$boundary}",
            "Authorization: Bearer {$authToken}",
            "Content-Length: " . strlen($body),
        ]),
        'content'       => $body,
        'timeout'       => 30,
        'ignore_errors' => true,
    ]]);

    $response = file_get_contents(BACKEND_API_URL . '/jobs/upload', false, $context);
    $httpCode = 0;
    $curlError = '';

    if ($response === false) {
        jsonResponse([
            'success' => false,
            'error'   => 'Could not reach the analysis server. Make sure the backend is running on ' . BACKEND_API_URL
        ], 503);
    }

    // Extract HTTP code from response headers
    foreach ($http_response_header as $header) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
            $httpCode = intval($matches[1]);
        }
    }
}

// ── HANDLE FASTAPI RESPONSE ───────────────────────────────────
if ($curlError) {
    jsonResponse([
        'success' => false,
        'error'   => 'Could not connect to the analysis server: ' . $curlError
    ], 503);
}

$decoded = json_decode($response, true);

if ($httpCode === 200 || $httpCode === 201) {
    // FastAPI returns {job_id, status, created_at}
    $jobId = $decoded['job_id'] ?? null;

    if (!$jobId) {
        jsonResponse(['success' => false, 'error' => 'Backend did not return a job ID.'], 500);
    }

    // Store in session so processing.php polling can use it
    $_SESSION['current_job'] = [
        'job_id'     => $jobId,
        'status'     => $decoded['status'] ?? 'pending',
        'created_at' => $decoded['created_at'] ?? date('c'),
        'filename'   => $file['name'],
        'storage_provider' => $decoded['storage_provider'] ?? null,
        'storage_bucket' => $decoded['storage_bucket'] ?? null,
        'storage_object_path' => $decoded['storage_object_path'] ?? null,
        'storage_public_url' => $decoded['storage_public_url'] ?? null,
    ];

    jsonResponse([
        'success' => true,
        'job_id'  => $jobId,
        'status'  => $decoded['status'] ?? 'pending',
        'storage_provider' => $decoded['storage_provider'] ?? null,
        'storage_bucket' => $decoded['storage_bucket'] ?? null,
        'storage_object_path' => $decoded['storage_object_path'] ?? null,
        'storage_public_url' => $decoded['storage_public_url'] ?? null,
    ]);

} else {
    $detail = $decoded['detail'] ?? $decoded['error'] ?? 'Upload failed (HTTP ' . $httpCode . ')';
    jsonResponse(['success' => false, 'error' => $detail], $httpCode ?: 500);
}

// ── HELPERS ───────────────────────────────────────────────────

/**
 * Get a basic preview of the uploaded file for column mapping
 */
function getFilePreview(string $filePath, string $ext): array {
    if ($ext === '.csv') {
        $handle = fopen($filePath, 'r');
        if (!$handle) return ['columns' => [], 'sample_rows' => []];

        $columns    = fgetcsv($handle) ?: [];
        $sampleRows = [];
        $i = 0;
        while (($row = fgetcsv($handle)) !== false && $i < 5) {
            $sampleRows[] = array_combine($columns, array_pad($row, count($columns), null));
            $i++;
        }
        fclose($handle);

        return [
            'columns'     => $columns,
            'sample_rows' => $sampleRows,
            'suggested_mapping' => suggestMapping($columns),
        ];
    }

    // For xlsx we just return column names via a simple approach
    return ['columns' => [], 'sample_rows' => [], 'suggested_mapping' => []];
}

/**
 * Quick client-side column mapping suggestion (mirrors preprocessing.py logic)
 */
function suggestMapping(array $columns): array {
    $colsLower = array_map('strtolower', $columns);
    $mapping   = [];

    $patterns = [
        'customer_id'  => ['customer_id','customerid','customer','cust_id','client_id','user_id','buyer_id','member_id'],
        'invoice_date' => ['invoice_date','invoicedate','date','transaction_date','order_date','purchase_date','txn_date'],
        'invoice_id'   => ['invoice_id','invoiceid','invoice_no','transaction_id','order_id','receipt_no','txn_id'],
        'amount'       => ['total line amount','total_line_amount','amount','total','revenue','sales','price','value','payment','sum'],
        'product'      => ['product_name','product name','productname','item_name','item name','sku','description'],
        'category'     => ['categor','product_type','department','item_type','class','group'],
    ];

    foreach ($patterns as $field => $keywords) {
        foreach ($keywords as $kw) {
            foreach ($colsLower as $i => $col) {
                if (str_contains($col, $kw) || str_contains($kw, $col)) {
                    $mapping[$field] = $columns[$i];
                    break 2;
                }
            }
        }
    }

    return $mapping;
}
