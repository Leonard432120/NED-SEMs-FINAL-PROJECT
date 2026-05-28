<?php
require_once __DIR__ . '/teacher_init.php';
$conn = get_db_connection();

/* ================== DASHBOARD COUNTS ================== */

// Assigned as Item Writer
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exam_assignments WHERE teacher_id=? AND role='item_writer'");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$assigned = $stmt->get_result()->fetch_assoc()['total'];

// Moderation Tasks
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exam_assignments WHERE teacher_id=? AND role='moderator'");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$moderations = $stmt->get_result()->fetch_assoc()['total'];

// My Submissions
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exam_assignments ea JOIN exams e ON ea.exam_id = e.exam_id WHERE ea.teacher_id=? AND ea.role='item_writer' AND e.status IN ('submitted','under_moderation','approved')");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc()['total'];

// Pending Reviews
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM exam_assignments ea
    JOIN exams e ON ea.exam_id = e.exam_id
    WHERE ea.teacher_id=? AND ea.role='item_writer' AND e.status='under_moderation'
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc()['total'];

// Exams list for Results Table
$stmt = $conn->prepare("
    SELECT e.exam_id, e.exam_name
    FROM exam_assignments ea
    JOIN exams e ON ea.exam_id = e.exam_id
    WHERE ea.teacher_id=? AND ea.role='item_writer'
    ORDER BY e.exam_name ASC
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
$exams = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
        <title>Teacher Dashboard</title>

        <link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

        <style>
            .table-container table{width:100%;border-collapse:collapse}
            .table-container th,.table-container td{padding:12px;border-bottom:1px solid #eee;text-align:left}
            .modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.65);display:flex;justify-content:center;align-items:center;z-index:9999}
            .modal-box{background:#fff;width:550px;max-width:95%;padding:30px;border-radius:10px;position:relative}
            .modal-close{position:absolute;right:15px;top:10px;font-size:26px;cursor:pointer}
            .notify-card{border-radius:8px;padding:18px;margin-bottom:15px}
            .writer{background:#eef6ff;border-left:5px solid #3b82f6}
            .moderator{background:#ecfeff;border-left:5px solid #14b8a6}
            .role-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 18px 40px rgba(15,23,42,.08);}
            .role-card h3{margin-bottom:12px;font-size:20px;color:#111827;}
            .role-card p{margin-bottom:18px;color:#475569;line-height:1.7;}
            .role-card .role-action{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:12px;font-weight:700;text-decoration:none;color:#fff;transition:transform .18s ease,box-shadow .18s ease;}
            .role-card.writer .role-action{background:#2563eb;}
            .role-card.moderator .role-action{background:#0f766e;}
            .role-card .role-action:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(15,23,42,.16);}
        </style>

        <script>
            function closeModal(){document.getElementById("notifyModal").style.display="none";}
        </script>
    </head>

    <body>

        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <span class="dashboard-title">NED-SEMS | Teacher Portal</span>
            </div>
            <div class="header-right">
                <div class="profile">
                    <span class="profile-name"><?php echo $_SESSION['name'] ?? 'Teacher'; ?></span>
                    <img src="<?= BASE_URL ?>/static/images/user.png">
                    <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>

        <div class="dashboard">

            <!-- SIDEBAR -->
            <?php include __DIR__ . '/teacher_sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <div class="main-content">

                <?php if ($assigned>0 || $moderations>0): ?>
                <div id="notifyModal" class="modal-overlay">
                    <div class="modal-box">
                        <span class="modal-close" onclick="closeModal()">×</span>
                        <h2>🔔 New Role Assignment</h2>

                        <?php if ($assigned>0): ?>
                        <div class="notify-card writer">
                            <h3>📝 Item Writer Assignment</h3>
                            <a href="assigned_exams.php" class="btn btn-dark">Open Tasks</a>
                        </div>
                        <?php endif; ?>

                        <?php if ($moderations>0): ?>
                        <div class="notify-card moderator">
                            <h3>🔎 Moderator Assignment</h3>
                            <a href="moderation_exams.php" class="btn btn-dark">Start Moderation</a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endif; ?>

                <h2 style="margin-bottom:15px;">Teacher & Moderator Dashboard</h2>

                <!-- STATS -->
                <div class="stats">
                    <div class="stat-box"><h4>Assigned Exams</h4><p><?= $assigned ?></p></div>
                    <div class="stat-box"><h4>Moderation Tasks</h4><p><?= $moderations ?></p></div>
                    <div class="stat-box"><h4>My Submissions</h4><p><?= $submission ?></p></div>
                    <div class="stat-box"><h4>Pending Reviews</h4><p><?= $review ?></p></div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div class="quick-links">
                        <a href="assigned_exams.php">Assigned Exams</a>
                        <a href="moderation_exams.php">Moderation Tasks</a>
                        <a href="my_submissions.php">My Submissions</a>
                    </div>
                </div>

                <!-- RESULTS TABLE -->
                <div class="card">
                    <h3>Enter Results</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr><th>Exam</th><th style="width:200px;">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($exams)): ?>
                                <?php foreach($exams as $exam): ?>
                                <tr>
                                <td><?= htmlspecialchars($exam['exam_name']) ?></td>
                                <td>
                                <a href="enter_results.php?exam_id=<?= $exam['exam_id'] ?>" class="btn btn-teal btn-small">
                                Enter Results
                                </a>
                                </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="2" style="text-align:center;padding:20px;">No exams available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ROLE CARDS -->
                <div class="dashboard-grid">
                    <div class="role-card writer">
                        <h3>Item Writer Work</h3>
                        <p>Create and upload exam questions.</p>
                        <a href="assigned_exams.php" class="role-action">View Tasks</a>
                    </div>

                    <div class="role-card moderator">
                        <h3>Moderator Work</h3>
                        <p>Review submitted exams.</p>
                        <a href="moderation_exams.php" class="role-action">Start Review</a>
                    </div>
                </div>

            </div>
        </div>

    </body>
</html>