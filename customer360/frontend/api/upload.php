<?php
/**
 * Upload API Endpoint
 * Proxies file upload from PHP frontend to FastAPI backend
 *
 * The preview action supports the finalized upload -> mapping -> processing flow.
 * The older direct upload path remains for compatibility but the UI uses preview.
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$action = $_GET['action'] ?? $_POST['action'] ?? 'upload';
$jobName = trim((string) ($_POST['job_name'] ?? $_POST['jobName'] ?? ''));

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// When the request body exceeds post_max_size, PHP discards the multipart payload
// before populating $_FILES/$_POST. Surface that real cause instead of "No file provided".
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
$postMaxSizeBytes = parseIniSize(ini_get('post_max_size'));
if ($contentLength > 0 && empty($_FILES) && empty($_POST) && $postMaxSizeBytes > 0 && $contentLength > $postMaxSizeBytes) {
    $limitMb = round($postMaxSizeBytes / (1024 * 1024), 1);
    jsonResponse([
        'success' => false,
        'error' => "File too large for the server upload limit ({$limitMb} MB). Increase PHP post_max_size/upload_max_filesize or choose a smaller file."
    ], 413);
}

if (!isset($_FILES['file']) && !isset($_FILES['csv_file'])) {
    jsonResponse(['success' => false, 'error' => 'No file provided'], 400);
}

$file      = $_FILES['file'] ?? $_FILES['csv_file'];
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

if ($action === 'preview') {
    // Preview mode stores a temporary uploaded CSV and asks FastAPI to profile it
    // before the user commits to running the full analysis.
    if ($ext !== '.csv') {
        jsonResponse([
            'success' => false,
            'error' => 'The preview and column-mapping flow currently supports CSV files only. Please upload a .csv export.'
        ], 400);
    }

    if (!$authToken) {
        jsonResponse([
            'success' => false,
            'error' => 'Preview requires an authenticated backend session. Please sign in again and retry.'
        ], 401);
    }

    $previewDir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'preview' . DIRECTORY_SEPARATOR;
    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0755, true);
    }

    $tempName = uniqid('preview_', true) . $ext;
    $tempPath = $previewDir . $tempName;

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        jsonResponse(['success' => false, 'error' => 'Could not prepare file preview.'], 500);
    }

    $preview = getFilePreview($tempPath, $ext);
    $backendPreview = apiRequest('/jobs/upload/preview', 'POST', null, $authToken, [
        'file' => $tempPath
    ]);
    if ($backendPreview['success'] && is_array($backendPreview['data'])) {
        // Prefer backend-generated suggestions/profiles because they use the same
        // preprocessing code that will run during the real analysis.
        $preview = array_merge($preview, $backendPreview['data']);
    }

    // Session storage lets column-mapping.php access the temporary file securely
    // without sending server paths back into the page URL.
    $_SESSION['current_upload'] = [
        'job_name' => $jobName,
        'filename' => $file['name'],
        'filepath' => $tempPath,
        'columns' => $preview['columns'] ?? [],
        'sample_rows' => $preview['sample_rows'] ?? [],
        'suggested_mapping' => $preview['suggested_mapping'] ?? null,
        'column_profiles' => $preview['column_profiles'] ?? [],
        'total_rows' => $preview['total_rows'] ?? null,
        'raw_rows' => $preview['raw_rows'] ?? null,
        'removed_blank_rows' => $preview['removed_blank_rows'] ?? null,
    ];

    jsonResponse([
        'success' => true,
        'filename' => $file['name'],
        'columns' => $preview['columns'] ?? [],
        'sample_rows' => $preview['sample_rows'] ?? [],
        'suggested_mapping' => $preview['suggested_mapping'] ?? null,
        'column_profiles' => $preview['column_profiles'] ?? [],
        'total_rows' => $preview['total_rows'] ?? null,
        'raw_rows' => $preview['raw_rows'] ?? null,
        'removed_blank_rows' => $preview['removed_blank_rows'] ?? null,
    ]);
}

if (!$authToken) {
    jsonResponse([
        'success' => false,
        'error' => 'Live uploads require an authenticated backend session. Please sign in again and retry.'
    ], 401);
}

// ── REAL MODE: forward to FastAPI ─────────────────────────────
$clustering_method  = $_POST['clustering_method']  ?? 'kmeans';
$include_comparison = $_POST['include_comparison']  ?? 'true';

// Build multipart body manually (curl is cleaner than file_get_contents for files)
if (function_exists('curl_init')) {
    // Direct analysis upload path kept for compatibility with older form flows.
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
        CURLOPT_TIMEOUT        => 120,
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
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"include_comparison\"\r\n\r\n";
    $body .= "{$include_comparison}\r\n";
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
$payload = extractUploadPayload($decoded);

if ($httpCode === 200 || $httpCode === 201) {
    $jobId = $payload['job_id'] ?? null;

    if (!$jobId) {
        $backendSnippet = summarizeBackendResponse($response);
        jsonResponse([
            'success' => false,
            'error' => 'Backend returned success but did not include a job ID.',
            'backend_response' => $backendSnippet,
        ], 502);
    }

    // Store in session so processing.php polling can use it
    $_SESSION['current_job'] = [
        'job_id'     => $jobId,
        'status'     => $payload['status'] ?? 'pending',
        'created_at' => $payload['created_at'] ?? date('c'),
        'filename'   => $file['name'],
        'storage_provider' => $payload['storage_provider'] ?? null,
        'storage_bucket' => $payload['storage_bucket'] ?? null,
        'storage_object_path' => $payload['storage_object_path'] ?? null,
        'storage_public_url' => $payload['storage_public_url'] ?? null,
        'storage_warning' => $payload['storage_warning'] ?? null,
    ];

    jsonResponse([
        'success' => true,
        'job_id'  => $jobId,
        'status'  => $payload['status'] ?? 'pending',
        'storage_provider' => $payload['storage_provider'] ?? null,
        'storage_bucket' => $payload['storage_bucket'] ?? null,
        'storage_object_path' => $payload['storage_object_path'] ?? null,
        'storage_public_url' => $payload['storage_public_url'] ?? null,
        'storage_warning' => $payload['storage_warning'] ?? null,
    ]);

} else {
    $detail = $payload['detail'] ?? $payload['error'] ?? summarizeBackendResponse($response) ?? ('Upload failed (HTTP ' . $httpCode . ')');
    jsonResponse(['success' => false, 'error' => $detail], $httpCode ?: 500);
}

// ── HELPERS ───────────────────────────────────────────────────

/**
 * Get a basic preview of the uploaded file for column mapping
 */
