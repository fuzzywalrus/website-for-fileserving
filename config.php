<?php
// Error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Base directory to browse
$baseDir = './plex';

// Password for accessing the directory browser
$password = "password23";

// Files and directories to exclude from listing
$excludedItems = [
    '@eaDir',
    '#recycle',
    '.DS_Store',
    'Desktop DB',
    'Desktop DF'
];
?>