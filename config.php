<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Base directory to browse
$baseDir = './plex';

// Password for accessing the directory browser
$password = "password";

// Files and directories to exclude from listing
$excludedItems = [
    '@eaDir',
    '#recycle',
    '.DS_Store',
    'Desktop DB',
    'Desktop DF'
];
?>