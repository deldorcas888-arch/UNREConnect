<?php
// Set CORS headers to allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Basic validation
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit();
}

// Ensure logs directory exists and is writable
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        error_log('Failed to create logs directory');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
        exit();
    }
}

// Create a log entry
$logEntry = sprintf(
    "[%s] %s\nURL: %s\nUser Agent: %s\nStack Trace:\n%s\nContext: %s\n\n",
    date('Y-m-d H:i:s'),
    $data['message'] ?? 'No error message',
    $data['url'] ?? 'Unknown URL',
    $data['userAgent'] ?? 'Unknown User Agent',
    $data['stack'] ?? 'No stack trace',
    json_encode($data['context'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// Write to log file
$logFile = $logDir . '/client-errors-' . date('Y-m-d') . '.log';
$result = file_put_contents($logFile, $logEntry, FILE_APPEND);

if ($result === false) {
    error_log('Failed to write to error log');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to log error']);
    exit();
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success']);
