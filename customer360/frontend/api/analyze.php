<?php
/**
 * Analysis API proxy.
 * Bridges the PHP frontend to the synchronous FastAPI analysis endpoints.
 */
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'analyze';
$token = $_SESSION['auth_token'] ?? null;

if (!$token) {
    jsonResponse(['success' => false, 'error' => 'No authenticated backend session is available'], 401);
}

switch ($action) {
    case 'analyze':
        handleAnalyze($token);
        break;
    case 'chart':
        streamBinaryProxy(BACKEND_API_URL . '/analysis/charts/' . rawurlencode($_GET['job_id'] ?? '') . '/' . rawurlencode($_GET['file'] ?? ''), $token, 'image/png');
        break;
    case 'report':
        streamBinaryProxy(BACKEND_API_URL . '/analysis/report/' . rawurlencode($_GET['job_id'] ?? ''), $token, 'application/pdf');
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleAnalyze(string $token): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    if (!isset($_FILES['file'])) {
        jsonResponse(['success' => false, 'error' => 'No CSV file provided'], 400);
    }

    $mapping = $_POST['mapping'] ?? null;
    if (!$mapping) {
        jsonResponse(['success' => false, 'error' => 'No mapping payload provided'], 400);
    }

    $file = $_FILES['file'];
    $postFields = [
        'file' => new CURLFile($file['tmp_name'], $file['type'] ?: 'text/csv', $file['name']),
        'mapping' => $mapping,
        'clustering_method' => $_POST['clustering_method'] ?? 'kmeans',
        'include_comparison' => $_POST['include_comparison'] ?? 'true',
    ];

    $ch = curl_init(BACKEND_API_URL . '/analysis/analyze');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 240,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'Could not connect to the analysis server: ' . $curlError], 503);
    }

    $decoded = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        jsonResponse(['success' => false, 'error' => $decoded['detail'] ?? $decoded['error'] ?? 'Analysis failed'], $httpCode ?: 502);
    }

    $jobId = $decoded['job_id'] ?? ($decoded['meta']['job_id'] ?? null);
    if ($jobId) {
        $_SESSION['current_job'] = [
            'job_id' => $jobId,
            'status' => 'completed',
            'filename' => $file['name'],
            'created_at' => $decoded['meta']['created_at'] ?? date('c'),
        ];
    }
    $_SESSION['analysis_results'] = $decoded;

    if (isset($decoded['charts']) && is_array($decoded['charts']) && $jobId) {
        foreach ($decoded['charts'] as $key => $value) {
            if ($value) {
                $decoded['charts'][$key] = 'api/analyze.php?action=chart&job_id=' . urlencode($jobId) . '&file=' . urlencode(basename($value));
            }
        }
    }

    if ($jobId) {
        $decoded['report_url'] = 'api/analyze.php?action=report&job_id=' . urlencode($jobId);
    }

    jsonResponse([
        'success' => true,
        'data' => $decoded,
    ]);
}

function streamBinaryProxy(string $url, string $token, string $fallbackContentType): void {
    if (str_ends_with($url, '//') || str_ends_with($url, '/')) {
        jsonResponse(['success' => false, 'error' => 'Missing job or file identifier'], 400);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 240,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'Proxy request failed: ' . $curlError], 502);
    }

    $rawHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($body, true);
        jsonResponse(['success' => false, 'error' => $decoded['detail'] ?? 'Request failed'], $httpCode ?: 502);
    }

    $contentType = $fallbackContentType;
    $contentDisposition = null;
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (stripos($line, 'Content-Type:') === 0) {
            $contentType = trim(substr($line, strlen('Content-Type:')));
        }
        if (stripos($line, 'Content-Disposition:') === 0) {
            $contentDisposition = trim(substr($line, strlen('Content-Disposition:')));
        }
    }

    header('Content-Type: ' . $contentType);
    if ($contentDisposition) {
        header('Content-Disposition: ' . $contentDisposition);
    }
    echo $body;
    exit;
}
