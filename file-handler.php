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

// CSRF Protection: Validate Origin/Referer headers for file downloads
function validateFileAccessHeaders() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Allow same-origin requests
    if (!empty($origin)) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost === $host) {
            return true;
        }
    }
    
    // Allow requests from same host via referer
    if (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost === $host) {
            return true;
        }
    }
    
    // Allow direct access (no referer/origin) for direct links and bookmarks
    if (empty($origin) && empty($referer)) {
        return true;
    }
    
    // Log potential CSRF attempt
    $clientIP = getClientIP();
    error_log("Potential CSRF attempt from IP: {$clientIP}, Origin: {$origin}, Referer: {$referer}, Host: {$host}");
    
    return false;
}

// Perform CSRF header validation
if (!validateFileAccessHeaders()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain');
    exit('Cross-origin request blocked for security');
}

// If we have a file ID, decode it
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Validate encrypted ID format first
    $encryptedId = validateEncryptedId($_GET['id']);
    if ($encryptedId === false) {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid file ID format');
    }
    
    // Get the file path from the hash
    $decrypted = decryptPath($encryptedId);
    
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

// Validate file path input
$validatedPath = validateFilePath($filePath);
if ($validatedPath === false) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file path format');
}
$filePath = $validatedPath;

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
    // Parse Range header according to RFC 7233
    if (preg_match('/bytes=\s*(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
        $rangeStart = $m[1] === '' ? null : (int)$m[1];
        $rangeEnd = $m[2] === '' ? null : (int)$m[2];

        if ($rangeStart === null && $rangeEnd !== null) {
            // Suffix-byte-range-spec: bytes=-N (last N bytes)
            $start = max(0, $fileSize - $rangeEnd);
            $end = $fileSize - 1;
        } elseif ($rangeStart !== null && $rangeEnd === null) {
            // Byte-range-spec: bytes=N- (from byte N to end)
            $start = $rangeStart;
            $end = $fileSize - 1;
        } elseif ($rangeStart !== null && $rangeEnd !== null) {
            // Byte-range-spec: bytes=N-M (from byte N to byte M)
            $start = $rangeStart;
            $end = min($rangeEnd, $fileSize - 1);
            
            // Check for invalid range where start > end
            if ($end < $start) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $fileSize);
                exit('Range Not Satisfiable');
            }
        } else {
            // Invalid range specification
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $fileSize);
            exit('Invalid Range');
        }
        
        // Ensure start is within valid bounds
        if ($start >= $fileSize) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $fileSize);
            exit('Range Not Satisfiable');
        }
        
        $isPartial = true;
    } else {
        // Malformed Range header - ignore and serve full content
        $isPartial = false;
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

// Set content disposition with RFC 5987 compliant filename handling
$filename = basename($realFilePath);
$disposition = $forceDownload ? 'attachment' : 'inline';

// Escape quotes and backslashes for the quoted filename parameter
$quoted = str_replace(['\\','"'], ['\\\\','\\"'], $filename);

// Create RFC 5987 compliant Content-Disposition header with UTF-8 filename support
$cd = $disposition . '; filename="' . $quoted . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
header('Content-Disposition: ' . $cd);

// Add additional headers for better transfer handling
header('Accept-Ranges: bytes');

// CRITICAL: Prevent caching of authenticated content in shared caches
header('Cache-Control: private, max-age=0, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

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