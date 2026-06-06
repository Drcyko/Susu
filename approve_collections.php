<?php
include 'config.php';
include 'includes/header.php';

// Only admin/manager can approve
if(!in_array($_SESSION['role'], ['admin', 'manager'])) {
    $_SESSION['msg'] = "Error: Access denied";
    header("Location: index.php");
    exit();
}

// Handle approval
if(isset($_POST['approve'])) {
    $closing_id = intval($_POST['closing_id']);
    $closing = $conn->query("SELECT * FROM worker_closings WHERE id = $closing_id AND status = 'pending'")->fetch_assoc();

    if($closing) {
        $conn->begin_transaction();
        try {
            // Approve the closing
            $stmt = $conn->prepare("UPDATE worker_closings SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $closing_id);
            $stmt->execute();
            $stmt->close();

            // Approve all pending transactions for this worker on this date
            $conn->query("
                UPDATE transactions t
                SET t.status = 'approved'
                WHERE t.collected_by = {$closing['worker_id']}
                AND DATE(t.txn_date) = '{$closing['closing_date']}'
                AND t.status = 'pending'
            ");

            // Update account balances for all approved transactions
            $conn->query("
                UPDATE savings_accounts s
                JOIN transactions t ON s.id = t.account_id
                SET s.balance = s.balance + t.amount,
                    s.last_collection_date = CURDATE()
                WHERE t.collected_by = {$closing['worker_id']}
                AND DATE(t.txn_date) = '{$closing['closing_date']}'
                AND t.status = 'approved'
                AND t.txn_type = 'Deposit'
            ");

            $conn->commit();
            $_SESSION['msg'] = "Success: Closing approved - GHS " . number_format($closing['total_collected'], 2);
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Error: Approval failed - " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "Error: Closing not found or already processed";
    }
    header("Location: approve_collections.php");
    exit();
}

// Handle rejection
if(isset($_POST['reject'])) {
    $closing_id = intval($_POST['closing_id']);
    $reason = trim($_POST['reason']);

    $stmt = $conn->prepare("UPDATE worker_closings SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("isi", $_SESSION['user_id'], $reason, $closing_id);

    if($stmt->execute()) {
        $_SESSION['msg'] = "Closing rejected";
    } else {
        $_SESSION['msg'] = "Error: Rejection failed";
    }
    $stmt->close();
    header("Location: approve_collections.php");
    exit();
}

// Get pending closings
$pending = $conn->query("
    SELECT wc.*, u.username, u.full_name
    FROM worker_closings wc
    JOIN users u ON wc.worker_id = u.id
    WHERE wc.status = 'pending'
    ORDER BY wc.created_at DESC
");
?>

<h3 class="mb-4"><i class="bi bi-check2-square"></i> Approve Worker Closings</h3>

<?php if(isset($_SESSION['msg'])): ?>
<div class="alert alert-info alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg']); endif; ?>

<div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0">Pending Closings <span class="badge bg-warning text-dark"><?= $pending->num_rows ?></span></h5>
    </div>
    <div class="card-body">
        <?php if($pending->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Worker</th>
                        <th class="text-end">Total Collected</th>
                        <th class="text-end">Cash Submitted</th>
                        <th class="text-end">Shortfall</th>
                        <th class="text-center">Txns</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($row['closing_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                            <small class="text-muted">@<?= htmlspecialchars($row['username']) ?></small>
                        </td>
                        <td class="text-end fw-bold">GHS <?= number_format($row['total_collected'], 2) ?></td>
                        <td class="text-end">GHS <?= number_format($row['cash_submitted'], 2) ?></td>
                        <td class="text-end <?= $row['shortfall'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            GHS <?= number_format($row['shortfall'], 2) ?>
                        </td>
                        <td class="text-center"><span class="badge bg-info"><?= $row['txn_count'] ?></span></td>
                        <td class="text-center">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="closing_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="approve" class="btn btn-sm btn-success"
                                        onclick="return confirm('Approve GHS <?= number_format($row['total_collected'], 2) ?> for <?= htmlspecialchars($row['username']) ?>?')">
                                    <i class="bi bi-check"></i> Approve
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $row['id'] ?>">
                                <i class="bi bi-x"></i> Reject
                            </button>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Closing</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="closing_id" value="<?= $row['id'] ?>">
                                                <div class="alert alert-warning">
                                                    <strong>Worker:</strong> <?= htmlspecialchars($row['full_name']) ?><br>
                                                    <strong>Amount:</strong> GHS <?= number_format($row['total_collected'], 2) ?><br>
                                                    <strong>Shortfall:</strong> GHS <?= number_format($row['shortfall'], 2) ?>
                                                </div>
                                                <label class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                                                <textarea name="reason" class="form-control" rows="3" required
                                                          placeholder="e.g., Cash shortfall, missing receipts, etc"></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reject" class="btn btn-danger">Reject Closing</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php if($row['notes']): ?>
                    <tr class="table-light">
                        <td colspan="7"><small><strong>Worker Notes:</strong> <?= htmlspecialchars($row['notes']) ?></small></td>
                    </tr>
                    <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
            <p class="text-muted mt-2">No pending closings</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
