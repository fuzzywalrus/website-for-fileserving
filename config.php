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

// Base directory to browse
$baseDir = $_ENV['BASE_DIR'];

// Password for accessing the directory browser
$password = $_ENV['PASSWORD'];

// Encryption key for URL obfuscation
$encryptionKey = $_ENV['ENCRYPTION_KEY'];

// Files and directories to exclude from listing
$excludedItems = isset($_ENV['EXCLUDED_ITEMS']) ? 
    array_map('trim', explode(',', $_ENV['EXCLUDED_ITEMS'])) : 
    ['@eaDir', '#recycle', '.DS_Store', 'Desktop DB', 'Desktop DF'];

// Site customization settings
$siteTitle = $_ENV['SITE_TITLE'] ?? 'Directory Browser';
$siteStats = $_ENV['SITE_STATS'] ?? 'Self Hosted!' ;
?>