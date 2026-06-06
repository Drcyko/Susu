<?php
include 'config.php';
include 'includes/header.php';

// Only workers can access
if($_SESSION['role'] !== 'worker' && $_SESSION['role'] !== 'admin') {
    $_SESSION['msg'] = "Error: Access denied";
    header("Location: index.php");
    exit();
}

// Handle collection submission - stays pending until closing approved
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect'])) {
    $account_id = intval($_POST['account_id']);
    $amount = floatval($_POST['amount']);
    $collected_by = $_SESSION['user_id'];

    if($amount <= 0) {
        $_SESSION['msg'] = "Error: Amount must be greater than 0";
    } else {
        $acc_result = $conn->query("SELECT * FROM savings_accounts WHERE id = $account_id");
        $account = $acc_result->fetch_assoc();

        if($account) {
            // Insert as pending - will be approved when closing is approved
            $stmt = $conn->prepare("INSERT INTO transactions (account_id, txn_type, amount, collected_by, status, txn_date) VALUES (?, 'Deposit', ?, ?, 'pending', NOW())");
            $stmt->bind_param("idi", $account_id, $amount, $collected_by);

            if($stmt->execute()) {
                $_SESSION['msg'] = "Success: GHS " . number_format($amount, 2) . " submitted for approval";
            } else {
                $_SESSION['msg'] = "Error: Collection failed";
            }
            $stmt->close();
        }
    }
    header("Location: worker_dashboard.php?search=" . urlencode($_POST['current_search'] ?? ''));
    exit();
}

// Handle End of Day Closing
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_day'])) {
    $worker_id = $_SESSION['user_id'];
    $closing_date = date('Y-m-d');
    $cash_submitted = floatval($_POST['cash_submitted']);
    $notes = trim($_POST['notes']);

    // Check if already closed today
    $check = $conn->query("SELECT id FROM worker_closings WHERE worker_id = $worker_id AND closing_date = '$closing_date'");
    if($check->num_rows > 0) {
        $_SESSION['msg'] = "Error: You have already closed for today";
    } else {
        // Get today's totals
        $totals = $conn->query("
            SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
            FROM transactions
            WHERE collected_by = $worker_id
            AND DATE(txn_date) = '$closing_date'
            AND status = 'pending'
        ")->fetch_assoc();

        if($totals['total'] == 0) {
            $_SESSION['msg'] = "Error: No collections to close for today";
        } else {
            $stmt = $conn->prepare("INSERT INTO worker_closings (worker_id, closing_date, total_collected, txn_count, cash_submitted, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdids", $worker_id, $closing_date, $totals['total'], $totals['count'], $cash_submitted, $notes);

            if($stmt->execute()) {
                $_SESSION['msg'] = "Success: End of day submitted for approval";
            } else {
                $_SESSION['msg'] = "Error: Closing failed";
            }
            $stmt->close();
        }
    }
    header("Location: worker_dashboard.php");
    exit();
}

