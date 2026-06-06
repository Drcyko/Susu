<?php
require_once 'config.php';
require_once 'includes/header.php';

// Must be logged in and forced to change password
if(!isset($_SESSION['user_id']) || !isset($_SESSION['force_password_change'])) {
    header("Location: login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($new_username) || empty($new_password)) {
        $_SESSION['msg'] = 'Error: All fields required';
    } elseif($new_password !== $confirm_password) {
        $_SESSION['msg'] = 'Error: Passwords do not match';
    } elseif(strlen($new_password) < 6) {
        $_SESSION['msg'] = 'Error: Password must be at least 6 characters';
    } else {
        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $new_username, $_SESSION['user_id']);
        $check->execute();
        if($check->get_result()->num_rows > 0) {
            $_SESSION['msg'] = 'Error: Username already taken';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $hashed, $_SESSION['user_id']);

            if($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                unset($_SESSION['force_password_change']);
                $_SESSION['msg'] = 'Credentials updated successfully';

                if(function_exists('log_action')) {
                    log_action('PASSWORD_CHANGE', 'User changed default credentials');
                }

                header("Location: " . ($_SESSION['role'] == 'worker' ? 'worker_dashboard.php' : 'index.php'));
                exit();
            } else {
                $_SESSION['msg'] = 'Error: Failed to update credentials';
            }
        }
    }
    header("Location: change_password.php");
    exit();
}
?>
<div class="container" style="max-width: 450px; margin-top: 50px;">
    <div class="card shadow">
        <div class="card-header bg-warning">
            <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Update Your Credentials</h4>
        </div>
        <div class="card-body p-4">
            <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= str_starts_with($_SESSION['msg'], 'Error:') ? 'danger' : 'success' ?> alert-dismissible fade show">
                    <?= $_SESSION['msg'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <p class="text-muted">You must change your default username and password before accessing the system.</p>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">New Username</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg"></i> Update & Continue
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
