<?php
include 'config.php';
include 'includes/header.php'; // header.php already handles login redirect + background

// Get dashboard stats with error handling
$total_members_result = $conn->query("SELECT COUNT(*) as cnt FROM members");
$total_members = $total_members_result ? $total_members_result->fetch_assoc()['cnt'] : 0;

$total_savings_result = $conn->query("SELECT SUM(balance) as total FROM savings_accounts");
$total_savings = $total_savings_result ? ($total_savings_result->fetch_assoc()['total'] ?? 0) : 0;

$total_loans_result = $conn->query("SELECT COUNT(*) as cnt FROM loans");
$total_loans = $total_loans_result ? $total_loans_result->fetch_assoc()['cnt'] : 0;

$active_loans_result = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE status IN ('Approved','Active')");
$active_loans = $active_loans_result ? $active_loans_result->fetch_assoc()['cnt'] : 0;

$loan_portfolio_result = $conn->query("SELECT SUM(amount_due - amount_paid) as outstanding FROM loans WHERE status IN ('Approved','Active')");
$loan_portfolio = $loan_portfolio_result ? ($loan_portfolio_result->fetch_assoc()['outstanding'] ?? 0) : 0;

// Daily collection stats
$daily_clients_result = $conn->query("SELECT COUNT(*) as cnt FROM savings_accounts WHERE daily_rate > 0");
$daily_clients = $daily_clients_result ? $daily_clients_result->fetch_assoc()['cnt'] : 0;

$expected_daily_result = $conn->query("SELECT SUM(daily_rate) as total FROM savings_accounts WHERE daily_rate > 0");
$expected_daily = $expected_daily_result ? ($expected_daily_result->fetch_assoc()['total'] ?? 0) : 0;

