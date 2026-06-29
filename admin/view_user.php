<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= USER ID ================= */
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    die("Invalid user ID");
}

/* ================= FETCH USER ================= */
$stmt = $conn->prepare("
    SELECT u.*,
    s.school_name AS school_name,
    (SELECT COUNT(*) FROM audit_logs a WHERE a.user_id = u.user_id) AS activity_score
    FROM users u
    LEFT JOIN schools s ON s.school_id = u.school_id
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title>User Profile</title>

<?php include __DIR__ . '/../common/head_assets.php'; ?>
</head>

<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- PAGE HEADER -->
<div class="page-header">

    <div>
        <h2 class="page-title">User Profile</h2>
        <p class="page-subtitle">Complete user information and academic details</p>
    </div>

    <div class="header-actions">
        <a href="manage_users.php" class="btn btn-secondary btn-small">← Back</a>
        <a href="edit_user.php?id=<?= $user['user_id'] ?>" class="btn btn-edit btn-small">Edit User</a>
    </div>

</div>

<!-- PROFILE CARD -->
<div class="card">

    <!-- HEADER -->
    <div class="section-header">

        <div style="display:flex;align-items:center;gap:16px;">

            <!-- AVATAR -->
            <div style="
                width:65px;
                height:65px;
                border-radius:50%;
                background:var(--primary-dark);
                color:#fff;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:24px;
                font-weight:700;
            ">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>

            <div>

                <h3 style="margin:0;">
                    <?= htmlspecialchars($user['name']) ?>
                </h3>

                <p class="muted-text">
                    <?= htmlspecialchars($user['email']) ?>
                </p>

                <span class="badge badge-<?= $user['status'] ?>">
                    <?= ucfirst($user['status']) ?>
                </span>

            </div>

        </div>

    </div>

    <!-- BASIC INFO -->
    <div class="insight-grid">

        <div class="insight-card">
            <h4>Account Information</h4>

            <div class="insight-card__row">
                <span>Email</span>
                <strong><?= htmlspecialchars($user['email']) ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Role</span>
                <strong><?= ucfirst($user['role']) ?></strong>
            </div>

            <div class="insight-card__row">
                <span>School</span>
                <strong><?= htmlspecialchars($user['school_name'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Phone</span>
                <strong><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Gender</span>
                <strong><?= htmlspecialchars($user['gender'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Activity Score</span>
                <strong><?= $user['activity_score'] ?></strong>
            </div>

        </div>

        <div class="insight-card">
            <h4>System Information</h4>

            <div class="insight-card__row">
                <span>Created At</span>
                <strong><?= $user['created_at'] ?? 'N/A' ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Last Login</span>
                <strong><?= $user['last_login'] ?? 'N/A' ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Status</span>
                <strong><?= ucfirst($user['status']) ?></strong>
            </div>

        </div>

    </div>

    <!-- ACADEMIC INFO -->
    <h3 class="page-title" style="margin-top:25px;">Academic / Staff Information</h3>

    <div class="insight-grid">

        <div class="insight-card">

            <h4>Teaching Profile</h4>

            <div class="insight-card__row">
                <span>Teacher Category</span>
                <strong><?= htmlspecialchars($user['teacher_category'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Major Subject</span>
                <strong><?= htmlspecialchars($user['major_subject'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Minor Subject</span>
                <strong><?= htmlspecialchars($user['minor_subject'] ?? 'N/A') ?></strong>
            </div>

        </div>

        <div class="insight-card">

            <h4>Qualification Details</h4>

            <div class="insight-card__row">
                <span>Qualification</span>
                <strong><?= htmlspecialchars($user['qualification'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Employment Number</span>
                <strong><?= htmlspecialchars($user['employment_number'] ?? 'N/A') ?></strong>
            </div>

        </div>

    </div>

</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>