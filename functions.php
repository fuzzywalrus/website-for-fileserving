<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

/**
 * Securely decodes URL parameters to prevent double encoding attacks
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

/**
 * Sanitizes a file path to prevent directory traversal
 * 
 * @param string $path Path to sanitize
 * @param string $baseDir Base directory to ensure paths stay within
 * @return string|false Sanitized path or false if invalid
 */
function sanitizePath($path, $baseDir) {
    // Fully decode the path first to handle any encoding tricks
    $path = secureUrlDecode($path);
    
    // Remove any directory traversal sequences
    $path = str_replace(['../', '..\\', './', '.\\'], '', $path);
    
    // Remove multiple slashes
    $path = preg_replace('#/+#', '/', $path);
    $path = preg_replace('#\\\\+#', '\\', $path);
    
    // Remove leading slashes
    $path = ltrim($path, '/\\');
    
    // Construct the full path
    $fullPath = $baseDir . '/' . $path;
    
    // Get real paths to compare
    $realBasePath = realpath($baseDir);
    $realPath = realpath($fullPath);
    
    // If realpath fails, the path doesn't exist or contains invalid characters
    if ($realPath === false) {
        // Check if the directory simply doesn't exist yet but would be valid
        $parentDir = dirname($fullPath);
        $realParentPath = realpath($parentDir);
        
        if ($realParentPath === false || strpos($realParentPath, $realBasePath) !== 0) {
            return false;
        }
        
        // The path is valid but doesn't exist yet
        return $fullPath;
    }
    
    // Check if the path is inside the base directory
    if (strpos($realPath, $realBasePath) !== 0) {
        return false;
    }
    
    return $fullPath;
}

/**
 * Validates if a filename matches a whitelist of allowed characters
 * 
 * @param string $filename The filename to validate
 * @return bool Whether the filename is valid
 */
function isValidFilename($filename) {
    // Allow alphanumeric, spaces, and some special characters
    return preg_match('/^[a-zA-Z0-9_\-\. ()]+$/', $filename);
}

// Function to get current directory from URL
function getCurrentDirectory($baseDir) {
    $currentDir = $baseDir;

    // If a path is specified in the URL, use that instead (with security checks)
    if (isset($_GET['path'])) {
        $secureFullPath = sanitizePath($_GET['path'], $baseDir);
        
        if ($secureFullPath !== false) {
            $currentDir = $secureFullPath;
        }
    }
    
    return $currentDir;
}

// Function to get parent directory
function getParentDirectory($currentDir, $baseDir) {
    $parentDir = dirname($currentDir);
    
    // Get real paths for comparison
    $realBasePath = realpath($baseDir);
    $realParentPath = realpath($parentDir);
    
    // Ensure parent directory is still within base directory
    if ($realParentPath === false || strpos($realParentPath, $realBasePath) !== 0) {
        $parentDir = $baseDir;
    }
    
    return $parentDir;
}

// Function to get breadcrumbs
function getBreadcrumbs($currentDir, $baseDir) {
    $breadcrumbs = array();
    $breadcrumbs[] = ['name' => 'Home', 'path' => $baseDir];

    // Sanitize and get the relative path
    $realBasePath = realpath($baseDir);
    $realCurrentPath = realpath($currentDir);
    
    // If the current path is invalid, just return home
    if ($realCurrentPath === false || strpos($realCurrentPath, $realBasePath) !== 0) {
        return $breadcrumbs;
    }
    
    $relativePath = str_replace($realBasePath, '', $realCurrentPath);
    $relativePath = ltrim($relativePath, '/\\');
    
    if (!empty($relativePath)) {
        $parts = preg_split('/[\/\\\\]/', $relativePath);
        $path = $baseDir;
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $path .= '/' . $part;
            
            // Validate the path again to ensure no manipulation
            $realPath = realpath($path);
            if ($realPath === false || strpos($realPath, $realBasePath) !== 0) {
                continue;
            }
            
            $breadcrumbs[] = ['name' => htmlspecialchars($part), 'path' => $path];
        }
    }
    
    return $breadcrumbs;
}

