<?php
include 'config.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$txn_id = isset($_GET['txn_id']) ? intval($_GET['txn_id']) : 0;

$stmt = $conn->prepare("
    SELECT t.*, m.full_name, m.phone, s.account_number, u.full_name as teller_name
    FROM transactions t
    JOIN savings_accounts s ON t.account_id = s.id
    JOIN members m ON s.member_id = m.id
    JOIN users u ON u.id = ?
    WHERE t.id = ?
");
$stmt->bind_param("ii", $_SESSION['user_id'], $txn_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$txn) die("Transaction not found");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $txn_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } }
        body { font-family: monospace; }
        .receipt { max-width: 400px; margin: 20px auto; border: 2px dashed #000; padding: 20px; }
    </style>
</head>
<body>
<div class="receipt">
    <div class="text-center">
        <h4>S&L MANAGER</h4>
        <p class="mb-1">Accra, Ghana</p>
        <hr>
        <h5>TRANSACTION RECEIPT</h5>
        <p>Receipt #: <?= str_pad($txn_id, 6, '0', STR_PAD_LEFT) ?></p>
    </div>
    <hr>
    <table class="table table-sm table-borderless">
        <tr><td>Date:</td><td><?= date('d M Y H:i', strtotime($txn['txn_date'])) ?></td></tr>
        <tr><td>Member:</td><td><?= htmlspecialchars($txn['full_name']) ?></td></tr>
        <tr><td>Phone:</td><td><?= htmlspecialchars($txn['phone']) ?></td></tr>
        <tr><td>Account:</td><td><?= htmlspecialchars($txn['account_number']) ?></td></tr>
        <tr><td>Type:</td><td><strong><?= $txn['txn_type'] ?></strong></td></tr>
        <tr><td>Amount:</td><td><strong>GHS <?= number_format($txn['amount'], 2) ?></strong></td></tr>
        <tr><td>Description:</td><td><?= htmlspecialchars($txn['description']) ?></td></tr>
        <tr><td>Teller:</td><td><?= htmlspecialchars($txn['teller_name']) ?></td></tr>
    </table>
    <hr>
    <p class="text-center small">Thank you for banking with us!</p>
    <div class="no-print text-center mt-3">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
</div>
</body>
</html>
