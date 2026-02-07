<?php
/**
 * Authentication API Endpoint
 * Handles login, register, logout by proxying to Python backend
 */
require_once __DIR__ . '/config.php';
session_start();

// Check if this is an AJAX request
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Helper to respond with redirect or JSON
function respondOrRedirect($data, $successUrl = null, $errorUrl = null) {
    $isSuccess = isset($data['success']) && $data['success'];
    
    if (isAjax()) {
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $name = $_POST['name'] ?? '';
    
    if (!$email || !$password) {
        respondOrRedirect(
            ['success' => false, 'error' => 'Email and password are required'],
            null,
            '../register.php'
        );
    }
    
    // Call Python backend
    $result = apiRequest('/auth/register', 'POST', [
        'email' => $email,
        'password' => $password,
        'company_name' => $company_name
    ]);
    
    if ($result['success']) {
        // Auto-login after registration
        $loginResult = apiRequest('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        
        if ($loginResult['success']) {
            $_SESSION['auth_token'] = $loginResult['data']['access_token'];
            $_SESSION['user_id'] = $loginResult['data']['user_id'] ?? 1;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $name ?: explode('@', $email)[0];
            $_SESSION['company_name'] = $company_name;
            
            respondOrRedirect(
                ['success' => true, 'message' => 'Registration successful', 'redirect' => 'dashboard.php'],
                '../dashboard.php',
                null
            );
        }
    }
    
    // For demo: allow registration if backend is down
    if ($result['http_code'] === 0) {
        // Backend not available - create demo session
        $_SESSION['user_id'] = 1;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name ?: explode('@', $email)[0];
        $_SESSION['company_name'] = $company_name ?: 'Demo Company';
        $_SESSION['demo_mode'] = true;
        
        respondOrRedirect(
            ['success' => true, 'message' => 'Registration successful (demo mode)', 'redirect' => 'dashboard.php', 'demo_mode' => true],
            '../dashboard.php',
            null
        );
    }
    
    respondOrRedirect(
        ['success' => false, 'error' => $result['data']['detail'] ?? 'Registration failed'],
        null,
        '../register.php'
    );
}

function handleLogin() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        respondOrRedirect(
            ['success' => false, 'error' => 'Email and password are required'],
            null,
            '../signin.php'
        );
    }
    
    // Call Python backend
    $result = apiRequest('/auth/login', 'POST', [
        'email' => $email,
        'password' => $password
    ]);
    
    if ($result['success']) {
        $_SESSION['auth_token'] = $result['data']['access_token'];
        $_SESSION['user_id'] = $result['data']['user_id'] ?? 1;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $result['data']['user_name'] ?? explode('@', $email)[0];
        $_SESSION['company_name'] = $result['data']['company_name'] ?? '';
        
        respondOrRedirect(
            ['success' => true, 'message' => 'Login successful', 'redirect' => 'dashboard.php'],
            '../dashboard.php',
            null
        );
    }
    
    // For demo: allow login with any credentials if backend is down
    if ($result['http_code'] === 0) {
        // Backend not available - create demo session
        $_SESSION['user_id'] = 1;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = explode('@', $email)[0];
        $_SESSION['company_name'] = 'Demo Company';
        $_SESSION['demo_mode'] = true;
        
        respondOrRedirect(
            ['success' => true, 'message' => 'Login successful (demo mode)', 'redirect' => 'dashboard.php', 'demo_mode' => true],
            '../dashboard.php',
            null
        );
    }
    
    respondOrRedirect(
        ['success' => false, 'error' => $result['data']['detail'] ?? 'Invalid credentials'],
        null,
        '../signin.php'
    );
}

function handleLogout() {
    session_destroy();
    
    // If called via AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
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
