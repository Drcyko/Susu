<?php
// Start session if not already started
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in - except on login.php itself
$current_page = basename($_SERVER['PHP_SELF']);
if(!isset($_SESSION['user_id']) && $current_page !== 'login.php') {
    header("Location: login.php");
    exit();
}

// Force password change if required - UPGRADE
if(isset($_SESSION['user_id']) && isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true && $current_page !== 'change_password.php') {
    header("Location: change_password.php");
    exit();
}

// Safe role check to prevent undefined index
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S&L Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)),
                    url('/savings_loan/images/bg.jpg') no-repeat center center fixed;
        background-color: #f8f9fa; /* Fallback color if image fails */
        background-size: cover;
        min-height: 100vh;
    }
    .navbar {
        background-color: rgba(33, 37, 41, 0.98) !important;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px); /* Safari support */
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .container.mt-4 {
        background-color: rgba(255,255,255,0.97);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    .table {
        background-color: white;
    }
    .alert {
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= $user_role === 'worker' ? 'worker_dashboard.php' : 'index.php' ?>">
      <i class="bi bi-bank"></i> S&L Manager
    </a>
    <?php if(isset($_SESSION['user_id'])): ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <?php if($user_role !== 'worker'): ?>
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page == 'members.php' ? 'active' : '' ?>" href="members.php">
            <i class="bi bi-people"></i> Members
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page == 'loans.php' ? 'active' : '' ?>" href="loans.php">
            <i class="bi bi-cash-coin"></i> Loans
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page == 'expenses.php' ? 'active' : '' ?>" href="expenses.php">
            <i class="bi bi-receipt"></i> Expenses
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
            <i class="bi bi-graph-up"></i> Reports
          </a>
        </li>
        <?php if(in_array($user_role, ['admin', 'manager'])): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($current_page, ['bog_report.php', 'audit_log.php', 'approve_collections.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-shield-check"></i> Compliance
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="approve_collections.php"><i class="bi bi-check2-square"></i> Approve Collections</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="bog_report.php"><i class="bi bi-file-earmark-text"></i> BOG Report</a></li>
            <li><a class="dropdown-item" href="audit_log.php"><i class="bi bi-clock-history"></i> Audit Trail</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>
      <ul class="navbar-nav <?= $user_role === 'worker' ? 'ms-auto' : '' ?>">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User' ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted">Role: <?= $user_role ? htmlspecialchars($user_role) : 'N/A' ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</nav>
<div class="container mt-4">
<?php
// Display flash messages
if(isset($_SESSION['msg'])) {
    $msg_type = str_starts_with($_SESSION['msg'], 'Error:') ? 'danger' : 'success';
    echo '<div class="alert alert-'.$msg_type.' alert-dismissible fade show" role="alert">';
    echo $_SESSION['msg']; // Allow HTML like links in success messages
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['msg']);
}
?>
