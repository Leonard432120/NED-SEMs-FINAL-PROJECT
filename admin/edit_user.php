<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header("Location: manage_users.php");
    exit();
}

/* ================= GET USER ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: manage_users.php");
    exit();
}

/* ================= LOAD SCHOOLS ================= */
$schools = $conn->query("
    SELECT school_id, school_name, district, status
    FROM schools
    ORDER BY school_name ASC
");

$message = '';
$message_type = '';

/* ================= UPDATE USER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admin_id   = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $role       = trim($_POST['role'] ?? '');
    $status     = trim($_POST['status'] ?? '');
    $school_id  = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
    $employment_number = trim($_POST['employment_number'] ?? '');

    $allowed_roles = ['admin', 'teacher', 'headteacher', 'examination_officer'];

    if (!in_array($role, $allowed_roles)) {

        $message = "Invalid role selected.";
        $message_type = "error";

    } else {

        /* ================= FETCH OLD VALUES ================= */
        $old_role = $user['role'];
        $old_status = $user['status'];
        $old_school_id = $user['school_id'];
        $old_emp = $user['employment_number'];

        /* old school name */
        $old_school_name = 'No School Assigned';
        if ($old_school_id) {
            $stmtOld = $conn->prepare("SELECT school_name FROM schools WHERE school_id=?");
            $stmtOld->bind_param("i", $old_school_id);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result()->fetch_assoc();
            $old_school_name = $resOld['school_name'] ?? 'Unknown';
            $stmtOld->close();
        }

        /* ================= SCHOOL VALIDATION ================= */
        $new_school_name = 'No School Assigned';

        if ($school_id) {

            $school_check = $conn->prepare("
                SELECT school_name, status 
                FROM schools 
                WHERE school_id = ?
            ");

            $school_check->bind_param("i", $school_id);
            $school_check->execute();
            $school = $school_check->get_result()->fetch_assoc();
            $school_check->close();

            if ($school && $school['status'] === 'inactive') {

                $message = "Cannot assign user to inactive school.";
                $message_type = "error";

                return;

            } else {
                $new_school_name = $school['school_name'] ?? 'Unknown';
            }
        }

        /* ================= UPDATE USER ================= */
        if ($school_id) {

            $stmt = $conn->prepare("
                UPDATE users
                SET role=?, status=?, school_id=?, employment_number=?
                WHERE user_id=?
            ");

            $stmt->bind_param("ssisi", $role, $status, $school_id, $employment_number, $user_id);

        } else {

            $stmt = $conn->prepare("
                UPDATE users
                SET role=?, status=?, school_id=NULL, employment_number=?
                WHERE user_id=?
            ");

            $stmt->bind_param("sssi", $role, $status, $employment_number, $user_id);
        }

        /* ================= EXECUTE ================= */
        if ($stmt->execute()) {

            /* ================= BUILD CHANGES ================= */
            $changes = [];

            if ($old_role !== $role) {
                $changes[] = "Role: {$old_role} → {$role}";
            }

            if ($old_status !== $status) {
                $changes[] = "Status: {$old_status} → {$status}";
            }

            if ($old_school_name !== $new_school_name) {
                $changes[] = "School: {$old_school_name} → {$new_school_name}";
            }

            if ($old_emp !== $employment_number) {
                $changes[] = "Employment No: {$old_emp} → {$employment_number}";
            }

            $changes_text = !empty($changes)
                ? implode("\n", $changes)
                : "No changes were made.";

            /* ================= EMAIL ================= */
            send_email(
                $user['email'],
                "Account Updated - NED-SEMS",
                "Hello {$user['name']},

Your account has been updated by the administrator.

Changes:
{$changes_text}

If you did not expect these changes, please contact the administration.

Regards,
NED-SEMS Administration"
            );

            /* ================= AUDIT LOG ================= */
            $action = "update_user";
            $details = "Updated user ID {$user_id} ({$user['name']})";

            $log = $conn->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    target_user_id,
                    action,
                    details,
                    ip_address
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "iisss",
                $admin_id,
                $user_id,
                $action,
                $details,
                $ip_address
            );

            $log->execute();
            $log->close();

            $message = "User updated successfully.";
            $message_type = "success";
        }

        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

<style>

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:18px;
}

.form-group{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.form-group label{
    font-size:13px;
    font-weight:600;
    color:#334155;
}

.page-subtitle{
    color:#64748b;
    margin-top:4px;
    font-size:14px;
}

.badge-preview{
    display:inline-block;
    margin-top:8px;
}
.spinner{
    width:60px;
    height:60px;
    border:6px solid #cbd5e1;
    border-top:6px solid #2563eb;
    border-radius:50%;
    animation: spin 1s linear infinite;
}

@keyframes spin{
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

</style>

</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
   

<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- ================= HEADER ================= -->
<div class="page-header">

    <div>
        <h2 class="page-title">Edit User</h2>
        <p class="page-subtitle">
            Update user information and access settings
        </p>
    </div>

    <div class="header-actions">
        <a href="manage_users.php" class="btn btn-dark btn-small">
            ← Back
        </a>
    </div>

</div>

<!-- ================= ALERT ================= -->
<?php if ($message): ?>

<div class="alert <?= $message_type ?>">
    <?= $message ?>
</div>

<?php endif; ?>

<!-- ================= FORM CARD ================= -->
<div class="card">

<form method="POST" id="editUserForm">

    <div class="form-grid">

        <!-- ================= ACCOUNT DETAILS ================= -->
        <div class="form-group">
            <h4 style="margin-bottom:10px;color:#334155;">Account Details</h4>

            <label>User Role</label>
            <select name="role" required>
                <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
                <option value="teacher" <?= $user['role']=='teacher'?'selected':'' ?>>Teacher</option>
                <option value="headteacher" <?= $user['role']=='headteacher'?'selected':'' ?>>Headteacher</option>
                <option value="examination_officer" <?= $user['role']=='examination_officer'?'selected':'' ?>>Examination Officer</option>
            </select>
        </div>

        <div class="form-group">
            <label>Account Status</label>
            <select name="status" required>
                <option value="active" <?= $user['status']=='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $user['status']=='inactive'?'selected':'' ?>>Inactive</option>
                <option value="pending_reassignment" <?= $user['status']=='pending_reassignment'?'selected':'' ?>>Pending Reassignment</option>
            </select>

            <div class="badge-preview" style="margin-top:8px;">
                <span class="badge badge-<?= $user['status'] ?>">
                    <?= $user['status'] ?>
                </span>
            </div>
        </div>

        <!-- ================= ORGANIZATION ================= -->
        <div class="form-group">
            <h4 style="margin-bottom:10px;color:#334155;">School Assignment</h4>

            <label>Assigned School</label>
            <select name="school_id">
                <option value="">No School Assigned</option>

                <?php while($s = $schools->fetch_assoc()): ?>
                    <option value="<?= $s['school_id'] ?>"
                        <?= $user['school_id'] == $s['school_id'] ? 'selected' : '' ?>>

                        <?= htmlspecialchars($s['school_name']) ?>
                        (<?= htmlspecialchars($s['district']) ?>)
                        - <?= ucfirst($s['status']) ?>

                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- ================= EMPLOYMENT INFO ================= -->
        <div class="form-group">
            <h4 style="margin-bottom:10px;color:#334155;">Employment Information</h4>

            <label>Employment Number</label>
            <input type="text"
                   name="employment_number"
                   value="<?= htmlspecialchars($user['employment_number'] ?? '') ?>"
                   placeholder="Enter employment number">
        </div>

    </div>

    <!-- ================= ACTION BUTTONS ================= -->
    <div style="margin-top:30px;display:flex;gap:10px;justify-content:flex-end;">

        <a href="manage_users.php" class="btn btn-edit">
            Cancel
        </a>

        <button type="submit" class="btn btn-dark">
            Save Changes
        </button>

    </div>

</form>

</div>

</div>
</div>
<!-- LOADING OVERLAY -->
<div id="loadingOverlay" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(15,23,42,0.6);
    z-index:9999;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    color:#fff;
">

    <div class="spinner"></div>
    <p style="margin-top:15px;font-size:16px;">
        Updating user details, please wait...
    </p>

</div>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('editUserForm');
    const overlay = document.getElementById('loadingOverlay');

    if (form && overlay) {

        form.addEventListener('submit', function () {

            // show loading immediately
            overlay.style.display = 'flex';

            // optional: disable button to prevent double submit
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerText = "Updating...";
            }

        });

    }

});
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>