<?php
include 'config.php';
include 'includes/header.php';

// Handle new loan application
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_loan'])) {
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $principal = isset($_POST['principal']) ? floatval($_POST['principal']) : 0;
    $rate = isset($_POST['interest_rate']) ? floatval($_POST['interest_rate']) : 0;
    $tenure = isset($_POST['tenure_months']) ? intval($_POST['tenure_months']) : 0;

    // Validation
    if($member_id <= 0) {
        $_SESSION['msg'] = "Error: Please select a member";
    } elseif($principal <= 0) {
        $_SESSION['msg'] = "Error: Principal must be greater than 0";
    } elseif($rate <= 0 || $rate > 100) {
        $_SESSION['msg'] = "Error: Interest rate must be between 0.01 and 100";
    } elseif($tenure <= 0 || $tenure > 120) {
        $_SESSION['msg'] = "Error: Tenure must be between 1 and 120 months";
    } else {
        // Check if member exists
        $check = $conn->prepare("SELECT id FROM members WHERE id = ?");
        $check->bind_param("i", $member_id);
        $check->execute();
        if($check->get_result()->num_rows === 0) {
            $_SESSION['msg'] = "Error: Member not found";
        } else {
            // Simple interest calculation
            $interest_amount = $principal * ($rate / 100) * ($tenure / 12);
            $amount_due = $principal + $interest_amount;
            $due_date = date('Y-m-d', strtotime("+$tenure months"));

            $stmt = $conn->prepare("INSERT INTO loans (member_id, principal, interest_rate, tenure_months, amount_due, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("iddids", $member_id, $principal, $rate, $tenure, $amount_due, $due_date);

            if($stmt->execute()) {
                $_SESSION['msg'] = "Loan application submitted for approval";
            } else {
                $_SESSION['msg'] = "Error: Failed to submit loan application";
            }
            $stmt->close();
        }
        $check->close();
    }

    header("Location: loans.php");
    exit();
}

// Approve loan - only Admin/Manager can approve
if(isset($_GET['approve'])) {
    if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager')) {
        $_SESSION['msg'] = "Error: You don't have permission to approve loans";
        header("Location: loans.php");
        exit();
    }

    $loan_id = intval($_GET['approve']);

    $check = $conn->prepare("SELECT id, status FROM loans WHERE id = ?");
    $check->bind_param("i", $loan_id);
    $check->execute();
    $loan_result = $check->get_result();

    if($loan_result->num_rows === 0) {
        $_SESSION['msg'] = "Error: Loan not found";
    } else {
        $loan = $loan_result->fetch_assoc();
        if($loan['status'] !== 'Pending') {
            $_SESSION['msg'] = "Error: Only pending loans can be approved";
        } else {
            $stmt = $conn->prepare("UPDATE loans SET status='Approved', disbursement_date=CURDATE() WHERE id=?");
            $stmt->bind_param("i", $loan_id);
            if($stmt->execute()) {
                $_SESSION['msg'] = "Loan approved and ready for disbursement";
            } else {
                $_SESSION['msg'] = "Error: Failed to approve loan";
            }
            $stmt->close();
        }
    }
    $check->close();

    header("Location: loans.php");
    exit();
}

// Reject loan
if(isset($_GET['reject'])) {
    if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager')) {
        $_SESSION['msg'] = "Error: You don't have permission to reject loans";
        header("Location: loans.php");
        exit();
    }

    $loan_id = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE loans SET status='Closed' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    if($stmt->affected_rows > 0) {
        $_SESSION['msg'] = "Loan application rejected";
    } else {
        $_SESSION['msg'] = "Error: Cannot reject this loan";
    }
    $stmt->close();

    header("Location: loans.php");
    exit();
}

// Get members for dropdown
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name");

// Get all loans - remove ORDER BY created_at if column doesn't exist
$loans = $conn->query("
    SELECT l.*, m.full_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    ORDER BY l.id DESC
");
?>

<h3>Apply for Loan</h3>
<form method="POST" class="row g-3 mb-4">
  <div class="col-md-3">
    <select name="member_id" class="form-control" required>
      <option value="">Select Member</option>
      <?php if($members && $members->num_rows > 0): ?>
        <?php while($m = $members->fetch_assoc()): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
        <?php endwhile; ?>
      <?php endif; ?>
    </select>
  </div>
  <div class="col-md-2">
    <input name="principal" type="number" step="0.01" min="1" class="form-control" placeholder="Principal GHS" required>
  </div>
  <div class="col-md-2">
    <input name="interest_rate" type="number" step="0.01" min="0.01" max="100" class="form-control" placeholder="Rate % p.a" required>
  </div>
  <div class="col-md-2">
    <input name="tenure_months" type="number" min="1" max="120" class="form-control" placeholder="Tenure Months" required>
  </div>
  <div class="col-md-3">
    <button name="apply_loan" class="btn btn-primary w-100">Submit Application</button>
  </div>
</form>

<h3>All Loans</h3>
<div class="table-responsive">
<table class="table table-bordered table-hover">
  <thead class="table-dark">
    <tr>
      <th>Member</th>
      <th>Principal</th>
      <th>Rate</th>
      <th>Tenure</th>
      <th>Amount Due</th>
      <th>Paid</th>
      <th>Balance</th>
      <th>Due Date</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
  <?php if($loans && $loans->num_rows > 0): ?>
    <?php while($l = $loans->fetch_assoc()):
      $balance = $l['amount_due'] - $l['amount_paid'];
      $status_class = match($l['status']) {
          'Approved' => 'success',
          'Pending' => 'warning',
          'Active' => 'info',
          'Closed' => 'secondary',
          'Defaulted' => 'danger',
          default => 'secondary'
      };
    ?>
    <tr>
      <td><?= htmlspecialchars($l['full_name']) ?></td>
      <td>GHS <?= number_format($l['principal'], 2) ?></td>
      <td><?= $l['interest_rate'] ?>%</td>
      <td><?= $l['tenure_months'] ?> mo</td>
      <td>GHS <?= number_format($l['amount_due'], 2) ?></td>
      <td>GHS <?= number_format($l['amount_paid'], 2) ?></td>
      <td>GHS <?= number_format($balance, 2) ?></td>
      <td><?= $l['due_date'] ? date('d M Y', strtotime($l['due_date'])) : '-' ?></td>
      <td><span class="badge bg-<?= $status_class ?>"><?= $l['status'] ?></span></td>
      <td>
        <?php if($l['status']=='Pending' && isset($_SESSION['role']) && ($_SESSION['role']=='Admin' || $_SESSION['role']=='Manager')): ?>
          <a href="loans.php?approve=<?= $l['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this loan?')">Approve</a>
          <a href="loans.php?reject=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this loan?')">Reject</a>
        <?php endif; ?>
        <a href="loan_details.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-info">Details</a>
      </td>
    </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr><td colspan="10" class="text-center text-muted">No loans found</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