// Function to get directory contents
function getDirectoryContents($currentDir) {
    global $excludedItems;
    
    $dirContents = array();
    
    // Validate the directory path
    if (!is_dir($currentDir)) {
        return $dirContents;
    }
    
    $realPath = realpath($currentDir);
    if ($realPath === false) {
        return $dirContents;
    }
    
    if ($handle = opendir($currentDir)) {
        while (false !== ($entry = readdir($handle))) {
            // Skip . and .. directories
            if ($entry == "." || $entry == "..") {
                continue;
            }
            
            // Skip excluded items and common system directories
            if (in_array($entry, $excludedItems) || 
                strpos($entry, '@eaDir') !== false || 
                strpos($entry, '#recycle') !== false) {
                continue;
            }
            
            // Validate filename
            if (!isValidFilename($entry)) {
                continue;
            }
            
            $fullPath = $currentDir . '/' . $entry;
            
            // Double-check the full path is valid
            $realFullPath = realpath($fullPath);
            if ($realFullPath === false) {
                continue;
            }
            
            // Get file size safely
            $fileSize = '-';
            if (is_file($fullPath) && is_readable($fullPath)) {
                try {
                    $fileSize = formatFileSize(filesize($fullPath));
                } catch (Exception $e) {
                    $fileSize = 'Unknown';
                }
            }
            
            // Get file modification time safely
            $modifiedTime = 'Unknown';
            try {
                $modifiedTime = date("Y-m-d H:i:s", filemtime($fullPath));
            } catch (Exception $e) {
                // Use default value
            }
            
            $dirContents[] = [
                'name' => htmlspecialchars($entry),
                'path' => $fullPath,
                'is_dir' => is_dir($fullPath),
                'size' => $fileSize,
                'modified' => $modifiedTime,
                'type' => is_dir($fullPath) ? 'directory' : getFileIcon($entry)
            ];
        }
        
        closedir($handle);
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
    global $excludedItems, $baseDir;
    
    // Security checks
    if (!is_dir($directory)) {
        return $results;
    }
    
    $realBasePath = realpath($baseDir);
    $realDirectory = realpath($directory);
    
    if ($realDirectory === false || strpos($realDirectory, $realBasePath) !== 0) {
        return $results;
    }
    
    // Limit search depth to prevent excessive resource usage
    $depth = substr_count($relativePath, '/');
    if ($depth > 10) { // Limit to 10 levels deep
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
            
            // Validate filename
            if (!isValidFilename($entry)) {
                continue;
            }
            
            $fullPath = $directory . '/' . $entry;
            
            // Double-check the full path is valid
            $realFullPath = realpath($fullPath);
            if ($realFullPath === false || strpos($realFullPath, $realBasePath) !== 0) {
                continue;
            }
            
            $entryRelativePath = $relativePath ? $relativePath . '/' . $entry : $entry;
            
            // Check if name contains search term
            if (stripos($entry, $searchTerm) !== false) {
                // Get file size safely
                $fileSize = '-';
                if (is_file($fullPath) && is_readable($fullPath)) {
                    try {
                        $fileSize = formatFileSize(filesize($fullPath));
                    } catch (Exception $e) {
                        $fileSize = 'Unknown';
                    }
                }
                
                // Get file modification time safely
                $modifiedTime = 'Unknown';
                try {
                    $modifiedTime = date("Y-m-d H:i:s", filemtime($fullPath));
                } catch (Exception $e) {
                    // Use default value
                }
                
                $results[] = [
                    'name' => htmlspecialchars($entry),
                    'path' => $fullPath,
                    'relative_path' => $entryRelativePath,
                    'is_dir' => is_dir($fullPath),
                    'size' => $fileSize,
                    'modified' => $modifiedTime,
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
    if (!is_numeric($bytes) || $bytes < 0) {
        return "Unknown";
    }
    
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
        'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'html', 'htm'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz']
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
        'html', 'htm', 'txt'
    ];
    
    return in_array($extension, $playable);
}

/**
 * Generate an encrypted ID for a file path
 */
function encryptPath($path) {
    global $encryptionKey;
    
    // Sanitize the path before encryption
    $path = filter_var($path, FILTER_SANITIZE_STRING);
    
    // Create a unique hash from the path and key
    $hash = hash_hmac('sha256', $path . time(), $encryptionKey);
    $shortHash = substr($hash, 0, 32); // Use first 32 chars for brevity
    
    // Store the hash-to-path mapping in the session
    if (!isset($_SESSION['path_hashes'])) {
        $_SESSION['path_hashes'] = array();
    }
    
    // Store the mapping with an expiration time (24 hours)
    $_SESSION['path_hashes'][$shortHash] = [
        'path' => $path,
        'expires' => time() + 86400 // 24 hours
    ];
    
    // Clean up expired hashes
    cleanExpiredHashes();
    
    // Return the hash as the file ID
    return $shortHash;
}

/**
 * Decrypt a file path from its hashed ID
 */
function decryptPath($hash) {
    // Security: Validate the hash format
    if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
        return false;
    }
    
    // Check if we have this hash stored
    if (isset($_SESSION['path_hashes'][$hash])) {
        $stored = $_SESSION['path_hashes'][$hash];
        
        // Check if the hash has expired
        if ($stored['expires'] < time()) {
            unset($_SESSION['path_hashes'][$hash]);
            return false;
        }
        
        return $stored['path'];
    }
    
    return false;
}

/**
 * Clean up expired hashes from the session
 */
function cleanExpiredHashes() {
    if (!isset($_SESSION['path_hashes'])) {
        return;
    }
    
    $now = time();
    foreach ($_SESSION['path_hashes'] as $hash => $stored) {
        if ($stored['expires'] < $now) {
            unset($_SESSION['path_hashes'][$hash]);
        }
    }
}