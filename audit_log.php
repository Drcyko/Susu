<?php
include 'config.php';
include 'includes/header.php';

// Only admin can view audit logs
if($_SESSION['role'] !== 'admin') {
    $_SESSION['msg'] = "Error: Access denied";
    header("Location: index.php");
    exit();
}

$logs = $conn->query("
    SELECT * FROM audit_log
    ORDER BY created_at DESC
    LIMIT 100
");
?>

<h3><i class="bi bi-file-earmark-text"></i> Audit Trail</h3>
<p class="text-muted">BOG Compliance - All user actions logged</p>

<div class="card shadow border-0" style="background-color: rgba(255,255,255,0.98);">
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><small><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></small></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><span class="badge bg-primary"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td><code><?= htmlspecialchars($log['table_name'] ?? '-') ?></code></td>
                    <td><?= $log['record_id'] ?? '-' ?></td>
                    <td><small><?= htmlspecialchars($log['ip_address']) ?></small></td>
                    <td>
                        <?php if($log['new_values']): ?>
                            <button class="btn btn-sm btn-outline-info" onclick='alert(<?= json_encode(json_decode($log['new_values'], true)) ?>)'>
                                <i class="bi bi-eye"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
