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

function handleStatus($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/status/$jobId", 'GET', null, $token);

    if (!$result['success']) {
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

    requireToken();
    header("Location: " . BACKEND_API_URL . "/jobs/report/$jobId");
    exit;
}

function handleDelete($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }

    $token = requireToken();
    $result = apiRequest("/jobs/$jobId", 'DELETE', null, $token);

    if (!$result['success']) {
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
