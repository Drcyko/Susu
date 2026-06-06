<?php
include 'config.php';
checkLogin();
$result = $conn->query("SELECT * FROM members ORDER BY fullname");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Family List - ADWENADZE EBUSUA</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #1e40af; text-align: center; margin-bottom: 5px; }
        .date { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background: #3b82f6; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f1f5f9; }
        .status-alive { color: #059669; font-weight: bold; }
        .status-deceased { color: #dc2626; font-weight: bold; }
        .gender-m { color: #2563eb; }
        .gender-f { color: #db2777; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <h2>ADWENADZE EBUSUA - APAM</h2>
    <div class="date">Family Members List - <?php echo date("M d, Y"); ?></div>

    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Birthday</th>
            <th>Relationship</th>
            <th>Family Position</th>
            <th>Phone</th>
            <th>WhatsApp</th>
            <th>Status</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['family_code'] ?? 'ADE' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)) ?></td>
            <td><?= htmlspecialchars($row['fullname']) ?></td>
            <td class="gender-<?= strtolower(substr($row['gender'] ?? '', 0, 1)) ?>">
                <?= htmlspecialchars($row['gender'] ?? '-') ?>
            </td>
            <td><?= $row['age'] ?></td>
            <td><?= $row['birthdate'] ? date("M d, Y", strtotime($row['birthdate'])) : '-' ?></td>
            <td><?= htmlspecialchars($row['relationship']) ?></td>
            <td><?= htmlspecialchars($row['family_position'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['whatsapp_number'] ?? '-') ?></td>
            <td class="status-<?= strtolower($row['status'] ?? 'alive') ?>">
                <?= ucfirst($row['status'] ?? 'alive') ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()">Print Again</button>
        <a href="index.php" style="margin-left: 10px;">Back to Dashboard</a>
    </div>
</body>
</html>
