<?php
include 'config.php';
include 'includes/header.php';

// Only admin/manager can access
if(!in_array($_SESSION['role'], ['admin', 'manager'])) {
    $_SESSION['msg'] = "Error: Access denied";
    header("Location: index.php");
    exit();
}

// Check if expenses table exists
$table_check = $conn->query("SHOW TABLES LIKE 'expenses'");
if($table_check->num_rows == 0) {
    echo '<div class="alert alert-danger">
            <strong>Error:</strong> expenses table not found.
            <a href="#" onclick="alert(\'Run the SQL in phpMyAdmin first\')">Run the SQL first</a>
          </div>';
    include 'includes/footer.php';
    exit();
}

// Handle Add Expense
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_date = $_POST['expense_date'];
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $receipt_no = trim($_POST['receipt_no']);
    $created_by = $_SESSION['user_id'];

    if($amount <= 0) {
        $_SESSION['msg'] = "Error: Amount must be greater than 0";
    } elseif(empty($category) || empty($description)) {
        $_SESSION['msg'] = "Error: Category and description required";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO expenses (expense_date, category, description, amount, receipt_no, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssisi", $expense_date, $category, $description, $amount, $receipt_no, $created_by);

        if($stmt->execute()) {
            $expense_id = $conn->insert_id;

            // Log audit
            if(function_exists('log_audit')) {
                log_audit('EXPENSE_ADD', 'expenses', $expense_id, null, [
                    'category' => $category,
                    'amount' => $amount,
                    'description' => $description
                ]);
            }

            $_SESSION['msg'] = "Success: Expense recorded";
        } else {
            $_SESSION['msg'] = "Error: Could not record expense";
        }
        $stmt->close();
    }

    header("Location: expenses.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Get expense details before delete for audit
    $exp_result = $conn->query("SELECT * FROM expenses WHERE id = $id");
    $exp_data = $exp_result->fetch_assoc();

    if($conn->query("DELETE FROM expenses WHERE id = $id")) {
        if(function_exists('log_audit')) {
            log_audit('EXPENSE_DELETE', 'expenses', $id, $exp_data, null);
        }
        $_SESSION['msg'] = "Success: Expense deleted";
    } else {
        $_SESSION['msg'] = "Error: Could not delete expense";
    }
    header("Location: expenses.php");
    exit();
}

// Get filter
$filter_month = $_GET['month'] ?? date('Y-m');
$month_start = $filter_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get expenses for month
$expenses = $conn->query("
    SELECT e.*, u.username as created_by_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.expense_date BETWEEN '$month_start' AND '$month_end'
    ORDER BY e.expense_date DESC, e.id DESC
");

// Get totals
$total_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM expenses
    WHERE expense_date BETWEEN '$month_start' AND '$month_end'
");
$month_total = ($total_result && $row = $total_result->fetch_assoc()) ? $row['total'] : 0;

// Get category breakdown
$categories = $conn->query("
    SELECT category, SUM(amount) as total, COUNT(*) as count
    FROM expenses
    WHERE expense_date BETWEEN '$month_start' AND '$month_end'
    GROUP BY category
    ORDER BY total DESC
");

// Common expense categories for dropdown
$common_categories = ['Rent', 'Salaries & Wages', 'Utilities', 'Transport', 'Stationery', 'Bank Charges', 'Marketing', 'Repairs & Maintenance', 'Insurance', 'Taxes', 'Other'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3><i class="bi bi-receipt"></i> Expenses Management</h3>
        <p class="text-muted mb-0">Track operational expenses for BOG reporting</p>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-circle"></i> Add Expense
        </button>
    </div>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div class="alert alert-info alert-dismissible fade show shadow-sm">
    <?= htmlspecialchars($_SESSION['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg']); endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Expenses This Month</h6>
                <h2 class="text-danger fw-bold mb-0">GHS <?= number_format($month_total, 2) ?></h2>
                <small class="text-muted"><?= date('F Y', strtotime($month_start)) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label mb-1">Filter by Month</label>
                        <input type="month" name="month" class="form-control" value="<?= $filter_month ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="bog_report.php?date=<?= $month_start ?>" class="btn btn-outline-primary w-100">
                            <i class="bi bi-file-earmark-text"></i> BOG Report
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Expense Records</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Receipt #</th>
                            <th class="text-end">Amount</th>
                            <th>By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($expenses && $expenses->num_rows > 0): ?>
                            <?php while($exp = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($exp['category']) ?></span></td>
                                <td><?= htmlspecialchars($exp['description']) ?></td>
                                <td><small><?= htmlspecialchars($exp['receipt_no'] ?: '-') ?></small></td>
                                <td class="text-end fw-bold text-danger">GHS <?= number_format($exp['amount'], 2) ?></td>
                                <td><small><?= htmlspecialchars($exp['created_by_name']) ?></small></td>
                                <td>
                                    <a href="expenses.php?delete=<?= $exp['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this expense?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="4">TOTAL</td>
                                <td class="text-end">GHS <?= number_format($month_total, 2) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No expenses recorded for this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Category Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if($categories && $categories->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while($cat = $categories->fetch_assoc()): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($cat['category']) ?></strong>
                                    <br><small class="text-muted"><?= $cat['count'] ?> transaction(s)</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">GHS <?= number_format($cat['total'], 2) ?></div>
                                    <small class="text-muted">
                                        <?= $month_total > 0 ? number_format(($cat['total'] / $month_total) * 100, 1) : 0 ?>%
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($common_categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g., Office rent for October" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (GHS) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receipt Number</label>
                        <input type="text" name="receipt_no" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
