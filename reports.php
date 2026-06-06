<?php
include 'config.php';
include 'includes/header.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Date filter
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// 1. Monthly stats
$deposits = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE txn_type='Deposit' AND txn_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
$withdrawals = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE txn_type='Withdrawal' AND txn_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
$new_loans = $conn->query("SELECT COALESCE(SUM(principal),0) as total FROM loans WHERE disbursement_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
$repayments = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE txn_type='Withdrawal' AND description LIKE 'Loan repayment%' AND txn_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];

// 2. Overall portfolio
$total_savings = $conn->query("SELECT COALESCE(SUM(balance),0) as total FROM savings_accounts")->fetch_assoc()['total'];
$active_principal = $conn->query("SELECT COALESCE(SUM(principal),0) as total FROM loans WHERE status IN ('Approved','Active')")->fetch_assoc()['total'];
$total_interest_earned = $conn->query("SELECT COALESCE(SUM(amount_due - principal),0) as total FROM loans WHERE status='Closed'")->fetch_assoc()['total'];
$outstanding_loans = $conn->query("SELECT COALESCE(SUM(amount_due - amount_paid),0) as total FROM loans WHERE status IN ('Approved','Active')")->fetch_assoc()['total'];

// 3. Overdue loans
$overdue = $conn->query("
    SELECT l.*, m.full_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.status='Active' AND l.due_date < CURDATE() AND l.amount_paid < l.amount_due
    ORDER BY l.due_date ASC
");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Reports & Analytics</h3>
    <form method="GET" class="d-flex">
        <input type="month" name="month" class="form-control me-2" value="<?= $month ?>">
        <button class="btn btn-primary">Filter</button>
    </form>
</div>

<h5>Monthly Summary: <?= date('F Y', strtotime($start_date)) ?></h5>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-bg-success mb-3">
            <div class="card-body">
                <h6>Total Deposits</h6>
                <h3>GHS <?= number_format($deposits, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-danger mb-3">
            <div class="card-body">
                <h6>Total Withdrawals</h6>
                <h3>GHS <?= number_format($withdrawals, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-info mb-3">
            <div class="card-body">
                <h6>Loans Disbursed</h6>
                <h3>GHS <?= number_format($new_loans, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-primary mb-3">
            <div class="card-body">
                <h6>Loan Repayments</h6>
                <h3>GHS <?= number_format($repayments, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<h5>Overall Portfolio</h5>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <h6>Total Savings Held</h6>
                <h4 class="text-success">GHS <?= number_format($total_savings, 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <h6>Active Loan Principal</h6>
                <h4 class="text-warning">GHS <?= number_format($active_principal, 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body">
                <h6>Outstanding Loans</h6>
                <h4 class="text-danger">GHS <?= number_format($outstanding_loans, 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <h6>Interest Earned</h6>
                <h4 class="text-primary">GHS <?= number_format($total_interest_earned, 2) ?></h4>
            </div>
        </div>
    </div>
</div>

<h5>Overdue Loans <span class="badge bg-danger"><?= $overdue->num_rows ?></span></h5>
<div class="table-responsive">
<table class="table table-bordered table-sm">
    <thead class="table-dark">
        <tr>
            <th>Member</th>
            <th>Principal</th>
            <th>Amount Due</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Due Date</th>
            <th>Days Overdue</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if($overdue->num_rows > 0): ?>
        <?php while($o = $overdue->fetch_assoc()):
            $balance = $o['amount_due'] - $o['amount_paid'];
            $days_overdue = floor((time() - strtotime($o['due_date'])) / 86400);
        ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($o['full_name']) ?></td>
            <td>GHS <?= number_format($o['principal'], 2) ?></td>
            <td>GHS <?= number_format($o['amount_due'], 2) ?></td>
            <td>GHS <?= number_format($o['amount_paid'], 2) ?></td>
            <td>GHS <?= number_format($balance, 2) ?></td>
            <td><?= date('d M Y', strtotime($o['due_date'])) ?></td>
            <td><?= $days_overdue ?> days</td>
            <td><a href="loan_details.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-warning">Collect</a></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="8" class="text-center text-muted">No overdue loans</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
