<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Function to get current directory from URL
function getCurrentDirectory($baseDir) {
    $currentDir = $baseDir;

    // If a path is specified in the URL, use that instead (with security checks)
    if (isset($_GET['path'])) {
        $requestedPath = $_GET['path'];
        
        // Security checks to prevent directory traversal
        $requestedPath = str_replace('../', '', $requestedPath);
        $requestedPath = str_replace('..\\', '', $requestedPath);
        $requestedPath = ltrim($requestedPath, '/');
        
        $fullPath = $baseDir . '/' . $requestedPath;
        
        // Check if the requested path is valid and within the base directory
        $realBasePath = realpath($baseDir);
        $realRequestedPath = realpath($fullPath);
        
        if ($realRequestedPath !== false && 
            strpos($realRequestedPath, $realBasePath) === 0) {
            $currentDir = $fullPath;
        }
    }
    
    return $currentDir;
}

// Function to get parent directory
function getParentDirectory($currentDir, $baseDir) {
    $parentDir = dirname($currentDir);
    if (strpos($parentDir, $baseDir) !== 0 && $parentDir !== dirname($baseDir)) {
        $parentDir = $baseDir;
    }
    return $parentDir;
}

// Function to get breadcrumbs
function getBreadcrumbs($currentDir, $baseDir) {
    $breadcrumbs = array();
    $breadcrumbs[] = ['name' => 'Home', 'path' => $baseDir];

    $relativePath = str_replace($baseDir, '', $currentDir);
    $relativePath = ltrim($relativePath, '/');
    
    if (!empty($relativePath)) {
        $parts = explode('/', $relativePath);
        $path = $baseDir;
        foreach ($parts as $part) {
            $path .= '/' . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $path];
        }
    }
    
    return $breadcrumbs;
}

// Function to get directory contents
function getDirectoryContents($currentDir) {
    global $excludedItems;
    
    $dirContents = array();
    if (is_dir($currentDir)) {
        if ($handle = opendir($currentDir)) {
            while (false !== ($entry = readdir($handle))) {
                // Skip . and .. directories
                if ($entry == "." || $entry == "..") {
                    continue;
                }
                
                // Skip excluded items
                if (in_array($entry, $excludedItems) || 
                    strpos($entry, '@eaDir') !== false || 
                    strpos($entry, '#recycle') !== false) {
                    continue;
                }
                
                $fullPath = $currentDir . '/' . $entry;
                $dirContents[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? formatFileSize(filesize($fullPath)) : '-',
                    'modified' => date("Y-m-d H:i:s", filemtime($fullPath)),
                    'type' => is_dir($fullPath) ? 'directory' : getFileIcon($entry)
                ];
            }
            closedir($handle);
        }
    }
    
    // Sort items: directories first, then files alphabetically
    usort($dirContents, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) {
            return -1;
        } else if (!$a['is_dir'] && $b['is_dir']) {
            return 1;
        } else {
            return strcasecmp($a['name'], $b['name']);
        }
    });
    
    return $dirContents;
}

// Function to search for files recursively
function searchFiles($directory, $searchTerm, &$results = array(), $relativePath = '') {
    global $excludedItems;
    
    if (!is_dir($directory)) {
        return $results;
    }
    
    if ($handle = opendir($directory)) {
        while (false !== ($entry = readdir($handle))) {
            // Skip . and .. directories
            if ($entry == "." || $entry == "..") {
                continue;
            }
            
            // Skip excluded items
            if (in_array($entry, $excludedItems) || 
                strpos($entry, '@eaDir') !== false || 
                strpos($entry, '#recycle') !== false) {
                continue;
            }
            
            $fullPath = $directory . '/' . $entry;
            $entryRelativePath = $relativePath ? $relativePath . '/' . $entry : $entry;
            
            // Check if name contains search term
            if (stripos($entry, $searchTerm) !== false) {
                $results[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'relative_path' => $entryRelativePath,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? formatFileSize(filesize($fullPath)) : '-',
                    'modified' => date("Y-m-d H:i:s", filemtime($fullPath)),
                    'type' => is_dir($fullPath) ? 'directory' : getFileIcon($entry)
                ];
            }
            
            // If this is a directory, search inside it
            if (is_dir($fullPath)) {
                searchFiles($fullPath, $searchTerm, $results, $entryRelativePath);
            }
        }
        closedir($handle);
    }
    
    return $results;
}

// Function to get file size in human-readable format
function formatFileSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Function to get file type icon
function getFileIcon($file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    $icons = [
        'video' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v'],
        'audio' => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'document' => ['pdf', 'doc', 'lit', 'nfo', 'docx', 'txt', 'rtf', 'odt', 'html', 'htm'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
        'diskimage' => ['iso', 'img', 'dmg', 'vhd', 'vmdk', 'qcow2'], // New category for disk image files
        'rom' => [
            // Nintendo
            'nes', 'snes', 'n64', 'z64', 'nsp', 'xci', 'gba', 'gbc', 'gb', '3ds', 'nds', 'wbfs', 'gcm', 'cxi', 'cia',
            // Sony
            'cso', 'bin', 'mdf', 'pbp', 'chd',
            // Sega
            'md', 'smd', 'gen', 'sms', 'gg', 'sg', '32x', 'cdi',
            // Atari
            'a26', 'a52', 'a78', 'j64',
            // Other systems
            'rom', 'ic1', 'nvm', 'mec', 'erom', 'v64'
        ]
    ];
    
    foreach ($icons as $type => $extensions) {
        if (in_array($extension, $extensions)) {
            return $type;
        }
    }
    
    return 'file';
}

// Function to check if file is playable/viewable
function isPlayable($file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $playable = [
        'mp4', 'webm', 'ogg', 'mp3', 'wav', 
        'jpg', 'jpeg', 'png', 'gif', 'pdf',
        'html', 'htm', 'txt'  // Added HTML and TXT files
    ];
    
    return in_array($extension, $playable);
}

/**
 * Generate an encrypted ID for a file path
 */
function encryptPath($path) {
    global $encryptionKey;
    
    // Create a simple hash from the path and key
    $hash = md5($encryptionKey . $path . time());
    
    // Store the hash-to-path mapping in the session
    if (!isset($_SESSION['path_hashes'])) {
        $_SESSION['path_hashes'] = array();
    }
    
    // Store the mapping
    $_SESSION['path_hashes'][$hash] = $path;
    
    // Return the hash as the file ID
    return $hash;
}

/**
 * Decrypt a file path from its hashed ID
 */
function decryptPath($hash) {
    // Check if we have this hash stored
    if (isset($_SESSION['path_hashes'][$hash])) {
        return $_SESSION['path_hashes'][$hash];
    }
    
    return false;
}