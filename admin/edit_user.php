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
    AND deleted_at IS NULL
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

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);

    $school_id = !empty($_POST['school_id'])
        ? (int)$_POST['school_id']
        : null;

    /* ================= CHECK SCHOOL STATUS ================= */
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

        } else {

            /* ================= UPDATE USER ================= */
            $stmt = $conn->prepare("
                UPDATE users
                SET
                    name = ?,
                    email = ?,
                    phone = ?,
                    role = ?,
                    status = ?,
                    school_id = ?
                WHERE user_id = ?
            ");

            $stmt->bind_param(
                "sssssii",
                $name,
                $email,
                $phone,
                $role,
                $status,
                $school_id,
                $user_id
            );

            if ($stmt->execute()) {

                /* ================= SEND EMAIL ================= */
                send_email(
                    $email,
                    "Account Updated",
                    "
                    Hello {$name},

                    Your account details were updated by the administrator.

                    Role: {$role}
                    Status: {$status}

                    Regards,
                    NED-SEMS Administration
                    "
                );

                /* ================= AUDIT LOG ================= */
                $log = $conn->prepare("
                    INSERT INTO audit_logs
                    (user_id, action, details)
                    VALUES (?, 'update_user', ?)
                ");

                $details = "Updated user ID {$user_id} ({$name})";

                $log->bind_param(
                    "is",
                    $_SESSION['user_id'],
                    $details
                );

                $log->execute();
                $log->close();

                $message = "User updated successfully.";
                $message_type = "success";

                /* REFRESH USER */
                $reload = $conn->prepare("
                    SELECT *
                    FROM users
                    WHERE user_id = ?
                ");

                $reload->bind_param("i", $user_id);
                $reload->execute();

                $user = $reload->get_result()->fetch_assoc();

                $reload->close();

            } else {

                $message = "Failed to update user.";
                $message_type = "error";
            }

            $stmt->close();
        }

    } else {

        /* ================= UPDATE WITHOUT SCHOOL ================= */
        $stmt = $conn->prepare("
            UPDATE users
            SET
                name = ?,
                email = ?,
                phone = ?,
                role = ?,
                status = ?,
                school_id = NULL
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "sssssi",
            $name,
            $email,
            $phone,
            $role,
            $status,
            $user_id
        );

        if ($stmt->execute()) {

            send_email(
                $email,
                "Account Updated",
                "
                Hello {$name},

                Your account information has been updated.

                Regards,
                NED-SEMS Administration
                "
            );

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (user_id, action, details)
                VALUES (?, 'update_user', ?)
            ");

            $details = "Updated user ID {$user_id} ({$name})";

            $log->bind_param(
                "is",
                $_SESSION['user_id'],
                $details
            );

            $log->execute();
            $log->close();

            $message = "User updated successfully.";
            $message_type = "success";

        } else {

            $message = "Failed to update user.";
            $message_type = "error";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

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

.status-card{
    margin-bottom:16px;
}

.badge-preview{
    display:inline-block;
    margin-top:8px;
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
            Update user information, school assignment and access permissions
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

<form method="POST">

<div class="form-grid">

    <!-- NAME -->
    <div class="form-group">
        <label>Full Name</label>

        <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($user['name']) ?>"
            required
        >
    </div>

    <!-- EMAIL -->
    <div class="form-group">
        <label>Email Address</label>

        <input
            type="email"
            name="email"
            value="<?= htmlspecialchars($user['email']) ?>"
            required
        >
    </div>

    <!-- PHONE -->
    <div class="form-group">
        <label>Phone Number</label>

        <input
            type="text"
            name="phone"
            value="<?= htmlspecialchars($user['phone']) ?>"
        >
    </div>

    <!-- ROLE -->
    <div class="form-group">
        <label>User Role</label>

        <select name="role" required>

            <option value="admin"
                <?= $user['role']=='admin'?'selected':'' ?>>
                Admin
            </option>

            <option value="teacher"
                <?= $user['role']=='teacher'?'selected':'' ?>>
                Teacher
            </option>

            <option value="headteacher"
                <?= $user['role']=='headteacher'?'selected':'' ?>>
                Headteacher
            </option>

            <option value="examination_officer"
                <?= $user['role']=='examination_officer'?'selected':'' ?>>
                Examination Officer
            </option>

            <option value="edm"
                <?= $user['role']=='edm'?'selected':'' ?>>
                EDM Officer
            </option>

        </select>
    </div>

    <!-- STATUS -->
    <div class="form-group">
        <label>Account Status</label>

        <select name="status" required>

            <option value="active"
                <?= $user['status']=='active'?'selected':'' ?>>
                Active
            </option>

            <option value="inactive"
                <?= $user['status']=='inactive'?'selected':'' ?>>
                Inactive
            </option>

            <option value="pending_reassignment"
                <?= $user['status']=='pending_reassignment'?'selected':'' ?>>
                Pending Reassignment
            </option>

        </select>

        <div class="badge-preview">
            <span class="badge badge-<?= $user['status'] ?>">
                <?= $user['status'] ?>
            </span>
        </div>

    </div>

    <!-- SCHOOL -->
    <div class="form-group">
        <label>Assigned School</label>

        <select name="school_id">

            <option value="">No School Assigned</option>

            <?php while($s = $schools->fetch_assoc()): ?>

            <option
                value="<?= $s['school_id'] ?>"
                <?= $user['school_id'] == $s['school_id'] ? 'selected' : '' ?>
            >

                <?= htmlspecialchars($s['school_name']) ?>
                (<?= htmlspecialchars($s['district']) ?>)
                - <?= ucfirst($s['status']) ?>

            </option>

            <?php endwhile; ?>

        </select>
    </div>

</div>

<!-- ================= ACTIONS ================= -->
<div style="margin-top:25px;display:flex;gap:10px;">

    <button type="submit" class="btn btn-dark">
        Save Changes
    </button>

    <a href="manage_users.php" class="btn btn-edit">
        Cancel
    </a>

</div>

</form>

</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>