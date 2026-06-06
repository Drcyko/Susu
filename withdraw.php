<?php
include 'config.php';

// Must be logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only allow POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: members.php");
    exit();
}

$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

// Validate input
if($account_id <= 0) {
    $_SESSION['msg'] = "Error: Invalid account";
    header("Location: members.php");
    exit();
}

if($amount <= 0) {
    $_SESSION['msg'] = "Error: Enter an amount greater than 0";
    header("Location: members.php");
    exit();
}

// Get current balance and check account exists
$stmt = $conn->prepare("SELECT id, balance FROM savings_accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    $_SESSION['msg'] = "Error: Account not found";
    header("Location: members.php");
    exit();
}

$account = $result->fetch_assoc();
$current_balance = $account['balance'];
$stmt->close();

// Check sufficient balance
if($amount > $current_balance) {
    $_SESSION['msg'] = "Error: Insufficient balance. Available: GHS " . number_format($current_balance, 2);
    header("Location: members.php");
    exit();
}

// Use transaction to ensure both queries run or none
$conn->begin_transaction();

try {
    // 1. Update balance
    $stmt1 = $conn->prepare("UPDATE savings_accounts SET balance = balance - ? WHERE id = ? AND balance >= ?");
    $stmt1->bind_param("did", $amount, $account_id, $amount);
    $stmt1->execute();

    if($stmt1->affected_rows === 0) {
        throw new Exception("Balance update failed or insufficient funds");
    }
    $stmt1->close();

    // 2. Log transaction
    $desc = 'Cash withdrawal by ' . $_SESSION['username'];
    $stmt2 = $conn->prepare("INSERT INTO transactions (account_id, amount, txn_type, description) VALUES (?, ?, 'Withdrawal', ?)");
    $stmt2->bind_param("ids", $account_id, $amount, $desc);
    $stmt2->execute();

    if($stmt2->affected_rows === 0) {
        throw new Exception("Transaction log failed");
    }
    $stmt2->close();

    $conn->commit();
    $_SESSION['msg'] = "Withdrew GHS " . number_format($amount, 2) . " successfully";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['msg'] = "Error: Withdrawal failed. " . $e->getMessage();
}

header("Location: members.php");
exit();
?>
