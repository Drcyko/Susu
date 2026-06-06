<?php
include 'config.php';
include 'includes/header.php';

// Must be logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($loan_id <= 0) {
    $_SESSION['msg'] = "Error: Invalid loan ID";
    header("Location: loans.php");
    exit();
}

// Get loan details with member info
$stmt = $conn->prepare("
    SELECT l.*, m.full_name, m.phone, m.email, m.id as member_id
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.id = ?
");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$loan) {
    $_SESSION['msg'] = "Error: Loan not found";
    header("Location: loans.php");
    exit();
}

// Handle loan disbursement
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disburse_loan'])) {
    if($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager') {
        $_SESSION['msg'] = "Error: You don't have permission to disburse loans";
        header("Location: loan_details.php?id=$loan_id");
        exit();
    }

    if($loan['status'] !== 'Approved') {
        $_SESSION['msg'] = "Error: Only approved loans can be disbursed";
        header("Location: loan_details.php?id=$loan_id");
        exit();
    }

    // Get member's savings account
    $acc_stmt = $conn->prepare("SELECT id, balance FROM savings_accounts WHERE member_id = ?");
    $acc_stmt->bind_param("i", $loan['member_id']);
    $acc_stmt->execute();
    $account = $acc_stmt->get_result()->fetch_assoc();
    $acc_stmt->close();

    if(!$account) {
        $_SESSION['msg'] = "Error: Member has no savings account to disburse to";
        header("Location: loan_details.php?id=$loan_id");
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1. Credit member's savings account with principal
        $stmt1 = $conn->prepare("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?");
        $stmt1->bind_param("di", $loan['principal'], $account['id']);
        $stmt1->execute();
        $stmt1->close();

        // 2. Log disbursement transaction
        $desc = "Loan disbursement - Loan ID: $loan_id";
        $stmt2 = $conn->prepare("INSERT INTO transactions (account_id, amount, txn_type, description) VALUES (?, ?, 'Deposit', ?)");
        $stmt2->bind_param("ids", $account['id'], $loan['principal'], $desc);
        $stmt2->execute();
        $stmt2->close();

        // 3. Update loan status to Active
        $stmt3 = $conn->prepare("UPDATE loans SET status = 'Active', disbursement_date = CURDATE() WHERE id = ?");
        $stmt3->bind_param("i", $loan_id);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();
        $_SESSION['msg'] = "Loan of GHS " . number_format($loan['principal'], 2) . " disbursed successfully";
        header("Location: loan_details.php?id=$loan_id");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Error: Disbursement failed. " . $e->getMessage();
    }
}

// Handle loan repayment
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : 'Cash';

    $balance_due = $loan['amount_due'] - $loan['amount_paid'];

    if($payment_amount <= 0) {
        $_SESSION['msg'] = "Error: Payment amount must be greater than 0";
    } elseif($payment_amount > $balance_due) {
        $_SESSION['msg'] = "Error: Payment cannot exceed balance due of GHS " . number_format($balance_due, 2);
    } elseif($loan['status'] !== 'Active') {
        $_SESSION['msg'] = "Error: Can only record payments for active loans";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update amount_paid
            $new_amount_paid = $loan['amount_paid'] + $payment_amount;
            $new_status = $new_amount_paid >= $loan['amount_due'] ? 'Closed' : 'Active';

            $stmt1 = $conn->prepare("UPDATE loans SET amount_paid = ?, status = ? WHERE id = ?");
            $stmt1->bind_param("dsi", $new_amount_paid, $new_status, $loan_id);
            $stmt1->execute();
            $stmt1->close();

            // 2. Log repayment in transactions table using member's savings account
            $acc_stmt = $conn->prepare("SELECT id FROM savings_accounts WHERE member_id = ?");
            $acc_stmt->bind_param("i", $loan['member_id']);
            $acc_stmt->execute();
            $account = $acc_stmt->get_result()->fetch_assoc();
            $acc_stmt->close();

            if($account) {
                $desc = "Loan repayment via $payment_method - Loan ID: $loan_id";
                $stmt2 = $conn->prepare("INSERT INTO transactions (account_id, amount, txn_type, description) VALUES (?, ?, 'Withdrawal', ?)");
                $stmt2->bind_param("ids", $account['id'], $payment_amount, $desc);
                $stmt2->execute();
                $stmt2->close();
            }

            $conn->commit();
            $_SESSION['msg'] = "Payment of GHS " . number_format($payment_amount, 2) . " recorded successfully";
            if($new_status == 'Closed') {
                $_SESSION['msg'] .= ". Loan fully paid and closed.";
            }
            header("Location: loan_details.php?id=$loan_id");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Error: Payment failed. " . $e->getMessage();
        }
    }
}

