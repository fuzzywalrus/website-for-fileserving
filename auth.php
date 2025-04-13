<?php
// Start the session for password protection
session_start();

// Check authentication status
$isAuthenticated = false;

// Check if user is already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $isAuthenticated = true;
}

// Handle login form submission
if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $isAuthenticated = true;
    } else {
        $loginError = "Incorrect password. Please try again.";
    }
}

// Function to display the login form
function displayLoginForm($error = null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Plex Directory Browser - Login</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background-color: #f8f9fa;
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
            .card-header {
                background-color: #f8f9fa;
                border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card login-card">
                        <div class="card-header text-center py-3">
                            <h4 class="mb-0"><i class="fas fa-server me-2"></i>A mysterious webpage!</h4>
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