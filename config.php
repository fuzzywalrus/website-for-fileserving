<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Load environment variables from .env file
require_once 'env.php';

// Error reporting (dev vs prod)
if (($_ENV['ENVIRONMENT'] ?? '') === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// Check and load critical configuration values
$requiredEnvVars = ['BASE_DIR', 'PASSWORD', 'ENCRYPTION_KEY'];
$missingVars = [];

foreach ($requiredEnvVars as $var) {
    if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    $errorMessage = 'Required environment variables missing: ' . implode(', ', $missingVars);
    error_log($errorMessage);
    
    // Show an appropriate error message
    if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
        die('Configuration Error: ' . $errorMessage . '. Please check your .env file.');
    } else {
        die('Configuration error. Please contact the administrator.');
    }
}

// Primary configuration settings
$baseDir = $_ENV['BASE_DIR'];
$password = $_ENV['PASSWORD'];

// Validate and process encryption key
$rawEncryptionKey = $_ENV['ENCRYPTION_KEY'];

// Check if key is hex-encoded (64 hex chars = 32 bytes)
if (strlen($rawEncryptionKey) === 64 && ctype_xdigit($rawEncryptionKey)) {
    // Use hex-decoded key directly to preserve entropy
    $encryptionKey = hex2bin($rawEncryptionKey);
    if ($encryptionKey === false) {
        die('Configuration error: Invalid hex encryption key format.');
    }
} else {
    // For non-hex keys, enforce minimum length and derive via SHA-256
    if (strlen($rawEncryptionKey) < 32) {
        $errorMsg = 'ENCRYPTION_KEY must be at least 32 characters or 64 hex characters';
        error_log($errorMsg);
        if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
            die('Configuration Error: ' . $errorMsg);
        } else {
            die('Configuration error. Please contact the administrator.');
        }
    }
    // Hash the key to get consistent 32-byte result
    $encryptionKey = hash('sha256', $rawEncryptionKey, true);
}

// Secondary password settings (optional)
$hasSecondaryPassword = isset($_ENV['SECONDARY_PASSWORD']) && !empty($_ENV['SECONDARY_PASSWORD']);
$secondaryPassword = $hasSecondaryPassword ? $_ENV['SECONDARY_PASSWORD'] : '';
$secondaryBaseDir = isset($_ENV['SECONDARY_BASE_DIR']) && !empty($_ENV['SECONDARY_BASE_DIR']) ? 
    $_ENV['SECONDARY_BASE_DIR'] : $baseDir;
$secondaryTitle = isset($_ENV['SECONDARY_TITLE']) && !empty($_ENV['SECONDARY_TITLE']) ? 
    $_ENV['SECONDARY_TITLE'] : $_ENV['SITE_TITLE'] ?? 'Directory Browser';
$secondaryStats = isset($_ENV['SECONDARY_STATS']) && !empty($_ENV['SECONDARY_STATS']) ? 
    $_ENV['SECONDARY_STATS'] : $_ENV['SITE_STATS'] ?? '';

// Files and directories to exclude from listing
$excludedItems = isset($_ENV['EXCLUDED_ITEMS']) ? 
    array_map('trim', explode(',', $_ENV['EXCLUDED_ITEMS'])) : 
    ['@eaDir', '#recycle', '.DS_Store', 'Desktop DB', 'Desktop DF'];

// Site customization settings
$siteTitle = $_ENV['SITE_TITLE'] ?? 'Directory Browser';
$siteStats = $_ENV['SITE_STATS'] ?? '';

// ======================
// HLS Streaming Configuration
// ======================

// Enable/disable video streaming
$streamingEnabled = isset($_ENV['ENABLE_STREAMING']) && 
    (strtolower($_ENV['ENABLE_STREAMING']) === 'true' || $_ENV['ENABLE_STREAMING'] === '1');

// Video quality setting (480p, 720p, or 1080p)
$streamingQuality = $_ENV['STREAMING_QUALITY'] ?? '720p';

// Validate quality setting
$validQualities = ['480p', '720p', '1080p'];
if (!in_array($streamingQuality, $validQualities)) {
    error_log("Invalid STREAMING_QUALITY: {$streamingQuality}. Defaulting to 720p");
    $streamingQuality = '720p';
}

// Cache directory for HLS segments
$streamingCacheDir = $_ENV['STREAMING_CACHE_DIR'] ?? './cache/hls';

// Create cache directory if streaming is enabled
if ($streamingEnabled) {
    // Convert relative path to absolute if needed
    if (!preg_match('/^[\/~]/', $streamingCacheDir)) {
        $streamingCacheDir = dirname(__FILE__) . '/' . $streamingCacheDir;
    }
    
    // Create directory with proper permissions if it doesn't exist
    if (!is_dir($streamingCacheDir)) {
        if (!@mkdir($streamingCacheDir, 0755, true)) {
            error_log("Failed to create streaming cache directory: {$streamingCacheDir}");
            if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
                die('Configuration Error: Failed to create streaming cache directory. Check permissions.');
            }
        }
    }
    
    // Verify directory is writable
    if (!is_writable($streamingCacheDir)) {
        error_log("Streaming cache directory is not writable: {$streamingCacheDir}");
        if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
            die('Configuration Error: Streaming cache directory is not writable. Check permissions.');
        }
    }
}

// Cache TTL in seconds (default 24 hours)
$streamingCacheTTL = isset($_ENV['STREAMING_CACHE_TTL']) ? 
    (int)$_ENV['STREAMING_CACHE_TTL'] : 86400;

// Hardware acceleration setting
$hwAccel = $_ENV['HW_ACCEL'] ?? 'auto';
$hwAccelOverride = $_ENV['HW_ACCEL_OVERRIDE'] ?? '';

// Use override if set, otherwise use auto-detected value
if (!empty($hwAccelOverride)) {
    $hwAccel = $hwAccelOverride;
}

// Validate hardware acceleration setting
$validHwAccel = ['auto', 'intel', 'nvidia', 'amd', 'none'];
if (!in_array($hwAccel, $validHwAccel)) {
    error_log("Invalid HW_ACCEL: {$hwAccel}. Defaulting to auto");
    $hwAccel = 'auto';
}

// FFmpeg paths
$ffmpegPath = $_ENV['FFMPEG_PATH'] ?? '/usr/bin/ffmpeg';
$ffprobePath = $_ENV['FFPROBE_PATH'] ?? '/usr/bin/ffprobe';

// Verify FFmpeg is available if streaming is enabled
if ($streamingEnabled) {
    if (!file_exists($ffmpegPath) || !is_executable($ffmpegPath)) {
        error_log("FFmpeg not found or not executable at: {$ffmpegPath}");
        if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
            die('Configuration Error: FFmpeg not found. Please install FFmpeg or set correct FFMPEG_PATH in .env');
        }
    }
    
    if (!file_exists($ffprobePath) || !is_executable($ffprobePath)) {
        error_log("FFprobe not found or not executable at: {$ffprobePath}");
        if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development') {
            die('Configuration Error: FFprobe not found. Please install FFmpeg or set correct FFPROBE_PATH in .env');
        }
    }
}