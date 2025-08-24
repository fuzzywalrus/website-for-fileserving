<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Set secure session parameters before starting session
require_once 'functions.php';
setSecureSessionParams();

// Start the session for password protection
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    // Clear remember me cookie if it exists
    clearRememberCookie();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check authentication status
$isAuthenticated = false;
$isSecondaryUser = false;

// Check if user is already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // Check session timeout - extended sessions last longer
    $sessionTimeout = isset($_SESSION['extended_session']) && $_SESSION['extended_session'] ? 2592000 : 86400; // 30 days vs 24 hours
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
        // Session expired
        clearRememberCookie();
        session_destroy();
        $loginError = "Your session has expired. Please log in again.";
    } else {
        // Check if session IP matches (optional security check)
        $currentIP = getClientIP();
        if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $currentIP) {
            // IP changed - could be session hijacking
            error_log("Session IP mismatch for user. Original: {$_SESSION['login_ip']}, Current: {$currentIP}");
            clearRememberCookie();
            session_destroy();
            $loginError = "Security error: Your session has been terminated. Please log in again.";
        } else {
            $isAuthenticated = true;
            
            // Check if they're using the secondary password
            if (isset($_SESSION['is_secondary_user']) && $_SESSION['is_secondary_user'] === true) {
                $isSecondaryUser = true;
            }
            
            // Update last activity time
            $_SESSION['last_activity'] = time();
        }
    }
} else {
    // Check for remember me cookie if not authenticated via session
    $rememberData = checkRememberCookie();
    if ($rememberData && $rememberData['valid']) {
        // Auto-login from remember me cookie
        $isAuthenticated = true;
        $isSecondaryUser = $rememberData['is_secondary'];
        
        // Set up session
        $_SESSION['authenticated'] = true;
        $_SESSION['is_secondary_user'] = $isSecondaryUser;
        $_SESSION['login_time'] = time();
        $_SESSION['login_ip'] = getClientIP();
        $_SESSION['extended_session'] = true; // Mark as extended session
        $_SESSION['remember_token'] = $rememberData['token'];
        
        // Log the auto-login
        $userType = $isSecondaryUser ? 'secondary' : 'primary';
        error_log("Auto-login via remember me cookie from IP: " . getClientIP() . " (User type: {$userType})");
    }
}

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle login form submission
if (isset($_POST['password'])) {
    // Validate CSRF token
    $csrfToken = validateCSRFToken($_POST['csrf_token'] ?? '');
    if (!$csrfToken || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $loginError = "Security error: Invalid form submission. Please try again.";
        error_log("CSRF token mismatch from IP: " . getClientIP());
    } else {
        // Validate password input
        $submittedPassword = validatePassword($_POST['password'] ?? '');
        if ($submittedPassword === false) {
            $loginError = "Invalid input: Password format is invalid.";
            error_log("Invalid password input from IP: " . getClientIP());
        } else {
    $clientIP = getClientIP();
    
    // Check rate limiting before processing login
    $rateLimitStatus = checkRateLimit($clientIP);
    
    if ($rateLimitStatus['blocked']) {
        $minutes = ceil($rateLimitStatus['remaining_time'] / 60);
        $attempts = $rateLimitStatus['attempts'];
        
        $loginError = "Too many failed login attempts. Please wait {$minutes} minute(s) before trying again. (Attempt {$attempts})";
        
        // Log the blocked attempt
        error_log("Blocked login attempt from IP: {$clientIP} (Attempt {$attempts}, {$rateLimitStatus['remaining_time']} seconds remaining)");
    } else {
        // Process login attempt
        $loginSuccess = false;
        
        // Check against primary password with secure comparison
        if (securePasswordCompare($submittedPassword, $password)) {
            $loginSuccess = true;
            $isSecondaryUser = false;
        } 
        // Check against secondary password if configured
        elseif ($hasSecondaryPassword && securePasswordCompare($submittedPassword, $secondaryPassword)) {
            $loginSuccess = true;
            $isSecondaryUser = true;
        }
        
        if ($loginSuccess) {
            // Successful login
            clearFailedAttempts($clientIP);
            regenerateSession();
            
            // Check if user wants to be remembered
            $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
            
            $_SESSION['authenticated'] = true;
            $_SESSION['is_secondary_user'] = $isSecondaryUser;
            $_SESSION['login_time'] = time();
            $_SESSION['login_ip'] = $clientIP;
            $_SESSION['extended_session'] = $rememberMe;
            
            // Set remember me cookie if requested
            if ($rememberMe) {
                $rememberToken = generateRememberToken();
                setRememberCookie($rememberToken, $isSecondaryUser);
                $_SESSION['remember_token'] = $rememberToken;
                
                // Update session parameters for extended session
                setSecureSessionParams(true);
            }
            
            $isAuthenticated = true;
            
            // Log successful login
            $userType = $isSecondaryUser ? 'secondary' : 'primary';
            $rememberStatus = $rememberMe ? ' (with remember me)' : '';
            error_log("Successful login from IP: {$clientIP} (User type: {$userType}){$rememberStatus}");
            
            // Redirect to clear POST data
            $redirectUrl = isset($_SESSION['redirect_after_login']) ? 
                $_SESSION['redirect_after_login'] : 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            // Failed login
            $failedAttemptStatus = recordFailedAttempt($clientIP);
            
            if ($failedAttemptStatus['blocked']) {
                $minutes = ceil($failedAttemptStatus['remaining_time'] / 60);
                $loginError = "Incorrect password. Account temporarily locked for {$minutes} minute(s) due to multiple failed attempts.";
            } else {
                $remaining = 4 - $failedAttemptStatus['attempts'];
                if ($remaining > 0) {
                    $loginError = "Incorrect password. {$remaining} attempt(s) remaining before temporary lockout.";
                } else {
                    $loginError = "Incorrect password. Please try again.";
                }
            }
            
            // Log failed attempt
            error_log("Failed login attempt from IP: {$clientIP} (Attempt {$failedAttemptStatus['attempts']})");
            
            // Add a small delay to slow down brute force attacks
            usleep(rand(100000, 500000)); // 0.1-0.5 second random delay
        }
    }
        } // Close password validation else block
    } // Close the CSRF check else block
}

// After authentication, set the appropriate base directory, title, and stats
if ($isAuthenticated && $isSecondaryUser) {
    // For secondary users, use the secondary directory, title, and stats
    $baseDir = $secondaryBaseDir;
    $siteTitle = $secondaryTitle;
    $siteStats = $secondaryStats;
}

// Function to display the login form
function displayLoginForm($error = null) {
    global $siteTitle;
    
    // Add security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Content-Security-Policy: default-src \'self\' https://cdnjs.cloudflare.com; style-src \'self\' https://cdnjs.cloudflare.com \'unsafe-inline\'; script-src \'self\' https://cdnjs.cloudflare.com; font-src \'self\' https://cdnjs.cloudflare.com; img-src \'self\' data:');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="color-scheme" content="light dark">
        <title><?= htmlspecialchars($siteTitle) ?> - Login</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="styles.css">
        <style>
            body {
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                border: none;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5">
                    <div class="card login-card">
                        <div class="card-header text-center py-3">
                            <h4 class="mb-0"><i class="fas fa-server me-2"></i><?= htmlspecialchars($siteTitle) ?></h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" maxlength="1000" required>
                                    </div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1">
                                    <label class="form-check-label" for="remember_me">
                                        <i class="fas fa-clock me-1"></i> Remember me for 30 days
                                    </label>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center text-muted py-3">
                            Secure Access Required
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>