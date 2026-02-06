<?php
// config.php - Core Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'whatsapp_sender');

define('APP_URL', 'http://localhost/whatsapp-bulk-sender');
define('APP_NAME', 'WhatsApp Bulk Sender Pro');
define('APP_VERSION', '1.0.0');

// WhatsApp API Configuration
define('WHATSAPP_API_URL', 'https://graph.instagram.com/v18.0');
define('WHATSAPP_BUSINESS_ACCOUNT_ID', 'YOUR_BUSINESS_ACCOUNT_ID');
define('WHATSAPP_API_TOKEN', 'YOUR_API_TOKEN');

// JWT Configuration
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours

// File Upload
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'mp4']);

// Security
define('CORS_ALLOWED_ORIGINS', ['http://localhost', 'http://localhost:3000']);
define('RATE_LIMIT', 100); // requests per minute
define('RATE_LIMIT_WINDOW', 60);

// Error Reporting
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Session Configuration
session_set_cookie_params([
    'secure' => false, // Set to true in production with HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable CORS
if (in_array($_SERVER['HTTP_ORIGIN'] ?? '', CORS_ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create directories if not exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
