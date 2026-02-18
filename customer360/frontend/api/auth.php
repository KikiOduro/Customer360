<?php
/**
 * Authentication API Endpoint
 * Handles login, register, logout by proxying to Python backend
 */
require_once __DIR__ . '/config.php';
session_start();

// Enable CORS for API calls
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get JSON body if content-type is application/json
$inputData = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? [];
}

// Merge with POST data
$requestData = array_merge($_POST, $inputData);

// Get action from multiple sources
$action = $_GET['action'] ?? $requestData['action'] ?? '';

// Check if this is an AJAX/API request (not a form submission)
function isApiRequest() {
    // Check for XMLHttpRequest header
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    }
    // Check for JSON content type
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        return true;
    }
    // Check for Accept: application/json header
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) {
        return true;
    }
    return false;
}

// Helper to respond with redirect or JSON
function respondOrRedirect($data, $successUrl = null, $errorUrl = null) {
    $isSuccess = isset($data['success']) && $data['success'];
    
    // For API/AJAX requests, always return JSON
    if (isApiRequest()) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    // For form submissions, redirect
    if ($isSuccess && $successUrl) {
        header('Location: ' . $successUrl);
        exit;
    }
    
    if (!$isSuccess && $errorUrl) {
        $errorMsg = urlencode($data['error'] ?? 'An error occurred');
        header('Location: ' . $errorUrl . '?error=' . $errorMsg);
        exit;
    }
    
    // Fallback to JSON
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkAuth();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleRegister() {
    global $requestData;
    
    $email = $requestData['email'] ?? '';
    $password = $requestData['password'] ?? '';
    $company_name = $requestData['company_name'] ?? '';
    $name = $requestData['name'] ?? $requestData['full_name'] ?? '';
    
    if (!$email || !$password) {
        respondOrRedirect(
            ['success' => false, 'error' => 'Email and password are required'],
            null,
            '../register.php'
        );
    }
    
    // Log the backend URL for debugging
    error_log("Backend URL: " . BACKEND_API_URL);
    error_log("Attempting to register user: " . $email);
    
    // Try Python backend first
    $result = apiRequest('/auth/register', 'POST', [
        'email' => $email,
        'password' => $password,
        'company_name' => $company_name
    ]);
    
    // Log for debugging
    error_log("Register API call to: " . BACKEND_API_URL . "/auth/register");
    error_log("Register result: " . json_encode($result));
    
    if ($result['success']) {
        // Auto-login after registration using JSON endpoint
        $loginResult = apiRequest('/auth/login/json', 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        
        error_log("Login after register result: " . json_encode($loginResult));
        
        if ($loginResult['success']) {
            $_SESSION['auth_token'] = $loginResult['data']['access_token'];
            $_SESSION['user_id'] = $loginResult['data']['user_id'] ?? 1;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $name ?: explode('@', $email)[0];
            $_SESSION['company_name'] = $company_name;
            $_SESSION['demo_mode'] = false;
            
            respondOrRedirect(
                ['success' => true, 'message' => 'Registration successful', 'redirect' => 'dashboard.php'],
                '../dashboard.php',
                null
            );
        }
    }
    
    // Check if backend returned an error (not connection failure)
    if ($result['http_code'] > 0) {
        // Backend responded but with an error (e.g., email already exists)
        $errorMsg = $result['data']['detail'] ?? 'Registration failed';
        respondOrRedirect(
            ['success' => false, 'error' => $errorMsg],
            null,
            '../register.php'
        );
    }
    
    // Demo mode: only if backend is truly unreachable (http_code = 0)
    $_SESSION['user_id'] = rand(1000, 9999);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $name ?: explode('@', $email)[0];
    $_SESSION['company_name'] = $company_name ?: 'My Business';
    $_SESSION['demo_mode'] = true;
    
    respondOrRedirect(
        ['success' => true, 'message' => 'Registration successful (demo mode)', 'redirect' => 'dashboard.php'],
        '../dashboard.php',
        null
    );
}

function handleLogin() {
    global $requestData;
    
    $email = $requestData['email'] ?? '';
    $password = $requestData['password'] ?? '';
    
    if (!$email || !$password) {
        respondOrRedirect(
            ['success' => false, 'error' => 'Email and password are required'],
            null,
            '../signin.php'
        );
    }
    
    // Try Python backend first - use /login/json for JSON body
    $result = apiRequest('/auth/login/json', 'POST', [
        'email' => $email,
        'password' => $password
    ]);
    
    // Log for debugging
    error_log("Login result: " . json_encode($result));
    
    if ($result['success']) {
        $_SESSION['auth_token'] = $result['data']['access_token'];
        $_SESSION['user_id'] = $result['data']['user_id'] ?? 1;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $result['data']['user_name'] ?? explode('@', $email)[0];
        $_SESSION['company_name'] = $result['data']['company_name'] ?? '';
        $_SESSION['demo_mode'] = false;
        
        respondOrRedirect(
            ['success' => true, 'message' => 'Login successful', 'redirect' => 'dashboard.php'],
            '../dashboard.php',
            null
        );
    }
    
    // Check if backend returned an error (not connection failure)
    if ($result['http_code'] > 0) {
        // Backend responded but with an error (e.g., wrong password)
        $errorMsg = $result['data']['detail'] ?? 'Invalid credentials';
        respondOrRedirect(
            ['success' => false, 'error' => $errorMsg],
            null,
            '../signin.php'
        );
    }
    
    // Demo mode: only if backend is truly unreachable (http_code = 0)
    $_SESSION['user_id'] = rand(1000, 9999);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = explode('@', $email)[0];
    $_SESSION['company_name'] = 'My Business';
    $_SESSION['demo_mode'] = true;
    
    respondOrRedirect(
        ['success' => true, 'message' => 'Login successful (demo mode)', 'redirect' => 'dashboard.php'],
        '../dashboard.php',
        null
    );
}

function handleLogout() {
    session_destroy();
    
    // If called via API/AJAX
    if (isApiRequest()) {
        jsonResponse(['success' => true, 'redirect' => 'signin.php']);
    }
    
    // Regular redirect
    header('Location: ../signin.php');
    exit;
}

function checkAuth() {
    if (isset($_SESSION['user_id'])) {
        jsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'company' => $_SESSION['company_name'] ?? ''
            ]
        ]);
    } else {
        jsonResponse(['authenticated' => false], 401);
    }
}
