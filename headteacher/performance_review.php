<?php
/**
 * headteacher/performance_review.php
 * ─────────────────────────────────────────────────────────────────
 * HT: Underperforming Teacher Intervention & Review Management
 * Automatically flags teachers below threshold, allows headteacher
 * to log structured meeting records and improvement plans.
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/teacher_performance_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn      = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$head_id   = (int)$_SESSION['user_id'];

// Fetch school info
$stmt = $conn->prepare("SELECT school_name FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Configurable weights
$weights = [
    'prep'    => (float)($_SESSION['w_prep']    ?? 20.0),
    'quality' => (float)($_SESSION['w_quality'] ?? 20.0),
    'mod'     => (float)($_SESSION['w_mod']     ?? 15.0),
    'marking' => (float)($_SESSION['w_marking'] ?? 20.0),
    'time'    => (float)($_SESSION['w_time']    ?? 15.0),
    'part'    => (float)($_SESSION['w_part']    ?? 10.0),
];

// ── HANDLE FORM SAVE ──────────────────────────────────────────────
$save_msg = '';
$save_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_review'])) {
    $rv_teacher_id    = (int)($_POST['teacher_id'] ?? 0);
    $rv_meeting_date  = trim($_POST['meeting_date'] ?? '');
    $rv_improve       = trim($_POST['improvement_plan'] ?? '');
    $rv_advice        = trim($_POST['advice_given'] ?? '');
    $rv_follow_up     = trim($_POST['follow_up_date'] ?? '') ?: null;
    $rv_status        = trim($_POST['review_status'] ?? 'Pending');
    $rv_comments      = trim($_POST['comments'] ?? '');

    $allowed_statuses = ['Pending', 'In Progress', 'Resolved'];
    if (!in_array($rv_status, $allowed_statuses)) {
        $rv_status = 'Pending';
    }

    if ($rv_teacher_id <= 0 || empty($rv_meeting_date)) {
        $save_err = 'Please select a teacher and provide the meeting date.';
    } else {
        // Verify teacher belongs to this school
        $chk = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND school_id = ? AND role = 'teacher'");
        $chk->bind_param("ii", $rv_teacher_id, $school_id);
        $chk->execute();
        $chk_res = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$chk_res) {
            $save_err = 'Invalid teacher selection.';
        } else {
            $ins = $conn->prepare("
                INSERT INTO teacher_performance_reviews
                    (teacher_id, school_id, meeting_date, improvement_plan, advice_given,
                     follow_up_date, review_status, comments, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param("iissssssi",
                $rv_teacher_id, $school_id, $rv_meeting_date,
                $rv_improve, $rv_advice, $rv_follow_up,
                $rv_status, $rv_comments, $head_id
            );
            if ($ins->execute()) {
                $save_msg = 'Performance review record saved successfully.';
            } else {
                $save_err = 'Database error: ' . $ins->error;
            }
            $ins->close();
        }
    }
}

// ── HANDLE STATUS UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $upd_review_id = (int)($_POST['review_id'] ?? 0);
    $upd_status    = trim($_POST['new_status'] ?? '');
    $allowed_statuses = ['Pending', 'In Progress', 'Resolved'];

    if ($upd_review_id > 0 && in_array($upd_status, $allowed_statuses)) {
        $upd_stmt = $conn->prepare("
            UPDATE teacher_performance_reviews
            SET review_status = ?
            WHERE review_id = ? AND school_id = ?
        ");
        $upd_stmt->bind_param("sii", $upd_status, $upd_review_id, $school_id);
        $upd_stmt->execute();
        $upd_stmt->close();
        $save_msg = 'Review status updated.';
    }
}

// ── LOAD ALL ACTIVE TEACHERS + THEIR METRICS ─────────────────────
$t_query = $conn->query("
    SELECT user_id, name, major_subject, teacher_category
    FROM users
    WHERE school_id = $school_id AND role = 'teacher' AND status = 'active'
    ORDER BY name ASC
");
$all_teachers = [];
while ($t = $t_query->fetch_assoc()) {
    $t['metrics'] = calculate_teacher_metrics($t['user_id'], $school_id, $weights, $conn);
    $all_teachers[] = $t;
}

// Identify flagged/underperforming teachers (overall < 70 or specific flags)
$flagged_teachers = array_filter($all_teachers, function($t) {
    $m = $t['metrics'];
    return $m['overall_score'] < 70.0
        || $m['late_submissions'] > 1
        || $m['marking_completion_score'] < 60.0
        || ($m['questions_written'] > 0 && $m['moderation_success_score'] < 50.0);
});

// Pre-select teacher if passed via GET
$preselect_teacher = (int)($_GET['teacher_id'] ?? 0);

// ── LOAD EXISTING REVIEW RECORDS ─────────────────────────────────
$filter_teacher = (int)($_GET['filter_teacher'] ?? 0);
$filter_status  = trim($_GET['filter_status'] ?? '');

$sql_where = 'tpr.school_id = ?';
$sql_params = [$school_id];
$sql_types  = 'i';

if ($filter_teacher > 0) {
    $sql_where  .= ' AND tpr.teacher_id = ?';
    $sql_params[] = $filter_teacher;
    $sql_types   .= 'i';
}
if (!empty($filter_status)) {
    $sql_where  .= ' AND tpr.review_status = ?';
    $sql_params[] = $filter_status;
    $sql_types   .= 's';
}

$rev_stmt = $conn->prepare("
    SELECT tpr.review_id, tpr.teacher_id, tpr.meeting_date, tpr.improvement_plan,
           tpr.advice_given, tpr.follow_up_date, tpr.review_status, tpr.comments,
           tpr.created_at, u.name AS teacher_name, htu.name AS recorded_by
    FROM teacher_performance_reviews tpr
    JOIN users u   ON u.user_id   = tpr.teacher_id
    LEFT JOIN users htu ON htu.user_id = tpr.created_by
    WHERE $sql_where
    ORDER BY tpr.meeting_date DESC
");
$rev_stmt->bind_param($sql_types, ...$sql_params);
$rev_stmt->execute();
$all_reviews = $rev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rev_stmt->close();

$conn->close();

$portal_title = 'NED-SEMS | Performance Reviews';
$module_css   = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Performance Reviews | NED-SEMS Headteacher</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
  <style>
    .pr-flagged-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px,1fr));
      gap: 14px;
      margin-bottom: 28px;
    }
    .pr-flag-card {
      background: #fff7ed;
      border: 1px solid #fed7aa;
      border-radius: 8px;
      padding: 14px;
      position: relative;
    }
    .pr-flag-card .flag-badge {
      position: absolute;
      top: 10px; right: 10px;
      background: #ef4444;
      color: #fff;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
    }
    .pr-flag-card h5 { margin: 0 0 6px; font-size: 0.95rem; color: #1e293b; }
    .pr-flag-reason  { font-size: 0.78rem; color: #c2410c; font-weight: 600; margin-bottom: 8px; }
    .pr-flag-score   { font-size: 1.3rem; font-weight: 800; color: #ef4444; }

    .pr-form-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 24px;
      margin-bottom: 28px;
    }
    .pr-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 720px) { .pr-form-grid { grid-template-columns: 1fr; } }
    .pr-form-group { display: flex; flex-direction: column; }
    .pr-form-group label {
      font-size: 0.8rem; font-weight: 700;
      color: #475569; text-transform: uppercase; margin-bottom: 4px;
    }
    .pr-form-group input,
    .pr-form-group select,
    .pr-form-group textarea {
      padding: 8px 10px;
      border: 1px solid #cbd5e1;
      border-radius: 5px;
      font-size: 0.9rem;
    }
    .pr-form-group textarea { resize: vertical; min-height: 80px; }

    .review-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 14px;
    }
    .review-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
      flex-wrap: wrap;
      gap: 6px;
    }
    .badge-rstatus-pill {
      font-size: 0.75rem;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 999px;
    }
    .badge-rstatus-pill--Pending    { background:#fef3c7; color:#d97706; }
    .badge-rstatus-pill--InProgress { background:#dbeafe; color:#1d4ed8; }
    .badge-rstatus-pill--Resolved   { background:#dcfce7; color:#15803d; }

    .pr-reason-tag {
      display: inline-block;
      font-size: 0.72rem;
      font-weight: 600;
      padding: 2px 8px;
      background: #fee2e2;
      color: #b91c1c;
      border-radius: 999px;
      margin: 2px 2px 0 0;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
<?php include __DIR__ . '/../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
  <div>
    <h1 class="page-title">Performance Reviews</h1>
    <p class="page-subtitle">Intervention management and documented improvement plans for <?= htmlspecialchars($school_info['school_name'] ?? '') ?></p>
  </div>
  <div class="header-actions">
    <a href="teacher_rankings.php" class="btn btn-secondary">Rankings</a>
  </div>
</div>

<!-- Flash messages -->
<?php if ($save_msg): ?>
  <div class="htf-alert htf-alert--success" style="margin-bottom:16px;"><?= htmlspecialchars($save_msg) ?></div>
<?php endif; ?>
<?php if ($save_err): ?>
  <div class="htf-alert htf-alert--error" style="margin-bottom:16px;"><?= htmlspecialchars($save_err) ?></div>
<?php endif; ?>

<!-- ═══ FLAGGED TEACHERS PANEL ═══ -->
<?php if (!empty($flagged_teachers)): ?>
<div class="card" style="padding:18px; margin-bottom:24px;">
  <h3 style="margin-top:0; color:#b91c1c; border-bottom:1px solid #fee2e2; padding-bottom:8px;">
    Teachers Requiring Intervention (<?= count($flagged_teachers) ?>)
  </h3>
  <div class="pr-flagged-grid">
    <?php foreach ($flagged_teachers as $ft): ?>
      <?php
        $fm = $ft['metrics'];
        $reasons = [];
        if ($fm['overall_score'] < 70.0)         $reasons[] = 'Low overall score';
        if ($fm['late_submissions'] > 1)          $reasons[] = 'Multiple late submissions';
        if ($fm['marking_completion_score'] < 60.0) $reasons[] = 'Low marking completion';
        if ($fm['questions_written'] > 0 && $fm['moderation_success_score'] < 50.0)
                                                  $reasons[] = 'Poor moderation quality';
      ?>
      <div class="pr-flag-card">
        <span class="flag-badge">NEEDS REVIEW</span>
        <h5><?= htmlspecialchars($ft['name']) ?></h5>
        <div class="pr-flag-score"><?= number_format($fm['overall_score'],1) ?>%</div>
        <div style="font-size:0.78rem; color:#64748b; margin-bottom:8px;"><?= htmlspecialchars($ft['major_subject'] ?? 'General') ?></div>
        <div class="pr-flag-reason">
          <?php foreach ($reasons as $r): ?>
            <span class="pr-reason-tag"><?= htmlspecialchars($r) ?></span>
          <?php endforeach; ?>
        </div>
        <a href="?teacher_id=<?= $ft['user_id'] ?>#log-review-form" class="btn btn-secondary btn-small" style="margin-top:6px;">Log Meeting</a>
        <a href="teacher_performance.php?id=<?= $ft['user_id'] ?>" class="btn btn-secondary btn-small" style="margin-top:6px;">View Profile</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══ LOG REVIEW FORM ═══ -->
<div class="pr-form-card" id="log-review-form">
  <h3 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Log a Performance Review Meeting</h3>
  <form method="POST">
    <div class="pr-form-grid">
      <div class="pr-form-group">
        <label for="teacher_id">Teacher *</label>
        <select name="teacher_id" id="teacher_id" required>
          <option value="">— Select Teacher —</option>
          <?php foreach ($all_teachers as $at): ?>
            <option value="<?= $at['user_id'] ?>"
              <?= ($preselect_teacher === $at['user_id'] || (int)($_POST['teacher_id'] ?? 0) === $at['user_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($at['name']) ?>
              (<?= number_format($at['metrics']['overall_score'],1) ?>% · <?= $at['metrics']['performance_level'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pr-form-group">
        <label for="meeting_date">Meeting Date *</label>
        <input type="date" name="meeting_date" id="meeting_date"
               value="<?= htmlspecialchars($_POST['meeting_date'] ?? date('Y-m-d')) ?>" required>
      </div>

      <div class="pr-form-group" style="grid-column: 1 / -1;">
        <label for="improvement_plan">Improvement Plan</label>
        <textarea name="improvement_plan" id="improvement_plan" placeholder="Describe agreed action steps, goals, and timelines…"><?= htmlspecialchars($_POST['improvement_plan'] ?? '') ?></textarea>
      </div>

      <div class="pr-form-group" style="grid-column: 1 / -1;">
        <label for="advice_given">Advice Given</label>
        <textarea name="advice_given" id="advice_given" placeholder="Summarise the guidance and professional advice communicated…"><?= htmlspecialchars($_POST['advice_given'] ?? '') ?></textarea>
      </div>

      <div class="pr-form-group">
        <label for="follow_up_date">Follow-Up Date</label>
        <input type="date" name="follow_up_date" id="follow_up_date"
               value="<?= htmlspecialchars($_POST['follow_up_date'] ?? '') ?>">
      </div>

      <div class="pr-form-group">
        <label for="review_status">Review Status</label>
        <select name="review_status" id="review_status">
          <option value="Pending"     <?= (($_POST['review_status'] ?? '') === 'Pending')     ? 'selected' : '' ?>>Pending</option>
          <option value="In Progress" <?= (($_POST['review_status'] ?? '') === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
          <option value="Resolved"    <?= (($_POST['review_status'] ?? '') === 'Resolved')    ? 'selected' : '' ?>>Resolved</option>
        </select>
      </div>

      <div class="pr-form-group" style="grid-column: 1 / -1;">
        <label for="comments">Additional Comments</label>
        <textarea name="comments" id="comments" placeholder="Any additional notes, observations, or follow-up actions…"><?= htmlspecialchars($_POST['comments'] ?? '') ?></textarea>
      </div>
    </div>

    <div style="margin-top:16px; display:flex; gap:10px;">
      <button type="submit" name="save_review" class="btn btn-primary">Save Review Record</button>
      <a href="performance_review.php" class="btn btn-secondary">Clear</a>
    </div>
  </form>
</div>

<!-- ═══ REVIEW RECORDS LIST ═══ -->
<div class="card" style="padding:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
    <h3 style="margin:0;">All Review Records</h3>
    <form method="GET" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <select name="filter_teacher" class="rpt-filter-select" onchange="this.form.submit()">
        <option value="">All Teachers</option>
        <?php foreach ($all_teachers as $at): ?>
          <option value="<?= $at['user_id'] ?>" <?= $filter_teacher === $at['user_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($at['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="filter_status" class="rpt-filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Pending"     <?= $filter_status === 'Pending'     ? 'selected' : '' ?>>Pending</option>
        <option value="In Progress" <?= $filter_status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="Resolved"    <?= $filter_status === 'Resolved'    ? 'selected' : '' ?>>Resolved</option>
      </select>
    </form>
  </div>

  <?php if (empty($all_reviews)): ?>
    <p class="empty-state">No review records found. Use the form above to log a meeting.</p>
  <?php else: ?>
    <?php foreach ($all_reviews as $rv): ?>
      <?php $sc = str_replace(' ', '', $rv['review_status']); ?>
      <div class="review-card">
        <div class="review-card-header">
          <div>
            <strong style="font-size:1rem; color:#1e293b;"><?= htmlspecialchars($rv['teacher_name']) ?></strong>
            <span style="color:#64748b; font-size:0.8rem; margin-left:8px;">Meeting: <?= date('d M Y', strtotime($rv['meeting_date'])) ?></span>
          </div>
          <div style="display:flex; gap:6px; align-items:center;">
            <span class="badge-rstatus-pill badge-rstatus-pill--<?= $sc ?>"><?= htmlspecialchars($rv['review_status']) ?></span>
            <a href="teacher_performance.php?id=<?= $rv['teacher_id'] ?>" class="btn btn-secondary btn-small">View Profile</a>
          </div>
        </div>

        <?php if (!empty($rv['improvement_plan'])): ?>
          <div style="font-size:0.85rem; margin-bottom:6px;">
            <strong style="color:#1e3a8a;">Improvement Plan:</strong>
            <?= htmlspecialchars($rv['improvement_plan']) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($rv['advice_given'])): ?>
          <div style="font-size:0.85rem; margin-bottom:6px;">
            <strong style="color:#065f46;">Advice Given:</strong>
            <?= htmlspecialchars($rv['advice_given']) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($rv['comments'])): ?>
          <div style="font-size:0.83rem; color:#475569; margin-bottom:8px;"><?= htmlspecialchars($rv['comments']) ?></div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:10px; padding-top:10px; border-top:1px dashed #f1f5f9;">
          <div style="font-size:0.78rem; color:#64748b;">
            Logged by: <?= htmlspecialchars($rv['recorded_by'] ?? 'Headteacher') ?> &bull;
            <?= date('d M Y', strtotime($rv['created_at'])) ?>
            <?php if (!empty($rv['follow_up_date'])): ?>
              &bull; Follow-up: <strong><?= date('d M Y', strtotime($rv['follow_up_date'])) ?></strong>
            <?php endif; ?>
          </div>

          <!-- Quick status update -->
          <form method="POST" style="display:flex; gap:6px; align-items:center;">
            <input type="hidden" name="review_id" value="<?= $rv['review_id'] ?>">
            <select name="new_status" class="rpt-filter-select" style="padding:4px 6px; font-size:0.78rem;">
              <option value="Pending"     <?= $rv['review_status'] === 'Pending'     ? 'selected' : '' ?>>Pending</option>
              <option value="In Progress" <?= $rv['review_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Resolved"    <?= $rv['review_status'] === 'Resolved'    ? 'selected' : '' ?>>Resolved</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-secondary btn-small">Update</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</div>
</div>
<?php include __DIR__ . '/../common/footer.php'; ?>
</body>
</html>
