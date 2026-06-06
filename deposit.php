<?php
include 'config.php';

// Redirect to login if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: members.php");
    exit();
}

$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

// Validation
if($account_id <= 0) {
    $_SESSION['msg'] = "Error: Invalid account selected";
    header("Location: members.php");
    exit();
}

if($amount <= 0) {
    $_SESSION['msg'] = "Error: Amount must be greater than 0";
    header("Location: members.php");
    exit();
}

if($amount > 99999999.99) {
    $_SESSION['msg'] = "Error: Amount too large";
    header("Location: members.php");
    exit();
}

// Check if account exists and get member info + daily_rate
$check = $conn->prepare("
    SELECT s.id, s.balance, s.daily_rate, m.full_name
    FROM savings_accounts s
    JOIN members m ON s.member_id = m.id
    WHERE s.id = ?
");
$check->bind_param("i", $account_id);
$check->execute();
$result = $check->get_result();

if($result->num_rows === 0) {
    $_SESSION['msg'] = "Error: Account not found";
    header("Location: members.php");
    exit();
}

$account_data = $result->fetch_assoc();
$check->close();

// Start transaction for safety
$conn->begin_transaction();

try {
    // 1. Update balance using prepared statement
    $stmt1 = $conn->prepare("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?");
    $stmt1->bind_param("di", $amount, $account_id);
    $stmt1->execute();

    if($stmt1->affected_rows === 0) {
        throw new Exception("Failed to update balance");
    }
    $stmt1->close();

    // 2. Update last_collection_date if this is a daily saver
    if($account_data['daily_rate'] > 0) {
        $stmt3 = $conn->prepare("UPDATE savings_accounts SET last_collection_date = CURDATE() WHERE id = ?");
        $stmt3->bind_param("i", $account_id);
        $stmt3->execute();
        $stmt3->close();
    }

    // 3. Log transaction using prepared statement
    $desc = "Cash deposit";
    if($account_data['daily_rate'] > 0) {
        $desc = "Daily collection";
        if($amount > $account_data['daily_rate']) {
            $desc = "Daily collection + Extra";
        } elseif($amount < $account_data['daily_rate']) {
            $desc = "Partial daily collection";
        }
    }

    $stmt2 = $conn->prepare("INSERT INTO transactions (account_id, amount, txn_type, description) VALUES (?, ?, 'Deposit', ?)");
    $stmt2->bind_param("ids", $account_id, $amount, $desc);
    $stmt2->execute();

    if($stmt2->affected_rows === 0) {
        throw new Exception("Failed to log transaction");
    }

    $txn_id = $stmt2->insert_id;
    $stmt2->close();

    // Commit if all queries succeeded
    $conn->commit();
    $_SESSION['msg'] = "Deposited GHS " . number_format($amount, 2) . " to " . htmlspecialchars($account_data['full_name']) . " successfully. <a href='receipt.php?txn_id=$txn_id' target='_blank' class='alert-link'>Print Receipt</a>";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['msg'] = "Error: Deposit failed. Please try again";
    error_log("Deposit error: " . $e->getMessage());
}

header("Location: members.php");
exit();
?>
