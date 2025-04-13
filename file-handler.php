<?php
// Include configuration file
require_once 'config.php';

// Include authentication file
require_once 'auth.php';

// If not authenticated, deny access
if (!$isAuthenticated) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
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

// Set the content type header
if (isset($contentTypes[$extension])) {
    header('Content-Type: ' . $contentTypes[$extension]);
} else {
    // For unknown types, use octet-stream and force download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($realFilePath) . '"');
}

// Set content length
header('Content-Length: ' . filesize($realFilePath));

// Disable caching for dynamic content
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Output the file content
readfile($realFilePath);
exit;
?>