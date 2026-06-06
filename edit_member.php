<?php
include 'config.php';
include 'includes/header.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($member_id <= 0) {
    $_SESSION['msg'] = "Error: Invalid member ID";
    header("Location: members.php");
    exit();
}

// Get member + account info
$stmt = $conn->prepare("
    SELECT m.*, s.id as account_id, s.account_number, s.daily_rate, s.balance
    FROM members m
    LEFT JOIN savings_accounts s ON m.id = s.member_id
    WHERE m.id = ?
");
if(!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$member) {
    $_SESSION['msg'] = "Error: Member not found";
    header("Location: members.php");
    exit();
}

// Update member
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $id_number = trim($_POST['id_number']);
    $daily_rate = floatval($_POST['daily_rate']);

    if(empty($full_name)) {
        $_SESSION['msg'] = "Error: Full name is required";
    } elseif(empty($phone)) {
        $_SESSION['msg'] = "Error: Phone is required";
    } elseif(!preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
        $_SESSION['msg'] = "Error: Invalid phone number format";
    } elseif($daily_rate < 0) {
        $_SESSION['msg'] = "Error: Daily rate cannot be negative";
    } else {
        // Check if phone exists for another member
        $check = $conn->prepare("SELECT id FROM members WHERE phone = ? AND id != ?");
        if(!$check) {
            $_SESSION['msg'] = "Error: Database error - " . $conn->error;
        } else {
            $check->bind_param("si", $phone, $member_id);
            $check->execute();
            if($check->get_result()->num_rows > 0) {
                $_SESSION['msg'] = "Error: Phone number already exists for another member";
                $check->close();
            } else {
                $check->close();

                $conn->begin_transaction();
                try {
                    // 1. Update member - NO EMAIL COLUMN
                    $stmt = $conn->prepare("UPDATE members SET full_name=?, phone=?, address=?, id_number=? WHERE id=?");
                    if(!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("ssssi", $full_name, $phone, $address, $id_number, $member_id);
                    $stmt->execute();
                    $stmt->close();

                    // 2. Update daily_rate if account exists
                    if($member['account_id']) {
                        $stmt2 = $conn->prepare("UPDATE savings_accounts SET daily_rate=? WHERE id=?");
                        if(!$stmt2) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        $stmt2->bind_param("di", $daily_rate, $member['account_id']);
                        $stmt2->execute();
                        $stmt2->close();
                    }

                    $conn->commit();
                    $_SESSION['msg'] = "Member updated successfully";
                    header("Location: members.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['msg'] = "Error: Failed to update member. " . $e->getMessage();
                    error_log("Edit member error: " . $e->getMessage());
                }
            }
        }
    }

    // Refresh data after failed update
    $stmt = $conn->prepare("
        SELECT m.*, s.id as account_id, s.account_number, s.daily_rate, s.balance
        FROM members m
        LEFT JOIN savings_accounts s ON m.id = s.member_id
        WHERE m.id = ?
    ");
    if($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Edit Member</h3>
    <?php if($member['account_number']): ?>
        <span class="badge bg-dark fs-6"><?= htmlspecialchars($member['account_number']) ?></span>
    <?php endif; ?>
</div>

<form method="POST" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Full Name *</label>
        <input name="full_name" class="form-control" value="<?= htmlspecialchars($member['full_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone *</label>
        <input name="phone" class="form-control" value="<?= htmlspecialchars($member['phone'] ?? '') ?>" placeholder="0244123456" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Ghana Card / ID Number</label>
        <input name="id_number" class="form-control" value="<?= htmlspecialchars($member['id_number'] ?? '') ?>" placeholder="GHA-123456789-0">
    </div>
    <div class="col-md-6">
        <label class="form-label">Address</label>
        <input name="address" class="form-control" value="<?= htmlspecialchars($member['address'] ?? '') ?>">
    </div>

    <?php if($member['account_id']): ?>
    <div class="col-md-6">
        <label class="form-label">Daily Savings Rate (GHS)</label>
        <input name="daily_rate" type="number" step="0.01" min="0" class="form-control"
               value="<?= htmlspecialchars($member['daily_rate'] ?? 0) ?>" placeholder="0.00">
        <small class="text-muted">Set to 0 if member is not on daily collection. Current balance: GHS <?= number_format($member['balance'] ?? 0, 2) ?></small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Account Number</label>
        <input class="form-control" value="<?= htmlspecialchars($member['account_number']) ?>" disabled>
        <small class="text-muted">Account number cannot be changed</small>
    </div>
    <?php else: ?>
        <input type="hidden" name="daily_rate" value="0">
        <div class="col-12">
            <div class="alert alert-warning">This member has no savings account yet.</div>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Update Member</button>
        <a href="members.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
