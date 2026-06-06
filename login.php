<?php
include 'config.php';

// If already logged in, go to appropriate dashboard
if(isset($_SESSION['user_id'])) {
    if(isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true) {
        header("Location: change_password.php");
    } elseif($_SESSION['role'] == 'worker') {
        header("Location: worker_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Basic validation
    if(empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, must_change_password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if(password_verify($password, $user['password'])) {
                // Set all session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Log audit if function exists
                if(function_exists('log_action')) {
                    log_action('LOGIN', "User logged in from " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                }

                // Check if must change password
                if($user['must_change_password'] == 1) {
                    $_SESSION['force_password_change'] = true;
                    header("Location: change_password.php");
                    exit();
                }

                // Redirect based on role
                if($user['role'] == 'worker') {
                    header("Location: worker_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "Invalid username or password";

                // Log failed login attempt
                if(function_exists('log_action')) {
                    log_action('LOGIN_FAILED', "Failed login attempt for username: " . htmlspecialchars($username));
                }
            }
        } else {
            $error = "Invalid username or password";

            // Log failed login attempt
            if(function_exists('log_action')) {
                log_action('LOGIN_FAILED', "Failed login attempt for username: " . htmlspecialchars($username));
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - S&L Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width: 400px; margin-top: 100px;">
    <div class="card shadow">
        <div class="card-body p-4">
            <h3 class="text-center mb-4">
                <i class="bi bi-bank text-primary"></i><br>
                S&L Manager
            </h3>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input name="username" type="text" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input name="password" type="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>

            <p class="text-center mt-3 mb-0 text-muted small">Field workers use your assigned credentials</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
