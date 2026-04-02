<?php
/**
 * Processing/Jobs API Endpoint
 * Backend-only proxy for job status, results, history, and deletion.
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'status';
$jobId = $_GET['job_id'] ?? $_SESSION['current_job']['job_id'] ?? null;

switch ($action) {
    case 'status':
        handleStatus($jobId);
        break;
    case 'results':
        handleResults($jobId);
        break;
    case 'list':
        handleList();
        break;
    case 'report':
        handleReport($jobId);
        break;
    case 'delete':
        handleDelete($jobId);
        break;
    case 'cancel':
        handleCancel($jobId);
        break;
    case 'history':
        handleHistory();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function requireToken(): string {
    $token = $_SESSION['auth_token'] ?? null;
    if (!$token) {
        jsonResponse(['error' => 'No authenticated backend session is available'], 401);
    }
    return $token;
}

function jsonAuthExpired(string $message = 'Your session has expired. Please sign in again.'): void {
    unset($_SESSION['auth_token'], $_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['company_name'], $_SESSION['current_job'], $_SESSION['analysis_results']);

    jsonResponse([
        'success' => false,
        'error' => $message,
        'reauth_required' => true,
        'redirect' => 'signin.php?error=' . rawurlencode($message),
    ], 401);
}

function handleStatus($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/status/$jobId", 'GET', null, $token);

    if (!$result['success']) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to get job status'], $result['http_code'] ?: 502);
    }

    $_SESSION['current_job']['status'] = $result['data']['status'];
    if (!empty($result['data']['completed_at'])) {
        $_SESSION['current_job']['completed_at'] = $result['data']['completed_at'];
    }

    jsonResponse([
        'success' => true,
        'job_id' => $result['data']['job_id'],
        'status' => $result['data']['status'],
        'progress_percent' => $result['data']['progress_percent'] ?? null,
        'progress_stage' => $result['data']['progress_stage'] ?? null,
        'progress_message' => $result['data']['progress_message'] ?? null,
        'error_message' => $result['data']['error_message'] ?? null,
        'created_at' => $result['data']['created_at'],
        'completed_at' => $result['data']['completed_at'] ?? null,
    ]);
}

function handleResults($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/results/$jobId", 'GET', null, $token);

    if (!$result['success']) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to get results'], $result['http_code'] ?: 502);
    }

    $_SESSION['analysis_results'] = $result['data'];

    jsonResponse([
        'success' => true,
        'results' => $result['data'],
    ]);
}

function handleList() {
    $token = requireToken();
    $result = apiRequest('/jobs/', 'GET', null, $token);

    if (!$result['success']) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to list jobs'], $result['http_code'] ?: 502);
    }

    jsonResponse([
        'success' => true,
        'jobs' => is_array($result['data']) ? $result['data'] : [],
    ]);
}

function handleReport($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $reportUrl = BACKEND_API_URL . "/jobs/report/$jobId";

    if (function_exists('curl_init')) {
        $ch = curl_init($reportUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/pdf',
            ],
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            jsonResponse(['error' => 'Failed to download report: ' . $curlError], 502);
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        if ($httpCode < 200 || $httpCode >= 300) {
            $decoded = json_decode($body, true);
            jsonResponse(['error' => $decoded['detail'] ?? 'Failed to download report'], $httpCode ?: 502);
        }

        $contentType = 'application/pdf';
        $contentDisposition = 'attachment; filename="customer360_report.pdf"';
        foreach (explode("\r\n", $rawHeaders) as $headerLine) {
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            }
            if (stripos($headerLine, 'Content-Disposition:') === 0) {
                $contentDisposition = trim(substr($headerLine, strlen('Content-Disposition:')));
            }
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $contentDisposition);
        echo $body;
        exit;
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", [
            'Authorization: Bearer ' . $token,
            'Accept: application/pdf',
        ]),
        'timeout' => 120,
        'ignore_errors' => true,
    ]]);

    $body = file_get_contents($reportUrl, false, $context);
    if ($body === false) {
        jsonResponse(['error' => 'Failed to download report'], 502);
    }

    $httpCode = 200;
    $contentType = 'application/pdf';
    $contentDisposition = 'attachment; filename="customer360_report.pdf"';
    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headerLine, $matches)) {
            $httpCode = (int) $matches[1];
        } elseif (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:')));
        } elseif (stripos($headerLine, 'Content-Disposition:') === 0) {
            $contentDisposition = trim(substr($headerLine, strlen('Content-Disposition:')));
        }
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($body, true);
        jsonResponse(['error' => $decoded['detail'] ?? 'Failed to download report'], $httpCode ?: 502);
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $contentDisposition);
    echo $body;
    exit;
}

function handleDelete($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/$jobId", 'DELETE', null, $token);

    if (!$result['success']) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to delete report'], $result['http_code'] ?: 502);
    }

    if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
        unset($_SESSION['current_job'], $_SESSION['analysis_results']);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Report deleted successfully',
    ]);
}

function handleCancel($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/$jobId/cancel", 'POST', null, $token);

    if (!$result['success']) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to cancel job'], $result['http_code'] ?: 502);
    }

    if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
        $_SESSION['current_job']['status'] = 'cancelled';
    }

    jsonResponse([
        'success' => true,
        'message' => 'Job cancelled successfully',
    ]);
}

function handleHistory() {
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, intval($_GET['per_page'] ?? 10));
    $status = trim((string) ($_GET['status'] ?? ''));
    $search = trim((string) ($_GET['search'] ?? ''));

    $token = requireToken();
    $result = apiRequest('/jobs/', 'GET', null, $token);

    if (!$result['success'] || !is_array($result['data'])) {
        if (($result['http_code'] ?? 0) === 401) {
            jsonAuthExpired($result['data']['detail'] ?? 'Your session has expired. Please sign in again.');
        }
        jsonResponse(['error' => $result['data']['detail'] ?? 'Failed to load job history'], $result['http_code'] ?: 502);
    }

    $jobs = $result['data'];

    if ($status !== '') {
        $jobs = array_values(array_filter($jobs, fn($job) => ($job['status'] ?? '') === $status));
    }
    if ($search !== '') {
        $searchLower = strtolower($search);
        $jobs = array_values(array_filter($jobs, function ($job) use ($searchLower) {
            return strpos(strtolower($job['original_filename'] ?? ''), $searchLower) !== false;
        }));
    }

    $total = count($jobs);
    $offset = ($page - 1) * $perPage;

    jsonResponse([
        'success' => true,
        'jobs' => array_slice($jobs, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ]);
}
