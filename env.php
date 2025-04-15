<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file into $_ENV and optionally into getenv()
 */

// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

/**
 * Load environment variables from .env file
 * 
 * @param string $path Path to the .env file
 * @param bool $overwrite Whether to overwrite existing environment variables
 * @param bool $putenv Whether to use putenv() function
 * @return bool True if the file was loaded, false otherwise
 */
function loadEnv($path = null, $overwrite = false, $putenv = true) {
    // Default to .env in the same directory
    if ($path === null) {
        $path = dirname(__FILE__) . '/.env';
    }
    
    // Check if file exists and is readable
    if (!is_readable($path)) {
        // Failed to load file
        error_log("Error: Could not read .env file at {$path}");
        return false;
    }
    
    // Read the file
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Split on first equals sign
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        // Remove surrounding quotes if they exist
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }
        
        // Skip if already set and not overwriting
        if (!$overwrite && (isset($_ENV[$name]) || getenv($name) !== false)) {
            continue;
        }
        
        // Set in $_ENV
        $_ENV[$name] = $value;
        
        // Set using putenv if requested
        if ($putenv) {
            putenv("{$name}={$value}");
        }
    }
    
    return true;
}

// Load environment variables from .env file
loadEnv();