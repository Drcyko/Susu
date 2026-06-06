<?php
include 'config.php';

// Redirect to login if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';

// Handle new member
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $id_num = isset($_POST['id_number']) ? trim($_POST['id_number']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $daily_rate = isset($_POST['daily_rate']) ? floatval($_POST['daily_rate']) : 0;

    // Basic validation
    if(empty($name) || empty($phone)) {
        $_SESSION['msg'] = "Error: Name and Phone are required";
        header("Location: members.php");
        exit();
    }

    if(!preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
        $_SESSION['msg'] = "Error: Invalid phone number format";
        header("Location: members.php");
        exit();
    }

    if($daily_rate < 0) {
        $_SESSION['msg'] = "Error: Daily rate cannot be negative";
        header("Location: members.php");
        exit();
    }

    // Check if phone already exists
    $check = $conn->prepare("SELECT id FROM members WHERE phone = ?");
    if(!$check) {
        $_SESSION['msg'] = "Error: Database error - " . $conn->error;
        header("Location: members.php");
        exit();
    }
    $check->bind_param("s", $phone);
    $check->execute();
    if($check->get_result()->num_rows > 0) {
        $_SESSION['msg'] = "Error: Phone number already exists";
        $check->close();
        header("Location: members.php");
        exit();
    }
    $check->close();

    // Start transaction - member + account must both succeed
    $conn->begin_transaction();

    try {
        // 1. Insert member
        $stmt = $conn->prepare("INSERT INTO members (full_name, phone, address, id_number) VALUES (?, ?, ?, ?)");
        if(!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssss", $name, $phone, $address, $id_num);
        $stmt->execute();
        $member_id = $stmt->insert_id;
        $stmt->close();

        // 2. Generate unique account number
        $attempts = 0;
        do {
            $acc_num = "SAV" . rand(100000, 999999);
            $check_acc = $conn->prepare("SELECT id FROM savings_accounts WHERE account_number = ?");
            if(!$check_acc) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $check_acc->bind_param("s", $acc_num);
            $check_acc->execute();
            $exists = $check_acc->get_result()->num_rows > 0;
            $check_acc->close();
            $attempts++;
        } while($exists && $attempts < 10);

        if($exists) {
            throw new Exception("Failed to generate unique account number");
        }

        // 3. Create savings account with daily_rate
        $stmt2 = $conn->prepare("INSERT INTO savings_accounts (member_id, account_number, balance, daily_rate) VALUES (?, ?, 0.00, ?)");
        if(!$stmt2) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt2->bind_param("isd", $member_id, $acc_num, $daily_rate);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        $rate_text = $daily_rate > 0 ? " with daily rate GHS " . number_format($daily_rate, 2) : "";
        $_SESSION['msg'] = "Member " . htmlspecialchars($name) . " added successfully with account " . $acc_num . $rate_text;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Error: Failed to add member. " . $e->getMessage();
        error_log("Add member error: " . $e->getMessage());
    }

    header("Location: members.php");
    exit();
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$members = null;

if(!empty($search)) {
    $search_param = "%$search%";
    $stmt = $conn->prepare("
        SELECT m.*, s.account_number, s.balance, s.id as account_id, s.daily_rate, s.last_collection_date
        FROM members m
        LEFT JOIN savings_accounts s ON m.id = s.member_id
        WHERE m.full_name LIKE ? OR m.phone LIKE ? OR s.account_number LIKE ?
        ORDER BY m.id DESC
    ");
    if(!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $members = $stmt->get_result();
    $stmt->close();
} else {
    $members = $conn->query("
        SELECT m.*, s.account_number, s.balance, s.id as account_id, s.daily_rate, s.last_collection_date
        FROM members m
        LEFT JOIN savings_accounts s ON m.id = s.member_id
        ORDER BY m.id DESC
    ");
}
?>

<h3>Add New Member</h3>
<form method="POST" class="row g-3 mb-4">
  <div class="col-md-3">
    <input name="full_name" class="form-control" placeholder="Full Name *" required>
  </div>
  <div class="col-md-2">
    <input name="phone" class="form-control" placeholder="Phone *" required>
  </div>
  <div class="col-md-2">
    <input name="id_number" class="form-control" placeholder="Ghana Card No.">
  </div>
  <div class="col-md-2">
    <input name="address" class="form-control" placeholder="Address">
  </div>
  <div class="col-md-2">
    <input name="daily_rate" type="number" step="0.01" min="0" class="form-control" placeholder="Daily Rate GHS" value="0.00">
  </div>
  <div class="col-md-1">
    <button name="add_member" class="btn btn-primary w-100">Add</button>
  </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>All Members</h3>
    <form method="GET" class="d-flex" style="max-width: 400px;">
        <input name="search" type="text" class="form-control me-2" placeholder="Search name, phone, or account no..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        <?php if(!empty($search)): ?>
            <a href="members.php" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if(!empty($search)): ?>
    <div class="alert alert-info">
        Showing results for "<strong><?= htmlspecialchars($search) ?></strong>" - <?= $members->num_rows ?> found
    </div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>Name</th>
      <th>Phone</th>
      <th>Account No</th>
      <th>Balance</th>
      <th>Daily Rate</th>
      <th style="width: 400px;">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if($members && $members->num_rows > 0): ?>
    <?php while($row = $members->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['full_name']) ?></td>
      <td><?= htmlspecialchars($row['phone']) ?></td>
      <td><?= $row['account_number'] ? htmlspecialchars($row['account_number']) : '<span class="text-danger">No Account</span>' ?></td>
      <td>GHS <?= number_format($row['balance'] ?? 0, 2) ?></td>
      <td>
        <?php if($row['daily_rate'] > 0): ?>
            <span class="badge bg-info">GHS <?= number_format($row['daily_rate'], 2) ?>/day</span>
            <?php
            if($row['last_collection_date']) {
                $days_due = floor((time() - strtotime($row['last_collection_date'])) / 86400);
                if($days_due > 0) {
                    echo "<br><small class='text-danger'><i class='bi bi-exclamation-triangle'></i> $days_due days due</small>";
                } elseif($days_due == 0) {
                    echo "<br><small class='text-success'><i class='bi bi-check-circle'></i> Paid today</small>";
                }
            } else {
                echo "<br><small class='text-warning'>Never collected</small>";
            }
            ?>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if($row['account_id']): ?>
          <div class="d-flex gap-1 mb-1">
            <form action="deposit.php" method="POST" class="d-flex flex-grow-1">
              <input type="hidden" name="account_id" value="<?= $row['account_id'] ?>">
              <input name="amount" type="number" step="0.01" min="0.01"
                     class="form-control form-control-sm"
                     placeholder="Amount"
                     value="<?= $row['daily_rate'] > 0 ? $row['daily_rate'] : '' ?>" required>
              <button class="btn btn-sm btn-success ms-1" title="Deposit"><i class="bi bi-plus-circle"></i></button>
            </form>
          </div>
          <div class="d-flex gap-1 mb-1">
            <form action="withdraw.php" method="POST" class="d-flex flex-grow-1">
              <input type="hidden" name="account_id" value="<?= $row['account_id'] ?>">
              <input name="amount" type="number" step="0.01" min="0.01" max="<?= $row['balance'] ?>" class="form-control form-control-sm" placeholder="Amount" required>
              <button class="btn btn-sm btn-danger ms-1" title="Withdraw"><i class="bi bi-dash-circle"></i></button>
            </form>
          </div>
          <a href="edit_member.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning w-100">
            <i class="bi bi-pencil"></i> Edit Member
          </a>
        <?php else: ?>
          <span class="text-muted">No savings account</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr><td colspan="6" class="text-center text-muted">
        <?= !empty($search) ? 'No members found matching your search.' : 'No members found. Add your first member above.' ?>
    </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