$overdue_collections_result = $conn->query("
    SELECT COUNT(*) as cnt
    FROM savings_accounts
    WHERE daily_rate > 0
    AND (last_collection_date IS NULL OR last_collection_date < CURDATE())
");
$overdue_collections = $overdue_collections_result ? $overdue_collections_result->fetch_assoc()['cnt'] : 0;

$today_collected_result = $conn->query("
    SELECT COALESCE(SUM(t.amount), 0) as total
    FROM transactions t
    JOIN savings_accounts s ON t.account_id = s.id
    WHERE t.txn_type = 'Deposit'
    AND s.daily_rate > 0
    AND DATE(t.txn_date) = CURDATE()
");
$today_collected = $today_collected_result ? $today_collected_result->fetch_assoc()['total'] : 0;

// Monthly expenses - NEW
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$total_expenses_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM expenses
    WHERE expense_date BETWEEN '$month_start' AND '$month_end'
");
$total_expenses = ($total_expenses_result && $row = $total_expenses_result->fetch_assoc()) ? $row['total'] : 0;

$recent_txns = $conn->query("
    SELECT t.*, m.full_name, s.account_number
    FROM transactions t
    JOIN savings_accounts s ON t.account_id = s.id
    JOIN members m ON s.member_id = m.id
    ORDER BY t.txn_date DESC LIMIT 5
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Dashboard</h3>
        <p class="text-muted mb-0">Welcome back, <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User' ?></p>
    </div>
    <div class="text-end">
        <small class="text-muted"><?= date('l, d F Y') ?></small>
    </div>
</div>

<?php if($overdue_collections > 0): ?>
<div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Attention:</strong> <?= $overdue_collections ?> client<?= $overdue_collections > 1 ? 's' : '' ?> with daily rates haven't paid today.
    <a href="members.php" class="alert-link">View members</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3">
        <a href="members.php" class="text-decoration-none">
            <div class="card text-bg-primary mb-3 shadow" style="border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0 opacity-75">Total Members</h6>
                            <h2 class="mb-0 fw-bold"><?= $total_members ?></h2>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="members.php" class="text-decoration-none">
            <div class="card text-bg-success mb-3 shadow" style="border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0 opacity-75">Total Savings</h6>
                            <h2 class="mb-0 fw-bold">GHS <?= number_format($total_savings, 2) ?></h2>
                        </div>
                        <i class="bi bi-piggy-bank fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="expenses.php" class="text-decoration-none">
            <div class="card text-bg-danger mb-3 shadow" style="border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0 opacity-75">Monthly Expenses</h6>
                            <h2 class="mb-0 fw-bold">GHS <?= number_format($total_expenses, 2) ?></h2>
                        </div>
                        <i class="bi bi-receipt fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="loans.php" class="text-decoration-none">
            <div class="card text-bg-warning mb-3 shadow" style="border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0 opacity-75">Active Loans</h6>
                            <h2 class="mb-0 fw-bold"><?= $active_loans ?> / <?= $total_loans ?></h2>
                        </div>
                        <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card border-0 mb-3 shadow-sm" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted mb-1">Daily Collection Clients</h6>
                        <h3 class="mb-0 fw-bold"><?= $daily_clients ?></h3>
                        <small class="text-muted">Expected: GHS <?= number_format($expected_daily, 2) ?>/day</small>
                    </div>
                    <i class="bi bi-calendar-check text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 mb-3 shadow-sm" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted mb-1">Collected Today</h6>
                        <h3 class="mb-0 text-success fw-bold">GHS <?= number_format($today_collected, 2) ?></h3>
                        <small class="text-muted">
                            <?= $expected_daily > 0 ? number_format(($today_collected / $expected_daily) * 100, 1) : 0 ?>% of expected
                        </small>
                    </div>
                    <i class="bi bi-check-circle text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 mb-3 shadow-sm" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted mb-1">Overdue Collections</h6>
                        <h3 class="mb-0 text-<?= $overdue_collections > 0 ? 'warning' : 'muted' ?> fw-bold"><?= $overdue_collections ?></h3>
                        <small class="text-muted">
                            <?= $overdue_collections > 0 ? 'Need collection today' : 'All caught up' ?>
                        </small>
                    </div>
                    <i class="bi bi-exclamation-triangle text-<?= $overdue_collections > 0 ? 'warning' : 'muted' ?> fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Transactions</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_txns && $recent_txns->num_rows > 0): ?>
                            <?php while($txn = $recent_txns->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d M Y H:i', strtotime($txn['txn_date'])) ?></td>
                                <td><?= htmlspecialchars($txn['full_name']) ?></td>
                                <td><code><?= htmlspecialchars($txn['account_number']) ?></code></td>
                                <td>
                                    <span class="badge bg-<?= $txn['txn_type']=='Deposit'?'success':'danger' ?>">
                                        <i class="bi bi-<?= $txn['txn_type']=='Deposit'?'arrow-down':'arrow-up' ?>"></i>
                                        <?= htmlspecialchars($txn['txn_type']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">GHS <?= number_format($txn['amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Compliance & Extras</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-bank text-primary"></i>
                                <strong>BOG/Regulatory Compliance</strong>
                                <br><small class="text-muted">Monthly returns, prudential reports</small>
                            </div>
                            <span class="badge bg-secondary">Soon</span>
                        </div>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-lock text-success"></i>
                                <strong>Data Encryption</strong>
                                <br><small class="text-muted">Protect member financial data</small>
                            </div>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-heart-pulse text-danger"></i>
                                <strong>Loan Insurance Tracking</strong>
                                <br><small class="text-muted">Credit life insurance partners</small>
                            </div>
                            <span class="badge bg-secondary">Soon</span>
                        </div>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people-fill text-info"></i>
                                <strong>Group Lending Support</strong>
                                <br><small class="text-muted">Susu groups, VSLAs, solidarity</small>
                            </div>
                            <span class="badge bg-secondary">Soon</span>
                        </div>
                    </div>
                    <div class="list-group-item px-0 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-text text-warning"></i>
                                <strong>Audit Trail</strong>
                                <br><small class="text-muted">All user actions logged</small>
                            </div>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-grid gap-2">
                    <a href="bog_report.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-printer"></i> Print BOG Report
                    </a>
                    <a href="audit_log.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-text"></i> View Audit Trail
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
a .card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
a .card {
    cursor: pointer;
}
</style>

<?php include 'includes/footer.php'; ?>
