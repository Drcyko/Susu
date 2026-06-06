<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';
checkLogin();

$message = "";

// Check if whatsapp_number column exists
$check_column = $conn->query("SHOW COLUMNS FROM members LIKE 'whatsapp_number'");
$has_whatsapp = $check_column->num_rows > 0;

// Handle form submission
if (isset($_POST['send'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $recipient = $_POST['recipient'];

    if ($has_whatsapp) {
        if ($recipient == 'all') {
            $sql = "SELECT whatsapp_number, fullname FROM members WHERE whatsapp_number != '' AND whatsapp_number IS NOT NULL";
        } else {
            $sql = "SELECT whatsapp_number, fullname FROM members WHERE id = " . intval($recipient);
        }

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $count = $result->num_rows;
            $numbers = [];
            while($row = $result->fetch_assoc()) {
                $numbers[] = $row['whatsapp_number'] . ' - ' . $row['fullname'];
            }
            $message = "<div class='success'>Announcement prepared for " . $count . " member(s)!<br><small>" . implode('<br>', $numbers) . "</small></div>";
        } else {
            $message = "<div class='error'>No WhatsApp numbers found. Add WhatsApp numbers in Edit Member first.</div>";
        }
    } else {
        $message = "<div class='error'>WhatsApp column missing. Run: ALTER TABLE members ADD COLUMN whatsapp_number VARCHAR(20);</div>";
    }
}

// Get all members for dropdown
if ($has_whatsapp) {
    $members = $conn->query("SELECT id, fullname, whatsapp_number FROM members WHERE whatsapp_number != '' AND whatsapp_number IS NOT NULL ORDER BY fullname ASC");
} else {
    $members = false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Send Announcement - ADWENADZE EBUSUA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            margin: 0; padding: 20px; min-height: 100vh; color: #e2e8f0;
        }
        .container {
            max-width: 700px; margin: auto; background: #1e293b;
            padding: 30px; border-radius: 12px; border: 1px solid #334155;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        h2 {
            color: #93c5fd;
            margin-top: 0;
            text-align: center;
            font-size: 24px;
        }
        .header-line {
            text-align: center;
            color: #fbbf24;
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3b82f6;
        }
        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 6px;
            color: #cbd5e1;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #334155;
            border-radius: 6px;
            background: #0f172a;
            color: #e2e8f0;
            box-sizing: border-box;
            font-size: 15px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        button {
            margin-top: 25px;
            padding: 14px 28px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: 0.2s;
        }
        button:hover { background: #047857; transform: translateY(-2px); }
        button:disabled { background: #475569; cursor: not-allowed; }
        .back-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 24px;
            background: #475569;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            width: 100%;
        }
        .back-btn:hover { background: #334155; }
        .success {
            background: #14532d;
            color: #bbf7d0;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #22c55e;
        }
        .error {
            background: #7f1d1d;
            color: #fecaca;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }
        .preview {
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .preview-title {
            color: #93c5fd;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .wa-preview {
            background: #075e54;
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }
        .wa-header {
            color: #fbbf24;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .wa-footer {
            color: #d1fae5;
            font-size: 12px;
            margin-top: 10px;
            font-style: italic;
        }
        small { font-size: 12px; opacity: 0.8; }
    </style>
</head>
<body>
<div class="container">
    <h2>📢 Send Family Announcement</h2>
    <div class="header-line">ADWENADZE EBUSUA - APAM</div>

    <?php echo $message; ?>

    <?php if (!$has_whatsapp): ?>
        <div class='error'>
            <strong>Setup Required:</strong><br>
            Run this SQL in phpMyAdmin first:<br>
            <code>ALTER TABLE members ADD COLUMN whatsapp_number VARCHAR(20);</code>
        </div>
    <?php endif; ?>

    <form method="POST" id="announcementForm">
        <label>Announcement Title:</label>
        <input type="text" name="title" id="title" placeholder="e.g. Family Meeting, Birthday, Funeral" required>

        <label>Message Content:</label>
        <textarea name="content" id="content" rows="6" placeholder="Type your announcement message here..." required></textarea>

        <label>Send To:</label>
        <select name="recipient" required <?php echo !$has_whatsapp ? 'disabled' : ''; ?>>
            <option value="all">All Members with WhatsApp</option>
            <?php if ($members && $members->num_rows > 0): ?>
                <?php while($m = $members->fetch_assoc()): ?>
                    <option value="<?php echo $m['id']; ?>">
                        <?php echo htmlspecialchars($m['fullname']); ?> (<?php echo htmlspecialchars($m['whatsapp_number']); ?>)
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <button type="submit" name="send" <?php echo !$has_whatsapp ? 'disabled' : ''; ?>>📤 Send Announcement</button>
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
    </form>

    <div class="preview">
        <div class="preview-title">📱 WhatsApp Preview:</div>
        <div class="wa-preview" id="preview">
            <div class="wa-header">*ADWENADZE EBUSUA - APAM*</div>
            <div id="preview-title">*Title goes here*</div>
            <div id="preview-content" style="margin-top:8px;">Your message content will appear here...</div>
            <div class="wa-footer">- Ebusuapanyin Kwame Atta</div>
        </div>
    </div>
</div>

<script>
document.getElementById('title').addEventListener('input', updatePreview);
document.getElementById('content').addEventListener('input', updatePreview);

function updatePreview() {
    const title = document.getElementById('title').value || 'Title goes here';
    const content = document.getElementById('content').value || 'Your message content will appear here...';

    document.getElementById('preview-title').innerHTML = '*' + title + '*';
    document.getElementById('preview-content').innerHTML = content.replace(/\n/g, '<br>');
}
</script>
</body>
</html>
