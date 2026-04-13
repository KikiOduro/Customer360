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
    case 'validate':
        handleValidateMapping();
        break;
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

function parseMappingRequest(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    return is_array($data) ? $data : [];
}

function validateMappingPayload(array $data): array {
    $mapping = $data['mapping'] ?? [];
    $amountSourceMode = $data['amount_source_mode'] ?? 'direct';
    $allowSyntheticCustomerId = !empty($data['allow_synthetic_customer_id']);
    $allowSyntheticInvoiceDate = !empty($data['allow_synthetic_invoice_date']);

    $required = ['invoice_id'];
    if (!$allowSyntheticCustomerId) {
        $required[] = 'customer_id';
    }
    if (!$allowSyntheticInvoiceDate) {
        $required[] = 'date';
    }

    foreach ($required as $field) {
        if (empty($mapping[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required mapping: $field"], 400);
        }
    }

    if ($amountSourceMode === 'formula') {
        if (empty($mapping['quantity']) || empty($mapping['unit_price'])) {
            jsonResponse(['success' => false, 'error' => 'Formula mode requires both Quantity and Unit Price mappings'], 400);
        }
    } elseif (empty($mapping['amount'])) {
        jsonResponse(['success' => false, 'error' => 'Missing required mapping: amount'], 400);
    }

    return $mapping;
}

function buildBackendMappingFormData(array $mapping, array $data): array {
    // Translate the UI's friendly field names into the FastAPI parameter names.
    // Keep include_comparison true unless the frontend explicitly overrides it so
    // every normal run compares the available clustering methods by default.
    $formData = [
        'customer_id_col' => $mapping['customer_id'] ?? '',
        'invoice_date_col' => $mapping['date'] ?? '',
        'invoice_id_col' => $mapping['invoice_id'] ?? '',
        'amount_col' => $mapping['amount'] ?? '',
        'clustering_method' => $data['clustering_method'] ?? 'kmeans',
        'include_comparison' => $data['include_comparison'] ?? true,
        'amount_source_mode' => $data['amount_source_mode'] ?? 'direct',
        'invoice_date_format' => $data['invoice_date_format'] ?? '',
        'dayfirst' => !empty($data['dayfirst']) ? 'true' : 'false',
        'decimal_separator' => $data['decimal_separator'] ?? '.',
        'thousands_separator' => $data['thousands_separator'] ?? ',',
        'currency_symbol' => $data['currency_symbol'] ?? '',
        'negative_amount_policy' => $data['negative_amount_policy'] ?? 'exclude',
        'allow_synthetic_customer_id' => !empty($data['allow_synthetic_customer_id']) ? 'true' : 'false',
        'allow_synthetic_invoice_date' => !empty($data['allow_synthetic_invoice_date']) ? 'true' : 'false',
    ];

    foreach (['quantity', 'unit_price', 'product', 'category'] as $field) {
        if (!empty($mapping[$field])) {
            $formData[$field . '_col'] = $mapping[$field];
        }
    }

    return $formData;
}

function handleValidateMapping() {
    // Validation reuses the uploaded preview file instead of trusting only the form.
    // This catches real parsing problems before the user starts the longer job.
    $data = parseMappingRequest();
    $mapping = validateMappingPayload($data);

    if (!isset($_SESSION['current_upload'])) {
        jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
    }

    $upload = $_SESSION['current_upload'];
    $token = $_SESSION['auth_token'] ?? null;

    if (!$token || empty($upload['filepath']) || !file_exists($upload['filepath'])) {
        jsonResponse(['success' => false, 'error' => 'Could not validate mapping because the preview file or session token is unavailable.'], 401);
    }

    $result = apiRequest(
        '/jobs/upload/validate-mapping',
        'POST',
        buildBackendMappingFormData($mapping, $data),
        $token,
        ['file' => $upload['filepath']]
    );

    if (!$result['success']) {
        jsonResponse([
            'success' => false,
            'error' => $result['data']['detail'] ?? $result['error'] ?? 'Mapping validation failed'
        ], $result['http_code'] ?: 502);
    }

    $_SESSION['mapping_validation'] = $result['data']['validation'] ?? null;

    jsonResponse([
        'success' => true,
        'validation' => $result['data']['validation'] ?? null
    ]);
}

/**
 * Save column mapping and start analysis job
 */
function handleSaveMapping() {
    $data = parseMappingRequest();
    $mapping = validateMappingPayload($data);
    
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
        'amount' => $mapping['amount'] ?? null,
        'quantity' => $mapping['quantity'] ?? null,
        'unit_price' => $mapping['unit_price'] ?? null,
        'product' => $mapping['product'] ?? null,
        'category' => $mapping['category'] ?? null,
        'amount_source_mode' => $data['amount_source_mode'] ?? 'direct',
        'invoice_date_format' => $data['invoice_date_format'] ?? '',
        'dayfirst' => !empty($data['dayfirst']),
        'decimal_separator' => $data['decimal_separator'] ?? '.',
        'thousands_separator' => $data['thousands_separator'] ?? ',',
        'currency_symbol' => $data['currency_symbol'] ?? '',
        'negative_amount_policy' => $data['negative_amount_policy'] ?? 'exclude',
        'allow_synthetic_customer_id' => !empty($data['allow_synthetic_customer_id']),
        'allow_synthetic_invoice_date' => !empty($data['allow_synthetic_invoice_date'])
    ];
    
    // Try to start job via Python backend. PHP owns the session and page redirects;
    // FastAPI owns the actual analytics work.
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token && file_exists($upload['filepath'])) {
        $result = apiRequest('/jobs/upload/with-mapping', 'POST', buildBackendMappingFormData($mapping, $data), $token, [
            'file' => $upload['filepath']
        ]);
        
        if ($result['success']) {
            $_SESSION['current_job'] = [
                'job_id' => $result['data']['job_id'],
                'status' => $result['data']['status'],
                'filename' => $upload['filename'],
                'job_name' => $upload['job_name'] ?? null,
                'created_at' => $result['data']['created_at'],
                'storage_provider' => $result['data']['storage_provider'] ?? null,
                'storage_bucket' => $result['data']['storage_bucket'] ?? null,
                'storage_object_path' => $result['data']['storage_object_path'] ?? null,
                'storage_public_url' => $result['data']['storage_public_url'] ?? null,
            ];

            if (!empty($upload['filepath']) && file_exists($upload['filepath'])) {
                @unlink($upload['filepath']);
            }
            unset($_SESSION['current_upload']);
            
            jsonResponse([
                'success' => true,
                'job_id' => $result['data']['job_id'],
                'status' => $result['data']['status'],
                'redirect' => 'processing.php'
            ]);
        }
    }
    
    jsonResponse([
        'success' => false,
        'error' => 'Could not start a live analysis job. Re-upload the file and try again.'
    ], 502);
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
        'suggested_mapping' => $upload['suggested_mapping'] ?? null,
        'column_profiles' => $upload['column_profiles'] ?? [],
        'total_rows' => $upload['total_rows'] ?? null,
        'raw_rows' => $upload['raw_rows'] ?? null,
        'removed_blank_rows' => $upload['removed_blank_rows'] ?? null,
        'mapping_validation' => $_SESSION['mapping_validation'] ?? null
    ]);
}
