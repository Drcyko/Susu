<?php
include 'config.php';

// Start session only if not already started
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$member_data = null;

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_status'])) {
    $phone = trim($_POST['phone']);
    $account_number = trim($_POST['account_number']);

    if(empty($phone) || empty($account_number)) {
        $error = "Please enter both phone number and account number";
    } else {
        // Verify phone + account number match a real member
        $stmt = $conn->prepare("
            SELECT m.id, m.full_name, m.phone, s.account_number, s.balance, s.id as account_id
            FROM members m
            JOIN savings_accounts s ON m.id = s.member_id
            WHERE m.phone =? AND s.account_number =?
        ");
        $stmt->bind_param("ss", $phone, $account_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1) {
            $member_data = $result->fetch_assoc();

            // Get loans
            $loans = $conn->prepare("
                SELECT id, principal, amount_due, amount_paid, status, due_date
                FROM loans
                WHERE member_id =?
                ORDER BY id DESC
            ");
            $loans->bind_param("i", $member_data['id']);
            $loans->execute();
            $member_data['loans'] = $loans->get_result();
            $loans->close();

            // Get last 5 transactions
            $txns = $conn->prepare("
                SELECT txn_type, amount, txn_date, description
                FROM transactions
                WHERE account_id =?
                ORDER BY txn_date DESC LIMIT 5
            ");
            $txns->bind_param("i", $member_data['account_id']);
            $txns->execute();
            $member_data['transactions'] = $txns->get_result();
            $txns->close();
        } else {
            $error = "No account found. Check your phone number and account number";
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
    <title>Check My Account - S&L Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width: 500px; margin-top: 30px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            <h4><i class="bi bi-bank"></i> S&L Manager</h4>
            <p class="mb-0">Check Your Account Status</p>
        </div>
        <div class="card-body p-4">
            <?php if(!$member_data):?>
                <?php if($error):?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error)?></div>
                <?php endif;?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input name="phone" type="tel" class="form-control form-control-lg"
                               placeholder="0244123456" value="<?= isset($_POST['phone'])? htmlspecialchars($_POST['phone']) : ''?>" required>
                        <small class="text-muted">The phone number you registered with</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input name="account_number" type="text" class="form-control form-control-lg"
                               placeholder="SAV123456" value="<?= isset($_POST['account_number'])? htmlspecialchars($_POST['account_number']) : ''?>" required>
                        <small class="text-muted">Found on your passbook or receipt</small>
                    </div>
                    <button name="check_status" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-search"></i> Check Status
                    </button>
                </form>
                <p class="text-center mt-3 text-muted small">
                    Your information is secure. We only show data if both details match.
                </p>
            <?php else:?>
                <!-- Show Member Status -->
                <div class="text-center mb-3">
                    <h5>Welcome, <?= htmlspecialchars(explode(' ', $member_data['full_name'])[0])?></h5>
                    <small class="text-muted"><?= htmlspecialchars($member_data['account_number'])?></small>
                </div>

                <div class="card bg-success text-white mb-3">
                    <div class="card-body text-center">
                        <small>Savings Balance</small>
                        <h2>GHS <?= number_format($member_data['balance']?? 0, 2)?></h2>
                    </div>
                </div>

                <h6><i class="bi bi-cash-coin"></i> My Loans</h6>
                <?php if($member_data['loans']->num_rows > 0):?>
                    <?php while($l = $member_data['loans']->fetch_assoc()):
                        $balance = $l['amount_due'] - $l['amount_paid'];
                        $status_color = match($l['status']) {
                            'Active' => 'info', 'Closed' => 'success', 'Pending' => 'warning',
                            'Approved' => 'primary', 'Defaulted' => 'danger', default => 'secondary'
                        };
                   ?>
                    <div class="card mb-2">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>GHS <?= number_format($l['principal'], 2)?></strong><br>
                                    <small class="text-muted">Balance: GHS <?= number_format($balance, 2)?></small>
                                </div>
                                <span class="badge bg-<?= $status_color?>"><?= $l['status']?></span>
                            </div>
                            <?php if($l['status'] == 'Active' && $l['due_date']):?>
                                <small class="text-muted">Due: <?= date('d M Y', strtotime($l['due_date']))?></small>
                            <?php endif;?>
                        </div>
                    </div>
                    <?php endwhile;?>
                <?php else:?>
                    <p class="text-muted text-center">No loans</p>
                <?php endif;?>

                <h6 class="mt-3"><i class="bi bi-clock-history"></i> Recent Transactions</h6>
                <?php if($member_data['transactions']->num_rows > 0):?>
                    <div class="list-group list-group-flush">
                    <?php while($t = $member_data['transactions']->fetch_assoc()):?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= $t['txn_type']?></strong><br>
                                    <small class="text-muted"><?= date('d M Y', strtotime($t['txn_date']))?></small>
                                </div>
                                <div class="text-end">
                                    <strong class="<?= $t['txn_type'] == 'Deposit'? 'text-success' : 'text-danger'?>">
                                        <?= $t['txn_type'] == 'Deposit'? '+' : '-'?>GHS <?= number_format($t['amount'], 2)?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    <?php endwhile;?>
                    </div>
                <?php else:?>
                    <p class="text-muted text-center">No transactions yet</p>
                <?php endif;?>

                <a href="member_check.php" class="btn btn-outline-secondary w-100 mt-3">
                    <i class="bi bi-arrow-left"></i> Check Another Account
                </a>
            <?php endif;?>
        </div>
    </div>
    <p class="text-center mt-3 text-muted small">
        © <?= date('Y')?> S&L Manager | For help call: 0302-XXX-XXX
    </p>
</div>
</body>
</html>
