<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= SCHOOL ID ================= */
$school_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($school_id <= 0) {
    die("Invalid School ID");
}

/* ================= FETCH SCHOOL DETAILS ================= */
$stmt = $conn->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM users WHERE school_id = s.school_id AND role = 'headteacher') AS headteacher_count,
           (SELECT COUNT(*) FROM users WHERE school_id = s.school_id AND role = 'teacher') AS teacher_count,
           (SELECT name FROM users WHERE role = 'headteacher' AND school_id = s.school_id LIMIT 1) AS headteacher_name
    FROM schools s
    WHERE s.school_id = ?
");

$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$school) {
    die("School not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Profile - <?= htmlspecialchars($school['school_name']) ?></title>

    <?php include __DIR__ . '/../common/head_assets.php'; ?>
    
    <style>
        .school-avatar {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            flex-shrink: 0;
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
                <h2 class="page-title">School Profile</h2>
                <p class="page-subtitle">Detailed information about the school</p>
            </div>

            <div class="header-actions">
                <a href="manage_schools.php" class="btn btn-secondary btn-small">← Back to Schools</a>
                <a href="edit_school.php?id=<?= $school['school_id'] ?>" class="btn btn-edit btn-small">Edit School</a>
            </div>
        </div>

        <!-- SCHOOL CARD -->
        <div class="card">

            <!-- HEADER -->
            <div class="section-header">
                <div style="display:flex; align-items:center; gap:20px;">

                    <!-- School Avatar -->
                    <div class="school-avatar">
                        <?= strtoupper(substr($school['school_name'], 0, 1)) ?>
                    </div>

                    <div>
                        <h3 style="margin:0; font-size:1.8rem;">
                            <?= htmlspecialchars($school['school_name']) ?>
                        </h3>
                        <p class="muted-text" style="margin:4px 0 0 0;">
                            <?= htmlspecialchars($school['district']) ?> 
                            <?= $school['school_type'] ? '• ' . htmlspecialchars($school['school_type']) : '' ?>
                        </p>
                        <span class="badge badge-<?= $school['status'] ?>">
                            <?= ucfirst($school['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- BASIC INFORMATION -->
            <div class="insight-grid">

                <div class="insight-card">
                    <h4>School Information</h4>
                    
                    <div class="insight-card__row">
                        <span>School Name</span>
                        <strong><?= htmlspecialchars($school['school_name']) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>District</span>
                        <strong><?= htmlspecialchars($school['district']) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Cluster</span>
                        <strong><?= htmlspecialchars($school['cluster_name'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>School Number</span>
                        <strong><?= htmlspecialchars($school['school_number'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>School Type</span>
                        <strong><?= htmlspecialchars($school['school_type'] ?? 'N/A') ?></strong>
                    </div>
                </div>

                <div class="insight-card">
                    <h4>Contact Details</h4>
                    
                    <div class="insight-card__row">
                        <span>Phone</span>
                        <strong><?= htmlspecialchars($school['phone'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Email</span>
                        <strong><?= htmlspecialchars($school['email'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Address</span>
                        <strong><?= nl2br(htmlspecialchars($school['address'] ?? 'N/A')) ?></strong>
                    </div>
                </div>

            </div>

            <!-- STAFF INFORMATION -->
            <h3 class="page-title" style="margin-top:30px;">Staff Overview</h3>
            
            <div class="insight-grid">

                <div class="insight-card">
                    <h4>Headteacher</h4>
                    <div class="insight-card__row">
                        <span>Current Headteacher</span>
                        <strong><?= htmlspecialchars($school['headteacher_name'] ?? 'Not Assigned') ?></strong>
                    </div>
                </div>

                <div class="insight-card">
                    <h4>Staff Count</h4>
                    <div class="insight-card__row">
                        <span>Headteachers</span>
                        <strong><?= $school['headteacher_count'] ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Teachers</span>
                        <strong><?= $school['teacher_count'] ?></strong>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>