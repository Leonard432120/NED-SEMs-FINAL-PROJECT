<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$school_id = (int)$_SESSION['school_id'];
$user_name = $_SESSION['name'] ?? 'Examination Officer';

/* ================= BASIC STATS ================= */
$total_students = $conn->query("
    SELECT COUNT(*) AS total 
    FROM students 
    WHERE school_id = $school_id
")->fetch_assoc()['total'] ?? 0;

$total_exams = $conn->query("SELECT COUNT(*) AS total FROM exams")->fetch_assoc()['total'] ?? 0;

$received_marks = $conn->query("
    SELECT COUNT(*) AS total 
    FROM marks m
    JOIN students s ON m.student_id = s.student_id
    WHERE s.school_id = $school_id 
    AND m.submission_status = 'received'
")->fetch_assoc()['total'] ?? 0;

$pending_marks = $conn->query("
    SELECT COUNT(*) AS total 
    FROM marks m
    JOIN students s ON m.student_id = s.student_id
    WHERE s.school_id = $school_id 
    AND m.submission_status = 'submitted'
")->fetch_assoc()['total'] ?? 0;

$forwarded_marks = $conn->query("
    SELECT COUNT(*) AS total 
    FROM marks m
    JOIN students s ON m.student_id = s.student_id
    WHERE s.school_id = $school_id 
    AND m.submission_status = 'forwarded_to_edm'
")->fetch_assoc()['total'] ?? 0;

$compiled_results = $conn->query("
    SELECT COUNT(*) AS total 
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
")->fetch_assoc()['total'] ?? 0;

/* ================= RECENT EXAMS (SAFE QUERY - NO subject_id) ================= */
$recent_exams = $conn->query("
    SELECT 
        exam_name,
        start_date,
        status,
        'General' AS subject_name   -- Remove this if you have subject info elsewhere
    FROM exams 
    ORDER BY start_date DESC
    LIMIT 8
");

/* ================= RECENT MARKS ================= */
$recent_marks = $conn->query("
    SELECT
        st.name,
        sub.subject_name,
        m.score,
        m.grade,
        m.submission_status
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    LEFT JOIN subjects sub ON m.subject_id = sub.subject_id
    WHERE st.school_id = $school_id
    ORDER BY m.mark_id DESC
    LIMIT 10
");

/* ================= RECENT ANNOUNCEMENTS ================= */
$announcements = $conn->query("
    SELECT a.*, u.name AS author_name, u.role AS author_role
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.user_id
    WHERE a.school_id IS NULL OR a.school_id = $school_id
    ORDER BY a.created_at DESC
    LIMIT 3
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Officer Dashboard</title>
    <?php $module_css = 'examination_officer'; include '../common/head_assets.php'; ?>
    
    <style>
       .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
                gap: 15px;
                margin: 25px 0;
            }

            .stat-box {
                background: white;
                border-radius: 12px;
                padding: 20px 14px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.07);
                text-align: center;
                transition: all 0.3s ease;
                border: 1px solid var(--border-color);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                min-height: 172px;
            }

            .stat-box:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 25px rgba(0,0,0,0.1);
            }

            .stat-badge img {
                width:40px;
                height:40px;
                object-fit:contain;
                margin-bottom:10px;
            }

            .stat-number {
                font-size:1.50rem;
                font-weight:700;
                margin:6px 0 4px;
                color:#1e2937;
            }

            .stat-box h4 {
                margin:0 0 6px;
                font-size:0.85rem;
                color:#334155;
                font-weight:600;
            }

            .stat-box small {
                color:#64748b;
                font-size:0.82rem;
                line-height:1.4;
            }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <div class="page-header">
            <div>
                <h2 class="page-title">Examination Officer Dashboard</h2>
                <p class="page-subtitle">Manage examinations, monitor marks submissions and oversee results processing.</p>
            </div>
        </div>

        <!-- ================= WELL ALIGNED STAT CARDS ================= -->
                <div class="stats">
                    <div class="stat-box">                        
                        <h4>Registered Students</h4>
                        <p class="stat-number"><?= number_format($total_students) ?></p>
                        <small>Students registered in this school</small>
                        <a href="candidate_register.php" class="btn btn-small btn-view">View Students</a>
                    </div>
                    <div class="stat-box">                        
                        <h4>Marks Received</h4>
                        <p class="stat-number"><?= number_format($received_marks) ?></p>
                        <small>Marks successfully received from teachers</small>
                        <a href="receive_marks.php" class="btn btn-small btn-view">View Marks</a>
                    </div>
                    <div class="stat-box">                        
                        <h4>Pending Submissions</h4>
                        <p class="stat-number"><?= number_format($pending_marks) ?></p>
                        <small>Marks awaiting submission verification</small>
                        <a href="marks_verification.php" class="btn btn-small btn-view">Verify</a>
                    </div>               
                    <div class="stat-box">                        
                        <h4>Compiled Results</h4>
                        <p class="stat-number"><?= number_format($compiled_results) ?></p>
                        <small>Results prepared and available</small>
                        <a href="reports.php" class="btn btn-small btn-view">Reports</a>
                    </div>

                </div>

        <!-- ANNOUNCEMENTS -->
        <div class="card" style="margin-bottom: 25px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">Recent Announcements</h3>
                <a href="../common/announcements.php" class="btn btn-secondary btn-small">View All / Publish</a>
            </div>
            <div class="announcement-list" style="display:flex; flex-direction:column; gap:15px;">
                <?php if ($announcements && $announcements->num_rows > 0): ?>
                    <?php while ($ann = $announcements->fetch_assoc()): ?>
                        <div style="padding:15px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; text-align:left;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                <span style="font-size:0.75rem; color:#64748b;">
                                    <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                </span>
                            </div>
                            <p style="margin:0 0 8px 0; color:#334155; line-height:1.5; font-size:0.9rem;"><?= htmlspecialchars($ann['content']) ?></p>
                            <small style="color:#64748b;">By <?= htmlspecialchars($ann['author_name'] ?? 'System') ?> (<?= ucfirst(htmlspecialchars($ann['author_role'] ?? '')) ?>)</small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No announcements published yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="card">
            <h3>Quick Actions</h3>
            <div class="quick-links">
                <a href="receive_marks.php">Receive Marks</a>                
                <a href="results_review.php">Review Results</a>
                <a href="candidate_register.php">Candidate Register</a>                
            </div>
        </div>

        <!-- TWO PANELS -->
        <div class="panel-grid">

            <!-- RECENT EXAMS -->
            <div class="section">
                <div class="section-header">
                    <h3>Recent Examinations</h3>
                </div>
                <div class="table-container">
                    <table class="table-striped">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recent_exams->num_rows > 0): ?>
                                <?php while($exam = $recent_exams->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exam['exam_name']) ?></td>
                                    <td><?= htmlspecialchars($exam['subject_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($exam['start_date'])) ?></td>
                                    <td><span class="badge badge-approved"><?= ucfirst($exam['status']) ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No recent examinations found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- EXAM SUMMARY -->
            <div class="section">
                <div class="section-header">
                    <h3>Examination Summary</h3>
                </div>
                <div class="insight-card">
                    <div class="insight-card__row">
                        <span>Marks Received</span>
                        <strong><?= number_format($received_marks) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Awaiting Submission</span>
                        <strong><?= number_format($pending_marks) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Forwarded to EDM</span>
                        <strong><?= number_format($forwarded_marks) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Compiled Results</span>
                        <strong><?= number_format($compiled_results) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT MARKS -->
        <div class="section">
            <div class="section-header">
                <h3>Latest Marks Received</h3>
            </div>
            <div class="table-container">
                <table class="table-striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_marks->num_rows > 0): ?>
                            <?php while($row = $recent_marks->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['subject_name'] ?? 'N/A') ?></td>
                                <td><?= $row['score'] ?? 'N/A' ?></td>
                                <td><?= $row['grade'] ?? 'N/A' ?></td>
                                <td>
                                    <span class="badge badge-submitted">
                                        <?= ucwords(str_replace('_',' ', $row['submission_status'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No marks available yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>