<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Load environment variables from .env file
require_once 'env.php';

// Error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

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