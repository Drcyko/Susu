<?php
include 'config.php';
include 'includes/header.php';

// Only admin/manager can access
if(!in_array($_SESSION['role'], ['admin', 'manager'])) {
    $_SESSION['msg'] = "Error: Access denied";
    header("Location: index.php");
    exit();
}

$report_date = $_GET['date'] ?? date('Y-m-d');
$month_start = date('Y-m-01', strtotime($report_date));
$month_end = date('Y-m-t', strtotime($report_date));

// 1. CAPITAL ADEQUACY - with error handling
$total_savings_result = $conn->query("SELECT SUM(balance) as total FROM savings_accounts");
$total_savings = ($total_savings_result && $row = $total_savings_result->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

$total_loans_outstanding_result = $conn->query("
    SELECT SUM(amount_due - amount_paid) as total
    FROM loans
    WHERE status IN ('Approved','Active')
");
$total_loans_outstanding = ($total_loans_outstanding_result && $row = $total_loans_outstanding_result->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

// 2. ASSET QUALITY - Check if due_date exists first
$columns = $conn->query("SHOW COLUMNS FROM loans LIKE 'due_date'");
$has_due_date = $columns->num_rows > 0;

$par_30 = 0;
$par_90 = 0;

if($has_due_date) {
    $par_30_result = $conn->query("
        SELECT SUM(amount_due - amount_paid) as total
        FROM loans
        WHERE status IN ('Approved','Active')
        AND DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 30
    ");
    $par_30 = ($par_30_result && $row = $par_30_result->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

    $par_90_result = $conn->query("
        SELECT SUM(amount_due - amount_paid) as total
        FROM loans
        WHERE status IN ('Approved','Active')
        AND DATEDIFF(CURDATE(), due_date) > 90
    ");
    $par_90 = ($par_90_result && $row = $par_90_result->fetch_assoc()) ? ($row['total'] ?? 0) : 0;
}

// 3. LIQUIDITY
$cash_on_hand = $total_savings - $total_loans_outstanding;

// 4. MONTHLY ACTIVITY - Check if txn_date exists
$columns = $conn->query("SHOW COLUMNS FROM transactions LIKE 'txn_date'");
$date_col = $columns->num_rows > 0 ? 'txn_date' : 'created_at';

$deposits_month_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM transactions
    WHERE txn_type = 'Deposit'
    AND $date_col BETWEEN '$month_start' AND '$month_end'
");
$deposits_month = ($deposits_month_result && $row = $deposits_month_result->fetch_assoc()) ? $row['total'] : 0;

$withdrawals_month_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM transactions
    WHERE txn_type = 'Withdrawal'
    AND $date_col BETWEEN '$month_start' AND '$month_end'
");
$withdrawals_month = ($withdrawals_month_result && $row = $withdrawals_month_result->fetch_assoc()) ? $row['total'] : 0;

// Check if loans has created_at
$columns = $conn->query("SHOW COLUMNS FROM loans LIKE 'created_at'");
$loans_date_col = $columns->num_rows > 0 ? 'created_at' : 'id';

$loans_disbursed_month_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM loans
    WHERE $loans_date_col BETWEEN '$month_start' AND '$month_end'
");
$loans_disbursed_month = ($loans_disbursed_month_result && $row = $loans_disbursed_month_result->fetch_assoc()) ? $row['total'] : 0;

// 5. EXPENSES - NEW SECTION
$expenses_month_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM expenses
    WHERE expense_date BETWEEN '$month_start' AND '$month_end'
");
$total_expenses = ($expenses_month_result && $row = $expenses_month_result->fetch_assoc()) ? $row['total'] : 0;

// Get expense breakdown by category
$expenses_breakdown = $conn->query("
    SELECT category, SUM(amount) as total
    FROM expenses
    WHERE expense_date BETWEEN '$month_start' AND '$month_end'
    GROUP BY category
    ORDER BY total DESC
");

// 6. MEMBER STATS
$columns = $conn->query("SHOW COLUMNS FROM members LIKE 'created_at'");
$members_date_col = $columns->num_rows > 0 ? 'created_at' : 'id';

$new_members_month_result = $conn->query("
    SELECT COUNT(*) as cnt FROM members
    WHERE $members_date_col BETWEEN '$month_start' AND '$month_end'
");
$new_members_month = ($new_members_month_result && $row = $new_members_month_result->fetch_assoc()) ? $row['cnt'] : 0;

// Log audit if function exists
if(function_exists('log_action')) {
    log_action('BOG_REPORT_GENERATED', "Report for $month_start to $month_end");
}

$par_30_ratio = $total_loans_outstanding > 0 ? ($par_30 / $total_loans_outstanding) * 100 : 0;
$par_90_ratio = $total_loans_outstanding > 0 ? ($par_90 / $total_loans_outstanding) * 100 : 0;
$net_cash_flow = $deposits_month - $withdrawals_month - $loans_disbursed_month - $total_expenses;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3><i class="bi bi-bank"></i> BOG Prudential Report</h3>
        <p class="text-muted mb-0">Period: <?= date('F Y', strtotime($month_start)) ?></p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
</div>

<?php if(!$has_due_date): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Note:</strong> PAR ratios unavailable - `due_date` column missing in loans table. Add it for full BOG compliance.
</div>
<?php endif; ?>

<div class="card shadow border-0 mb-4" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">1. CAPITAL ADEQUACY & LIQUIDITY</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <td width="60%">Total Members' Savings (Liabilities)</td>
                <td class="text-end fw-bold">GHS <?= number_format($total_savings, 2) ?></td>
            </tr>
            <tr>
                <td>Total Loans Outstanding (Assets)</td>
                <td class="text-end fw-bold">GHS <?= number_format($total_loans_outstanding, 2) ?></td>
            </tr>
            <tr class="table-info">
                <td><strong>Cash/Liquidity Position</strong></td>
                <td class="text-end fw-bold">GHS <?= number_format($cash_on_hand, 2) ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow border-0 mb-4" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">2. ASSET QUALITY - PORTFOLIO AT RISK (PAR)</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <td>PAR 1-30 Days</td>
                <td class="text-end">GHS <?= number_format($par_30, 2) ?></td>
                <td class="text-end fw-bold <?= $par_30_ratio > 5 ? 'text-danger' : 'text-success' ?>">
                    <?= number_format($par_30_ratio, 2) ?>%
                </td>
            </tr>
            <tr>
                <td>PAR > 90 Days (NPL)</td>
                <td class="text-end">GHS <?= number_format($par_90, 2) ?></td>
                <td class="text-end fw-bold <?= $par_90_ratio > 5 ? 'text-danger' : 'text-success' ?>">
                    <?= number_format($par_90_ratio, 2) ?>%
                </td>
            </tr>
            <tr class="table-light">
                <td colspan="3"><small>BOG Threshold: PAR > 90 days should be < 5%</small></td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow border-0 mb-4" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">3. MONTHLY ACTIVITY SUMMARY</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <td>New Members Registered</td>
                <td class="text-end fw-bold"><?= $new_members_month ?></td>
            </tr>
            <tr>
                <td>Total Deposits Received</td>
                <td class="text-end fw-bold text-success">GHS <?= number_format($deposits_month, 2) ?></td>
            </tr>
            <tr>
                <td>Total Withdrawals Paid</td>
                <td class="text-end fw-bold text-danger">GHS <?= number_format($withdrawals_month, 2) ?></td>
            </tr>
            <tr>
                <td>New Loans Disbursed</td>
                <td class="text-end fw-bold">GHS <?= number_format($loans_disbursed_month, 2) ?></td>
            </tr>
            <tr>
                <td>Total Operational Expenses</td>
                <td class="text-end fw-bold text-danger">GHS <?= number_format($total_expenses, 2) ?></td>
            </tr>
            <tr class="table-info">
                <td><strong>Net Cash Flow</strong></td>
                <td class="text-end fw-bold <?= $net_cash_flow >= 0 ? 'text-success' : 'text-danger' ?>">
                    GHS <?= number_format($net_cash_flow, 2) ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow border-0 mb-4" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">4. EXPENSE BREAKDOWN</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">% of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if($expenses_breakdown && $expenses_breakdown->num_rows > 0): ?>
                    <?php while($exp = $expenses_breakdown->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($exp['category']) ?></td>
                        <td class="text-end">GHS <?= number_format($exp['total'], 2) ?></td>
                        <td class="text-end">
                            <?= $total_expenses > 0 ? number_format(($exp['total'] / $total_expenses) * 100, 1) : 0 ?>%
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="table-secondary fw-bold">
                        <td>TOTAL EXPENSES</td>
                        <td class="text-end">GHS <?= number_format($total_expenses, 2) ?></td>
                        <td class="text-end">100%</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">No expenses recorded this month</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">5. DECLARATION</h5>
    </div>
    <div class="card-body">
        <p>I hereby certify that the information provided in this report is true and accurate to the best of my knowledge.</p>
        <br><br>
        <div class="row">
            <div class="col-md-6">
                <p>_________________________<br>Name & Signature</p>
                <p><small>Manager</small></p>
            </div>
            <div class="col-md-6 text-end">
                <p>_________________________<br>Date</p>
                <p><small><?= date('d F Y') ?></small></p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .navbar, .btn, .alert, footer { display: none !important; }
    .card { page-break-inside: avoid; }
    body { background: white !important; }
    .container.mt-4 { background: white !important; box-shadow: none !important; }
}
</style>

<?php include 'includes/footer.php'; ?>