function getFilePreview(string $filePath, string $ext): array {
    // Lightweight local preview fallback used if backend preview data is unavailable.
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
    // Simple name-based fallback; the FastAPI preview provides richer profiling.
    $colsLower = array_map('strtolower', $columns);
    $mapping   = [];

    $patterns = [
        'customer_id'  => ['customer_id','customerid','customer','cust_id','client_id','user_id','buyer_id','member_id'],
        'invoice_date' => ['invoice_date','invoicedate','date','transaction_date','order_date','purchase_date','txn_date'],
        'invoice_id'   => ['invoice_id','invoiceid','invoice_no','transaction_id','order_id','receipt_no','txn_id'],
        'amount'       => ['total line amount','total_line_amount','line_total','order_total','invoice_total','amount','total_amount','revenue','sales','value','sum'],
        'quantity'     => ['quantity','qty','units','order_qty','item_qty'],
        'unit_price'   => ['unit_price','unitprice','unit price','item_price','selling_price','price','rate'],
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

function extractUploadPayload($decoded): array {
    // Normalize different backend response shapes into one structure for the PHP page.
    if (!is_array($decoded)) {
        return [];
    }

    if (isset($decoded['job_id']) || isset($decoded['status']) || isset($decoded['detail']) || isset($decoded['error'])) {
        return $decoded;
    }

    if (isset($decoded['data']) && is_array($decoded['data'])) {
        return $decoded['data'];
    }

    if (isset($decoded['result']) && is_array($decoded['result'])) {
        return $decoded['result'];
    }

    return $decoded;
}

function summarizeBackendResponse($response): ?string {
    if (!is_string($response)) {
        return null;
    }

    $response = trim($response);
    if ($response === '') {
        return null;
    }

    if (strlen($response) > 300) {
        return substr($response, 0, 300) . '...';
    }

    return $response;
}

function parseIniSize($value): int {
    // Convert PHP shorthand sizes like 25M or 1G into bytes for upload-limit checks.
    if ($value === false || $value === null) {
        return 0;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    switch ($unit) {
        case 'g':
            return (int) round($number * 1024 * 1024 * 1024);
        case 'm':
            return (int) round($number * 1024 * 1024);
        case 'k':
            return (int) round($number * 1024);
        default:
            return (int) round($number);
    }
}
