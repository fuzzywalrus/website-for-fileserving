<?php
// Immediate session start for authentication
session_start();

// Include configuration file
require_once 'config.php';

// Include functions file (for decryptPath)
require_once 'functions.php';

/**
 * Securely decodes URL parameters to prevent double encoding attacks
 * (Added here in case you haven't updated functions.php)
 * 
 * @param string $input The input string to decode
 * @return string The fully decoded string
 */
function secureUrlDecode($input) {
    $decoded = $input;
    $prevDecoded = '';
    
    // Keep decoding until there are no more changes (handles double encoding)
    while ($decoded !== $prevDecoded) {
        $prevDecoded = $decoded;
        $decoded = urldecode($prevDecoded);
    }
    
    return $decoded;
}

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

// Add extra protection against path traversal
$filePath = secureUrlDecode($filePath);
$filePath = str_replace(['../', '..\\', './', '.\\'], '', $filePath);
$filePath = ltrim($filePath, '/');

// Make sure the file exists in the base directory
$realBasePath = realpath($baseDir);
$fullPath = $baseDir . '/' . $filePath;
$realFilePath = realpath($fullPath);

if (!$realFilePath || strpos($realFilePath, $realBasePath) !== 0 || !is_file($realFilePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Get file information
$fileInfo = pathinfo($realFilePath);
$extension = strtolower($fileInfo['extension']);
$fileSize = filesize($realFilePath);

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

// Determine content type
$contentType = 'application/octet-stream'; // Default
if (isset($contentTypes[$extension]) && !$forceDownload) {
    $contentType = $contentTypes[$extension];
}

// Check if we need to serve a range (for video streaming)
$isPartial = false;
$start = 0;
$end = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    $isPartial = true;
    list($unit, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    
    if ($unit == 'bytes') {
        // Multiple ranges could be specified at the same time
        // We'll just handle the first range for now
        list($range) = explode(',', $range, 2);
        
        // Range can be: bytes=0-100 or bytes=100- or bytes=-100
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range, 2);
        }
        
        // Handle cases where range is bytes=100- or bytes=-100
        $start = ($start === '') ? 0 : intval($start);
        $end = ($end === '') ? $fileSize - 1 : intval($end);
        
        // Clamp values
        $start = max(0, min($start, $fileSize - 1));
        $end = min($fileSize - 1, max($start, $end));
    }
}

// ---------------------------
// IMPORTANT: Disable output buffering right before sending file
// ---------------------------
session_write_close();

if (ob_get_level()) ob_end_clean();

// ---------------------------
// Set time limit to 0 right before sending the file
// ---------------------------
set_time_limit(0);

// Begin sending headers
if ($isPartial) {
    // Partial content for range requests
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));
} else {
    // Normal download
    header('Content-Length: ' . $fileSize);
}

// Set the content type header
header('Content-Type: ' . $contentType);

// Set content disposition based on download mode
if ($forceDownload) {
    header('Content-Disposition: attachment; filename="' . basename($realFilePath) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($realFilePath) . '"');
}

// Add additional headers for better transfer handling
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400'); // Cache for 1 day

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Flush headers
flush();

// Open the file in binary mode
$handle = fopen($realFilePath, 'rb');
    
if (!$handle) {
    exit('Cannot open file');
}

// For partial content, seek to the start position
if ($isPartial && $start > 0) {
    fseek($handle, $start);
}

// The length to send
$length = $end - $start + 1;

// Stream the file in chunks
$chunkSize = 1024 * 1024; // 1MB chunks
while (!feof($handle) && $length > 0) {
    // Read the smaller of: chunk size or remaining length
    $readSize = min($chunkSize, $length);
    $buffer = fread($handle, $readSize);
    
    if ($buffer === false) {
        break;
    }
    
    echo $buffer;
    flush();
    
    $length -= strlen($buffer);
    
    // Check if the client disconnected
    if (connection_status() !== CONNECTION_NORMAL) {
        break;
    }
}

// Close the file handle
fclose($handle);

// ---------------------------
// CRITICAL: Make sure script ends completely after sending the file
// ---------------------------
exit();