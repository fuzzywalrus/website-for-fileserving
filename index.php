<?php
// Include configuration file
require_once 'config.php';

// Include authentication file
require_once 'auth.php';

// Include file functions
require_once 'functions.php';

// If not authenticated, show login form and exit
if (!$isAuthenticated) {
    displayLoginForm(isset($loginError) ? $loginError : null);
    exit;
}

// Check if this is a search request
$isSearchRequest = isset($_GET['search']) && !empty($_GET['search']);
$searchResults = [];
$searchTerm = '';

if ($isSearchRequest) {
    $searchTerm = trim($_GET['search']);
    $searchResults = searchFiles($baseDir, $searchTerm);
    
    // Get current directory and file listings for breadcrumb navigation
    $currentDir = $baseDir;
    $parentDir = $baseDir;
    $breadcrumbs = [['name' => 'Home', 'path' => $baseDir]];
    $dirContents = $searchResults; // Use search results instead of directory contents
} else {
    // Normal directory browsing
    $currentDir = getCurrentDirectory($baseDir);
    $parentDir = getParentDirectory($currentDir, $baseDir);
    $breadcrumbs = getBreadcrumbs($currentDir, $baseDir);
    $dirContents = getDirectoryContents($currentDir);
}

// Include the main view
require_once 'view.php';
?>