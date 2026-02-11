<?php
/**
 * API Configuration
 * Settings for connecting to the Python FastAPI backend
 */

// Python FastAPI backend URL
// FastAPI runs on port 8000, nginx proxies 80 -> 8000
// PHP connects directly to FastAPI on port 8000
define('BACKEND_API_URL', 'http://localhost:8000/api');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Upload settings
define('MAX_FILE_SIZE', 25 * 1024 * 1024); // 25MB
define('ALLOWED_EXTENSIONS', ['csv', 'xlsx', 'xls']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Make a request to the Python backend API
 * 
 * @param string $endpoint API endpoint (e.g., '/jobs/upload')
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array|null $data Data to send (for POST requests)
 * @param string|null $token JWT auth token
 * @param array|null $files Files to upload
 * @return array Response data
 */
function apiRequest($endpoint, $method = 'GET', $data = null, $token = null, $files = null) {
    $url = BACKEND_API_URL . $endpoint;
    
    $ch = curl_init();
    
    $headers = ['Accept: application/json'];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout for large files
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        
        if ($files) {
            // Multipart form data with files
            $postData = $data ?: [];
            foreach ($files as $key => $filePath) {
                $postData[$key] = new CURLFile($filePath);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } elseif ($data) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'Connection error: ' . $error,
            'http_code' => 0
        ];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded,
        'http_code' => $httpCode
    ];
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Require authentication - returns user token or exits with 401
 */
function requireAuth() {
    session_start();
    
    if (!isset($_SESSION['auth_token'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    return $_SESSION['auth_token'];
}