// Check if closed today
$today_closed = $conn->query("
    SELECT * FROM worker_closings
    WHERE worker_id = {$_SESSION['user_id']}
    AND closing_date = CURDATE()
")->fetch_assoc();

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_results = null;

if(!empty($search) && !$today_closed) {
    $search_esc = $conn->real_escape_string($search);
    $search_results = $conn->query("
        SELECT s.*, m.full_name, m.phone
        FROM savings_accounts s
        JOIN members m ON s.member_id = m.id
        WHERE s.daily_rate > 0
        AND (s.account_number LIKE '%$search_esc%'
             OR m.full_name LIKE '%$search_esc%'
             OR m.phone LIKE '%$search_esc%')
        ORDER BY m.full_name
        LIMIT 10
    ");
}

// Today's collections - pending only
$today_total_result = $conn->query("
    SELECT COALESCE(SUM(t.amount), 0) as total, COUNT(*) as count
    FROM transactions t
    WHERE t.collected_by = {$_SESSION['user_id']}
    AND DATE(t.txn_date) = CURDATE()
    AND t.txn_type = 'Deposit'
    AND t.status = 'pending'
");
$today_stats = $today_total_result->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Field Collection</h4>
        <small class="text-muted"><?= date('l, d F Y') ?></small>
    </div>
    <div class="text-end">
        <span class="badge bg-primary fs-6"><?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div class="alert alert-info alert-dismissible fade show shadow-sm">
    <?= htmlspecialchars($_SESSION['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg']); endif; ?>

<?php if($today_closed): ?>
<div class="alert alert-<?= $today_closed['status'] == 'approved' ? 'success' : ($today_closed['status'] == 'rejected' ? 'danger' : 'warning') ?> shadow-sm">
    <h5 class="alert-heading">
        <i class="bi bi-<?= $today_closed['status'] == 'approved' ? 'check-circle' : ($today_closed['status'] == 'rejected' ? 'x-circle' : 'clock') ?>"></i>
        Day Closed - <?= ucfirst($today_closed['status']) ?>
    </h5>
    <hr>
    <div class="row">
        <div class="col-4"><strong>Total Collected:</strong><br>GHS <?= number_format($today_closed['total_collected'], 2) ?></div>
        <div class="col-4"><strong>Cash Submitted:</strong><br>GHS <?= number_format($today_closed['cash_submitted'], 2) ?></div>
        <div class="col-4"><strong>Shortfall:</strong><br>
            <span class="<?= $today_closed['shortfall'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                GHS <?= number_format($today_closed['shortfall'], 2) ?>
            </span>
        </div>
    </div>
    <?php if($today_closed['status'] == 'rejected' && $today_closed['rejection_reason']): ?>
    <hr><strong>Reason:</strong> <?= htmlspecialchars($today_closed['rejection_reason']) ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-6">
        <div class="card text-bg-success shadow">
            <div class="card-body text-center py-3">
                <h6 class="mb-0 opacity-75">Today's Collections</h6>
                <h3 class="mb-0">GHS <?= number_format($today_stats['total'], 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card text-bg-info shadow">
            <div class="card-body text-center py-3">
                <h6 class="mb-0 opacity-75">Transactions</h6>
                <h3 class="mb-0"><?= $today_stats['count'] ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if(!$today_closed && $today_stats['total'] > 0): ?>
<div class="card shadow border-0 mb-3 border-warning" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> End of Day Closing</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Total Collected Today</label>
                    <input type="text" class="form-control form-control-lg" value="GHS <?= number_format($today_stats['total'], 2) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cash You're Submitting <span class="text-danger">*</span></label>
                    <input type="number" name="cash_submitted" class="form-control form-control-lg" step="0.01"
                           value="<?= $today_stats['total'] ?>" required>
                    <small class="text-muted">Enter actual cash amount</small>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes (Optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any issues, shortfall explanation, etc"></textarea>
            </div>
            <button type="submit" name="close_day" class="btn btn-warning btn-lg w-100"
                    onclick="return confirm('Submit end of day? You cannot collect more after closing.')">
                <i class="bi bi-lock-fill"></i> Close Day & Submit for Approval
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(!$today_closed): ?>
<div class="card shadow border-0 mb-3" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text"
                       name="search"
                       class="form-control form-control-lg"
                       placeholder="Account number, name or phone"
                       value="<?= htmlspecialchars($search) ?>"
                       autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                Search
            </button>
            <?php if(!empty($search)): ?>
            <a href="worker_dashboard.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-x"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if(!empty($search)): ?>
<div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">
            <i class="bi bi-person-lines-fill"></i> Search Results
            <?php if($search_results): ?>
                <span class="badge bg-secondary"><?= $search_results->num_rows ?></span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if($search_results && $search_results->num_rows > 0): ?>
            <?php while($client = $search_results->fetch_assoc()): ?>
            <div class="card mb-3 border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($client['full_name']) ?></h6>
                            <small class="text-muted">
                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($client['phone']) ?><br>
                                <i class="bi bi-credit-card"></i> <?= htmlspecialchars($client['account_number']) ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info">GHS <?= number_format($client['daily_rate'], 2) ?>/day</span><br>
                            <small class="text-muted">Balance: GHS <?= number_format($client['balance'], 2) ?></small>
                        </div>
                    </div>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="account_id" value="<?= $client['id'] ?>">
                        <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
                        <input type="number" name="amount" class="form-control" step="0.01"
                               placeholder="Amount" value="<?= $client['daily_rate'] ?>" required>
                        <button type="submit" name="collect" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Record
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No clients found for "<?= htmlspecialchars($search) ?>"</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-body text-center py-5">
        <i class="bi bi-search text-primary" style="font-size: 4rem;"></i>
        <h5 class="mt-3">Search for Client</h5>
        <p class="text-muted">Enter account number, name or phone number above</p>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
@media (max-width: 768px) {
    .container.mt-4 { padding: 15px; }
    .card-body { padding: 1rem; }
    .btn-lg { padding: 0.5rem 1rem; }
}
</style>

<?php include 'includes/footer.php'; ?>
