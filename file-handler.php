<?php
// Immediate session start for authentication
session_start();

// Include configuration file
require_once 'config.php';

// Include functions file (for decryptPath)
require_once 'functions.php';

// Authentication check - must happen before ANY output
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Save the requested URL for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: index.php');
    exit;
}

// If we have a file ID, decode it
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Get the file path from the hash
    $decrypted = decryptPath($_GET['id']);
    
    if ($decrypted !== false) {
        $_GET['path'] = $decrypted;
    } else {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid file ID');
    }
}

// Get the requested file path
$filePath = isset($_GET['path']) ? $_GET['path'] : '';

// Validate file path
if (empty($filePath)) {
    header('HTTP/1.1 400 Bad Request');
    exit('File path not specified');
}

// Prevent directory traversal
$filePath = str_replace('../', '', $filePath);
$filePath = str_replace('..\\', '', $filePath);

// Make sure the file exists in the base directory
$realBasePath = realpath($baseDir);
$fullPath = $baseDir . '/' . ltrim($filePath, '/');
$realFilePath = realpath($fullPath);

if (!$realFilePath || strpos($realFilePath, $realBasePath) !== 0 || !is_file($realFilePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Get file information
$fileInfo = pathinfo($realFilePath);
$extension = strtolower($fileInfo['extension']);

// Set the appropriate content type
$contentTypes = [
    'txt' => 'text/plain',
    'html' => 'text/html',
    'htm' => 'text/html',
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'audio/ogg',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav'
];

// Force download mode
$forceDownload = isset($_GET['download']) && $_GET['download'] === '1';

// Set the content type header
if (isset($contentTypes[$extension]) && !$forceDownload) {
    header('Content-Type: ' . $contentTypes[$extension]);
} else {
    // For unknown types or when forcing download, use octet-stream
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($realFilePath) . '"');
}

// Set content length
header('Content-Length: ' . filesize($realFilePath));

// Disable caching for dynamic content
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Output the file content
readfile($realFilePath);
exit;