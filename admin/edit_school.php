<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

$school_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($school_id <= 0) {
    header("Location: manage_schools.php");
    exit();
}

/* ================= FETCH SCHOOL ================= */
$stmt = $conn->prepare("SELECT * FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$school) {
    header("Location: manage_schools.php");
    exit();
}

/* ================= AVAILABLE HEADTEACHERS (Unassigned) ================= */
$available_headteachers = [];
$stmt = $conn->prepare("
    SELECT user_id, name 
    FROM users 
    WHERE role = 'headteacher' 
      AND (school_id IS NULL OR school_id = 0)
    ORDER BY name ASC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $available_headteachers[] = $row;
}
$result->close();

/* ================= AUDIT LOG FUNCTION ================= */
function log_school_activity($conn, $admin_id, $school_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss", $admin_id, $school_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

/* ================= UPDATE SCHOOL ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status         = trim($_POST['status'] ?? '');
    $headteacher_id = !empty($_POST['headteacher_id']) ? (int)$_POST['headteacher_id'] : null;
    $admin_id       = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE schools SET status = ? WHERE school_id = ?");
    $stmt->bind_param("si", $status, $school_id);

    if ($stmt->execute()) {

        $changes = [];

        // Log status change
        if ($school['status'] !== $status) {
            $changes[] = "Status: {$school['status']} → {$status}";
        }

        // Handle Headteacher Re-assignment
        if ($headteacher_id) {
            // Remove previous headteacher
            $reset = $conn->prepare("UPDATE users SET school_id = NULL WHERE school_id = ? AND role = 'headteacher'");
            $reset->bind_param("i", $school_id);
            $reset->execute();
            $reset->close();

            // Assign new headteacher
            $assign = $conn->prepare("UPDATE users SET school_id = ? WHERE user_id = ?");
            $assign->bind_param("ii", $school_id, $headteacher_id);
            $assign->execute();
            $assign->close();

            $changes[] = "Re-assigned Headteacher (User ID: $headteacher_id)";
        }

        // Audit Log
        $action = "update_school";
        $details = "Updated school ID $school_id ({$school['school_name']})";
        if (!empty($changes)) {
            $details .= " | Changes: " . implode(", ", $changes);
        }

        log_school_activity($conn, $admin_id, $school_id, $action, $details);

        $message = "School updated successfully.";
        $message_type = "success";

        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM schools WHERE school_id = ?");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $school = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $message = "Failed to update school.";
        $message_type = "error";
    }
    
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit School - <?= htmlspecialchars($school['school_name']) ?></title>

    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        .readonly-input {
            background: #f1f5f9 !important;
            color: #64748b;
        }
        .info-box {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            color: #475569;
        }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Edit School</h2>
                <p class="page-subtitle">
                    Administrative settings for <strong><?= htmlspecialchars($school['school_name']) ?></strong>
                </p>
            </div>

            <div class="header-actions">
                <a href="manage_schools.php" class="btn btn-secondary btn-small">← Back</a>
                <a href="view_school.php?id=<?= $school['school_id'] ?>" class="btn btn-view btn-small">View School</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">

            <div class="info-box" style="margin-bottom:25px;">
                <strong>Note:</strong> Only administrative fields are editable here. 
                School name, district, contact details, etc., should be updated by the Headteacher.
            </div>

            <form method="POST">

                <div class="form-grid">

                    <div class="form-group">
                        <label>School Name</label>
                        <input type="text" value="<?= htmlspecialchars($school['school_name']) ?>" class="readonly-input" readonly>
                    </div>

                    <div class="form-group">
                        <label>District</label>
                        <input type="text" value="<?= htmlspecialchars($school['district']) ?>" class="readonly-input" readonly>
                    </div>

                    <div class="form-group">
                        <label>Status <span style="color:red;">*</span></label>
                        <select name="status" required>
                            <option value="active" <?= $school['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $school['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Re-assign Headteacher</label>
                        <select name="headteacher_id">
                            <option value="">-- Keep Current / No Change --</option>
                            <?php foreach ($available_headteachers as $ht): ?>
                                <option value="<?= $ht['user_id'] ?>">
                                    <?= htmlspecialchars($ht['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#64748b;">Only unassigned headteachers are shown.</small>
                    </div>

                </div>

                <div style="margin-top:35px; display:flex; gap:12px; justify-content:flex-end;">
                    <a href="manage_schools.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-dark">Save Changes</button>
                </div>

            </form>

        </div>

    </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>