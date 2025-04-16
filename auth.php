<?php
// Security: Prevent direct file access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Start the session for password protection
session_start();

// Check authentication status
$isAuthenticated = false;
$isSecondaryUser = false;

// Check if user is already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $isAuthenticated = true;
    
    // Check if they're using the secondary password
    if (isset($_SESSION['is_secondary_user']) && $_SESSION['is_secondary_user'] === true) {
        $isSecondaryUser = true;
    }
}

// Handle login form submission
if (isset($_POST['password'])) {
    $submittedPassword = $_POST['password'];
    
    // Check against primary password
    if ($submittedPassword === $password) {
        $_SESSION['authenticated'] = true;
        $_SESSION['is_secondary_user'] = false;
        $isAuthenticated = true;
        $isSecondaryUser = false;
    } 
    // Check against secondary password if configured
    elseif ($hasSecondaryPassword && $submittedPassword === $secondaryPassword) {
        $_SESSION['authenticated'] = true;
        $_SESSION['is_secondary_user'] = true;
        $isAuthenticated = true;
        $isSecondaryUser = true;
    } 
    // Invalid password
    else {
        $loginError = "Incorrect password. Please try again.";
    }
    
    // Redirect to clear POST data
    if ($isAuthenticated && !isset($loginError)) {
        // Preserve any redirect URL if it exists
        $redirectUrl = isset($_SESSION['redirect_after_login']) ? 
            $_SESSION['redirect_after_login'] : 'index.php';
        unset($_SESSION['redirect_after_login']);
        
        header('Location: ' . $redirectUrl);
        exit;
    }
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
                width: 350px;
                border: none;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
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
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                    </div>
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