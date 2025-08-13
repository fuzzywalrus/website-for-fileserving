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

// Get the requested file path from the URL
$requestedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If a direct file is requested (not through our app), check if it's allowed
if ($requestedPath !== '/' && $requestedPath !== '/index.php') {
    // Get the absolute path of the requested file
    $requestedFilePath = realpath($_SERVER['DOCUMENT_ROOT'] . $requestedPath);
    
    // Check if this is a valid file within our base directory
    if ($requestedFilePath && is_file($requestedFilePath)) {
        $realBasePath = realpath($baseDir);
        // If the file is not in our base directory, deny access
        if (strpos($requestedFilePath, $realBasePath) !== 0) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access Denied');
        }
        
        // If we get here, the file is allowed - let the web server handle it normally
        return;
    }
}

// Check if this is a search request
$isSearchRequest = isset($_GET['search']) && !empty($_GET['search']);
$searchResults = [];
$searchTerm = '';

if ($isSearchRequest) {
    $searchTerm = validateSearchQuery(trim($_GET['search']));
    if ($searchTerm === false) {
        // Invalid search query - treat as no search
        $isSearchRequest = false;
        $searchTerm = '';
        error_log("Invalid search query from IP: " . getClientIP());
    } else {
        $searchResults = searchFiles($baseDir, $searchTerm);
    }
    
    // Get current directory and file listings for breadcrumb navigation
    $currentDir = $baseDir;
    $parentDir = $baseDir;
    $breadcrumbs = [['name' => 'Home', 'path' => $baseDir]];
    $dirContents = $searchResults; // Use search results instead of directory contents
} else {
    // Normal directory browsing - validate path parameter
    if (isset($_GET['path'])) {
        $pathInput = validateFilePath($_GET['path']);
        if ($pathInput === false) {
            // Invalid path - redirect to base directory
            error_log("Invalid path parameter from IP: " . getClientIP());
            header('Location: index.php');
            exit;
        }
        $_GET['path'] = $pathInput;
    }
    
    $currentDir = getCurrentDirectory($baseDir);
    $parentDir = getParentDirectory($currentDir, $baseDir);
    $breadcrumbs = getBreadcrumbs($currentDir, $baseDir);
    $dirContents = getDirectoryContents($currentDir);
}

// Include the main view
require_once 'view.php';
?>