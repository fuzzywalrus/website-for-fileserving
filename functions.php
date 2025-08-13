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
 * Securely encrypt a file path with session binding and TTL using AES-256-GCM
 * 
 * @param string $path The file path to encrypt
 * @param int $ttlMinutes TTL in minutes (default: 24 hours)
 * @return string|false Base64 encoded encrypted data or false on failure
 */
function encryptPath($path, $ttlMinutes = 1440) { // 24 hours default
    global $encryptionKey;
    
    try {
        // Create payload with path, session ID, user info, and expiry
        $sessionId = session_id();
        $userId = $_SESSION['is_secondary_user'] ?? false ? 'secondary' : 'primary';
        $expiry = time() + ($ttlMinutes * 60);
        
        $payload = json_encode([
            'path' => $path,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'expiry' => $expiry,
            'created' => time(),
            'ip' => getClientIP()
        ]);
        
        // Ensure we have a proper 32-byte key for AES-256
        $key = hash('sha256', $encryptionKey, true);
        
        // Generate a random 12-byte nonce (96 bits - recommended for GCM)
        $nonce = random_bytes(12);
        
        // Use additional authenticated data (AAD) for extra security
        $aad = 'file_access_token';
        
        // Encrypt the payload using AES-256-GCM
        $encrypted = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        
        if ($encrypted === false) {
            error_log('Encryption failed for path: ' . $path);
            return false;
        }
        
        // Combine nonce + tag + encrypted data and encode as base64url (URL-safe)
        $combined = $nonce . $tag . $encrypted;
        return base64url_encode($combined);
        
    } catch (Exception $e) {
        error_log('Encryption error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Securely decrypt a file path with session binding and TTL validation using AES-256-GCM
 * 
 * @param string $encryptedData Base64 encoded encrypted data
 * @return string|false The decrypted file path or false on failure
 */
function decryptPath($encryptedData) {
    global $encryptionKey;
    
    try {
        // Decode from base64url
        $combined = base64url_decode($encryptedData);
        if ($combined === false || strlen($combined) < 28) { // 12 + 16 = minimum size
            return false;
        }
        
        // Extract components: nonce (12 bytes) + tag (16 bytes) + encrypted data
        $nonce = substr($combined, 0, 12);
        $tag = substr($combined, 12, 16);
        $encrypted = substr($combined, 28);
        
        // Ensure we have the same 32-byte key
        $key = hash('sha256', $encryptionKey, true);
        
        // Use the same additional authenticated data (AAD)
        $aad = 'file_access_token';
        
        // Decrypt using AES-256-GCM
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        
        if ($decrypted === false) {
            error_log('Decryption failed for encrypted data');
            return false;
        }
        
        // Parse the JSON payload
        $payload = json_decode($decrypted, true);
        if (!$payload || !isset($payload['path'], $payload['session_id'], $payload['user_id'], $payload['expiry'])) {
            error_log('Invalid payload structure in decrypted data');
            return false;
        }
        
        // Check expiry
        if (time() > $payload['expiry']) {
            error_log('File access token expired (created: ' . date('Y-m-d H:i:s', $payload['created']) . ')');
            return false;
        }
        
        // Validate session ID
        if ($payload['session_id'] !== session_id()) {
            error_log('Session ID mismatch in file access token');
            return false;
        }
        
        // Validate user type
        $currentUserId = $_SESSION['is_secondary_user'] ?? false ? 'secondary' : 'primary';
        if ($payload['user_id'] !== $currentUserId) {
            error_log('User ID mismatch in file access token');
            return false;
        }
        
        // Optional: Validate IP (can be disabled if too strict)
        $currentIP = getClientIP();
        if (isset($payload['ip']) && $payload['ip'] !== $currentIP) {
            error_log("IP mismatch in file access token. Original: {$payload['ip']}, Current: {$currentIP}");
            // Don't fail on IP mismatch for better user experience, just log it
            // return false;
        }
        
        return $payload['path'];
        
    } catch (Exception $e) {
        error_log('Decryption error: ' . $e->getMessage());
        return false;
    }
}

/**
 * URL-safe base64 encoding
 * 
 * @param string $data Data to encode
 * @return string URL-safe base64 encoded string
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * URL-safe base64 decoding
 * 
 * @param string $data URL-safe base64 encoded string
 * @return string|false Decoded data or false on failure
 */
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

/**
 * Security functions for authentication and rate limiting
 */

/**
 * Get the client's IP address, handling proxies and load balancers
 * 
 * @return string The client's IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if an IP address is currently rate limited
 * 
 * @param string $ip The IP address to check
 * @return array Array with 'blocked' status and 'remaining_time' in seconds
 */
function checkRateLimit($ip) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $currentTime = time();
    $rateLimits = &$_SESSION['rate_limits'];
    
    // Clean up expired entries
    foreach ($rateLimits as $limitedIp => $data) {
        if ($currentTime > $data['unblock_time']) {
            unset($rateLimits[$limitedIp]);
        }
    }
    
    // Check if IP is currently blocked
    if (isset($rateLimits[$ip])) {
        $remainingTime = $rateLimits[$ip]['unblock_time'] - $currentTime;
        if ($remainingTime > 0) {
            return [
                'blocked' => true,
                'remaining_time' => $remainingTime,
                'attempts' => $rateLimits[$ip]['attempts']
            ];
        } else {
            unset($rateLimits[$ip]);
        }
    }
    
    return ['blocked' => false, 'remaining_time' => 0, 'attempts' => 0];
}

/**
 * Record a failed login attempt and apply rate limiting
 * 
 * @param string $ip The IP address that failed authentication
 * @return array Updated rate limit status
 */
function recordFailedAttempt($ip) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $currentTime = time();
    $rateLimits = &$_SESSION['rate_limits'];
    
    // Initialize or update the attempt counter
    if (!isset($rateLimits[$ip])) {
        $rateLimits[$ip] = [
            'attempts' => 1,
            'first_attempt' => $currentTime,
            'unblock_time' => 0
        ];
    } else {
        $rateLimits[$ip]['attempts']++;
    }
    
    $attempts = $rateLimits[$ip]['attempts'];
    
    // Allow 4 attempts in 30 seconds, then progressive blocking: 30s, 2min, 5min, 15min, 1hr, 24hr
    $blockDurations = [0, 0, 0, 0, 30, 120, 300, 900, 3600, 86400];
    $blockIndex = min($attempts - 1, count($blockDurations) - 1);
    $blockDuration = $blockDurations[$blockIndex];
    
    if ($blockDuration > 0) {
        $rateLimits[$ip]['unblock_time'] = $currentTime + $blockDuration;
    }
    
    return [
        'blocked' => $blockDuration > 0,
        'remaining_time' => $blockDuration,
        'attempts' => $attempts
    ];
}

/**
 * Clear failed attempts for an IP (called on successful login)
 * 
 * @param string $ip The IP address to clear
 */
function clearFailedAttempts($ip) {
    if (isset($_SESSION['rate_limits'][$ip])) {
        unset($_SESSION['rate_limits'][$ip]);
    }
}

/**
 * Securely hash a password using bcrypt
 * 
 * @param string $password The password to hash
 * @return string The hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash with timing attack protection
 * 
 * @param string $password The password to verify
 * @param string $hash The hash to verify against
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Secure password comparison with constant-time comparison for plain text passwords
 * This maintains backward compatibility while being more secure than ===
 * 
 * @param string $submitted The submitted password
 * @param string $stored The stored password
 * @return bool True if passwords match
 */
function securePasswordCompare($submitted, $stored) {
    // If stored password looks like a hash, use password_verify
    if (strlen($stored) >= 60 && (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$2x$') === 0)) {
        return password_verify($submitted, $stored);
    }
    
    // For plain text passwords, use hash_equals for constant-time comparison
    return hash_equals($stored, $submitted);
}

/**
 * Regenerate session ID for security
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Set secure session parameters
 */
function setSecureSessionParams($extended = false) {
    // Set secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Strict');
    
    // Set session timeout based on remember me preference
    $sessionTimeout = $extended ? 2592000 : 86400; // 30 days vs 24 hours
    ini_set('session.gc_maxlifetime', $sessionTimeout);
    ini_set('session.cookie_lifetime', $sessionTimeout);
}

/**
 * Generate a secure remember me token
 * 
 * @return string A cryptographically secure token
 */
function generateRememberToken() {
    return bin2hex(random_bytes(32)); // 64-character hex string
}

/**
 * Set a persistent login cookie
 * 
 * @param string $token The remember me token
 * @param bool $isSecondaryUser Whether this is a secondary user
 * @param int $expiry Cookie expiration time (default: 30 days)
 */
function setRememberCookie($token, $isSecondaryUser = false, $expiry = null) {
    if ($expiry === null) {
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days from now
    }
    
    $cookieData = [
        'token' => $token,
        'is_secondary' => $isSecondaryUser,
        'created' => time(),
        'ip' => getClientIP()
    ];
    
    // Encrypt the cookie data for security
    $encryptedData = encryptCookieData(json_encode($cookieData));
    
    // Set cookie with secure parameters
    $secure = isset($_SERVER['HTTPS']);
    setcookie('remember_auth', $encryptedData, $expiry, '/', '', $secure, true);
    
    // Store token in session for validation
    $_SESSION['remember_tokens'][$token] = [
        'created' => time(),
        'is_secondary' => $isSecondaryUser,
        'ip' => getClientIP()
    ];
}

/**
 * Check and validate a remember me cookie
 * 
 * @return array|false Returns user data array or false if invalid
 */
function checkRememberCookie() {
    if (!isset($_COOKIE['remember_auth'])) {
        return false;
    }
    
    try {
        // Decrypt and parse cookie data
        $decryptedData = decryptCookieData($_COOKIE['remember_auth']);
        if ($decryptedData === false) {
            clearRememberCookie();
            return false;
        }
        
        $cookieData = json_decode($decryptedData, true);
        if (!$cookieData || !isset($cookieData['token'])) {
            clearRememberCookie();
            return false;
        }
        
        $token = $cookieData['token'];
        
        // Check if token exists in session storage
        if (!isset($_SESSION['remember_tokens'][$token])) {
            clearRememberCookie();
            return false;
        }
        
        $storedData = $_SESSION['remember_tokens'][$token];
        
        // Validate token age (30 days max)
        $maxAge = 30 * 24 * 60 * 60; // 30 days
        if ((time() - $storedData['created']) > $maxAge) {
            unset($_SESSION['remember_tokens'][$token]);
            clearRememberCookie();
            return false;
        }
        
        // Optional: Check if IP has changed (comment out if too strict)
        if ($storedData['ip'] !== getClientIP()) {
            error_log("Remember me token IP mismatch. Original: {$storedData['ip']}, Current: " . getClientIP());
            // Uncomment the next lines if you want strict IP checking
            // unset($_SESSION['remember_tokens'][$token]);
            // clearRememberCookie();
            // return false;
        }
        
        return [
            'valid' => true,
            'is_secondary' => $storedData['is_secondary'],
            'token' => $token
        ];
        
    } catch (Exception $e) {
        error_log("Remember cookie validation error: " . $e->getMessage());
        clearRememberCookie();
        return false;
    }
}

/**
 * Clear the remember me cookie
 */
function clearRememberCookie() {
    if (isset($_COOKIE['remember_auth'])) {
        $secure = isset($_SERVER['HTTPS']);
        setcookie('remember_auth', '', time() - 3600, '/', '', $secure, true);
        unset($_COOKIE['remember_auth']);
    }
}

/**
 * Remove a specific remember token
 * 
 * @param string $token The token to remove
 */
function removeRememberToken($token) {
    if (isset($_SESSION['remember_tokens'][$token])) {
        unset($_SESSION['remember_tokens'][$token]);
    }
}

/**
 * Encrypt cookie data using the same encryption as file paths
 * 
 * @param string $data The data to encrypt
 * @return string|false The encrypted data or false on failure
 */
function encryptCookieData($data) {
    // Reuse the existing encryption function
    return encryptPath($data);
}

/**
 * Decrypt cookie data
 * 
 * @param string $encryptedData The encrypted data
 * @return string|false The decrypted data or false on failure
 */
function decryptCookieData($encryptedData) {
    // Reuse the existing decryption function
    return decryptPath($encryptedData);
}

/**
 * Input validation functions for security
 */

/**
 * Validate input length and sanitize
 * 
 * @param string $input The input to validate
 * @param int $maxLength Maximum allowed length
 * @param string $fieldName Field name for error logging
 * @return string|false Sanitized input or false if invalid
 */
function validateInput($input, $maxLength, $fieldName = 'input') {
    if (!is_string($input)) {
        error_log("Invalid input type for field: {$fieldName}");
        return false;
    }
    
    // Check length
    if (strlen($input) > $maxLength) {
        error_log("Input too long for field: {$fieldName} (length: " . strlen($input) . ", max: {$maxLength})");
        return false;
    }
    
    // Basic sanitization - remove null bytes and control characters
    $sanitized = str_replace("\0", '', $input);
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
    
    return $sanitized;
}

/**
 * Validate password input
 * 
 * @param string $password The password to validate
 * @return string|false Validated password or false if invalid
 */
function validatePassword($password) {
    // Allow generous 1000 characters for passwords (supports long passphrases)
    return validateInput($password, 1000, 'password');
}

/**
 * Validate search query input
 * 
 * @param string $query The search query to validate
 * @return string|false Validated query or false if invalid
 */
function validateSearchQuery($query) {
    // Allow 500 characters for search queries
    return validateInput($query, 500, 'search_query');
}

/**
 * Validate file path input
 * 
 * @param string $path The file path to validate
 * @return string|false Validated path or false if invalid
 */
function validateFilePath($path) {
    // Allow 2000 characters for file paths (handles deep directory structures)
    return validateInput($path, 2000, 'file_path');
}

/**
 * Validate CSRF token input
 * 
 * @param string $token The CSRF token to validate
 * @return string|false Validated token or false if invalid
 */
function validateCSRFToken($token) {
    // CSRF tokens should be exactly 64 characters (hex)
    if (!is_string($token) || strlen($token) !== 64) {
        error_log("Invalid CSRF token length: " . strlen($token ?? ''));
        return false;
    }
    
    // Check if it's valid hex
    if (!ctype_xdigit($token)) {
        error_log("Invalid CSRF token format (not hex)");
        return false;
    }
    
    return $token;
}

/**
 * Validate encrypted ID input
 * 
 * @param string $encryptedId The encrypted ID to validate
 * @return string|false Validated ID or false if invalid
 */
function validateEncryptedId($encryptedId) {
    // Allow 2000 characters for encrypted IDs (base64url encoded)
    $validated = validateInput($encryptedId, 2000, 'encrypted_id');
    
    if ($validated === false) {
        return false;
    }
    
    // Additional validation for base64url format
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $validated)) {
        error_log("Invalid encrypted ID format (not base64url)");
        return false;
    }
    
    return $validated;
}