// Refresh loan data after any updates
$stmt = $conn->prepare("
    SELECT l.*, m.full_name, m.phone, m.email, m.id as member_id
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.id = ?
");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance_due = $loan['amount_due'] - $loan['amount_paid'];
$progress_percent = $loan['amount_due'] > 0 ? ($loan['amount_paid'] / $loan['amount_due']) * 100 : 0;

// Get repayment history
$repayments = $conn->prepare("
    SELECT * FROM transactions
    WHERE description LIKE ? AND txn_type = 'Withdrawal'
    ORDER BY txn_date DESC
");
$search_term = "%Loan ID: $loan_id%";
$repayments->bind_param("s", $search_term);
$repayments->execute();
$repayment_history = $repayments->get_result();
$repayments->close();

$status_class = match($loan['status']) {
    'Approved' => 'success',
    'Pending' => 'warning',
    'Active' => 'info',
    'Closed' => 'secondary',
    'Defaulted' => 'danger',
    default => 'secondary'
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Loan Details</h3>
    <a href="loans.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Loans</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Loan Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Member:</strong></td>
                        <td><?= htmlspecialchars($loan['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?= htmlspecialchars($loan['phone']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="badge bg-<?= $status_class ?>"><?= $loan['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Principal:</strong></td>
                        <td>GHS <?= number_format($loan['principal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Interest Rate:</strong></td>
                        <td><?= $loan['interest_rate'] ?>% per annum</td>
                    </tr>
                    <tr>
                        <td><strong>Tenure:</strong></td>
                        <td><?= $loan['tenure_months'] ?> months</td>
                    </tr>
                    <tr>
                        <td><strong>Interest Amount:</strong></td>
                        <td>GHS <?= number_format($loan['amount_due'] - $loan['principal'], 2) ?></td>
                    </tr>
                    <tr class="table-active">
                        <td><strong>Total Amount Due:</strong></td>
                        <td><strong>GHS <?= number_format($loan['amount_due'], 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Amount Paid:</strong></td>
                        <td class="text-success">GHS <?= number_format($loan['amount_paid'], 2) ?></td>
                    </tr>
                    <tr class="table-active">
                        <td><strong>Balance Due:</strong></td>
                        <td><strong class="text-danger">GHS <?= number_format($balance_due, 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Due Date:</strong></td>
                        <td><?= $loan['due_date'] ? date('d M Y', strtotime($loan['due_date'])) : '-' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Disbursed On:</strong></td>
                        <td><?= $loan['disbursement_date'] ? date('d M Y', strtotime($loan['disbursement_date'])) : 'Not disbursed' ?></td>
                    </tr>
                </table>

                <div class="mt-3">
                    <label class="form-label">Repayment Progress</label>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress_percent ?>%">
                            <?= number_format($progress_percent, 1) ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <?php if($loan['status'] == 'Approved'): ?>
        <div class="card mb-3 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Disburse Loan</h5>
            </div>
            <div class="card-body">
                <p>This loan is approved and ready for disbursement.</p>
                <p><strong>Amount to Disburse:</strong> GHS <?= number_format($loan['principal'], 2) ?></p>
                <p class="text-muted small">This will credit the member's savings account and mark the loan as Active.</p>
                <form method="POST" onsubmit="return confirm('Disburse GHS <?= number_format($loan['principal'], 2) ?> to <?= htmlspecialchars($loan['full_name']) ?>?')">
                    <button name="disburse_loan" class="btn btn-success w-100">
                        <i class="bi bi-check-circle"></i> Disburse Loan Now
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if($loan['status'] == 'Active'): ?>
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cash"></i> Record Repayment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Payment Amount (GHS)</label>
                        <input name="payment_amount" type="number" step="0.01" min="0.01" max="<?= $balance_due ?>"
                               class="form-control" placeholder="0.00" required>
                        <small class="text-muted">Balance due: GHS <?= number_format($balance_due, 2) ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Cash">Cash</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <button name="record_payment" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Repayment History</h5>
            </div>
            <div class="card-body">
                <?php if($repayment_history && $repayment_history->num_rows > 0): ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($rep = $repayment_history->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($rep['txn_date'])) ?></td>
                            <td>GHS <?= number_format($rep['amount'], 2) ?></td>
                            <td><small><?= str_replace("Loan repayment via ", "", str_replace(" - Loan ID: $loan_id", "", $rep['description'])) ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted text-center mb-0">No repayments recorded yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
