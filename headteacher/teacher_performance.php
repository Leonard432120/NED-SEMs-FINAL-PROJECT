<?php
/**
 * headteacher/teacher_performance.php
 * ─────────────────────────────────────────────────────────────────
 * HT: Comprehensive Teacher Performance Dashboard
 * Shows weighted scores, AI-driven insights, historical trend,
 * subject breakdown, exam contributions, and moderation record.
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
$teacher_id = (int)($_GET['id'] ?? 0);

// Fetch teacher
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND school_id = ? AND role = 'teacher'");
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    header("Location: manage_teachers.php");
    exit();
}

// Read weights from session (set by rankings page, or default)
$weights = [
    'prep'    => (float)($_SESSION['w_prep']    ?? 20.0),
    'quality' => (float)($_SESSION['w_quality'] ?? 20.0),
    'mod'     => (float)($_SESSION['w_mod']     ?? 15.0),
    'marking' => (float)($_SESSION['w_marking'] ?? 20.0),
    'time'    => (float)($_SESSION['w_time']    ?? 15.0),
    'part'    => (float)($_SESSION['w_part']    ?? 10.0),
];

// Calculate comprehensive metrics
$m = calculate_teacher_metrics($teacher_id, $school_id, $weights, $conn);

// Dynamic strengths/weaknesses/achievements
$insights = generate_teacher_insights($m);

// Compute school-wide ranking
$rank_query = $conn->query("
    SELECT user_id FROM users
    WHERE school_id = $school_id AND role = 'teacher' AND status = 'active'
    ORDER BY user_id ASC
");
$all_teacher_ids = [];
while ($r = $rank_query->fetch_assoc()) {
    $all_teacher_ids[] = (int)$r['user_id'];
}

$rank_scores = [];
foreach ($all_teacher_ids as $tid) {
    $tm = calculate_teacher_metrics($tid, $school_id, $weights, $conn);
    $rank_scores[$tid] = $tm['overall_score'];
}
arsort($rank_scores);
$school_rank = array_search($teacher_id, array_keys($rank_scores)) + 1;
$total_teachers = count($rank_scores);

// Historical snapshots
$hist_stmt = $conn->prepare("
    SELECT year, term, overall_score, ranking, promotion_status, recommendation
    FROM teacher_performance_history
    WHERE teacher_id = ? AND school_id = ?
    ORDER BY year ASC, term ASC
");
$hist_stmt->bind_param("ii", $teacher_id, $school_id);
$hist_stmt->execute();
$history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hist_stmt->close();

// Subject performance for charts
$subj_stmt = $conn->prepare("
    SELECT sub.subject_name,
           ROUND(AVG(m.score),1) AS avg_score,
           COUNT(m.mark_id) AS entries,
           SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.subject_id
    JOIN students st  ON m.student_id = st.student_id
    WHERE m.teacher_id = ? AND st.school_id = ? AND m.status = 'approved'
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
");
$subj_stmt->bind_param("ii", $teacher_id, $school_id);
$subj_stmt->execute();
$subject_perf = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subj_stmt->close();

// Questions authored
$qstmt = $conn->prepare("
    SELECT q.moderation_status, e.exam_name, q.ai_quality_score,
           q.question_text, q.created_at
    FROM questions q
    JOIN exams e ON q.exam_id = e.exam_id
    WHERE q.created_by = ?
    ORDER BY q.created_at DESC
    LIMIT 10
");
$qstmt->bind_param("i", $teacher_id);
$qstmt->execute();
$recent_questions = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qstmt->close();

// Recent mark submissions
$recent_stmt = $conn->prepare("
    SELECT st.name AS student_name, st.exam_number, st.class,
           sub.subject_name, m.score, m.grade, m.status, m.created_at
    FROM marks m
    JOIN students st  ON m.student_id = st.student_id
    JOIN subjects sub ON m.subject_id = sub.subject_id
    WHERE m.teacher_id = ? AND st.school_id = ?
    ORDER BY m.created_at DESC
    LIMIT 10
");
$recent_stmt->bind_param("ii", $teacher_id, $school_id);
$recent_stmt->execute();
$recent_entries = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// Performance reviews for this teacher
$reviews_stmt = $conn->prepare("
    SELECT tpr.meeting_date, tpr.improvement_plan, tpr.advice_given,
           tpr.follow_up_date, tpr.review_status, tpr.comments,
           u.name AS recorded_by
    FROM teacher_performance_reviews tpr
    LEFT JOIN users u ON u.user_id = tpr.created_by
    WHERE tpr.teacher_id = ? AND tpr.school_id = ?
    ORDER BY tpr.meeting_date DESC
    LIMIT 5
");
$reviews_stmt->bind_param("ii", $teacher_id, $school_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();

$conn->close();

$class_data   = classify_performance($m['overall_score']);
$lvl_class    = str_replace(' ', '', $class_data['level']);

// Chart data serialisation
$hist_labels  = array_map(fn($h) => $h['term'] . ' ' . $h['year'], $history);
$hist_scores  = array_map(fn($h) => (float)$h['overall_score'], $history);
$subj_labels  = array_column($subject_perf, 'subject_name');
$subj_scores  = array_map(fn($s) => (float)$s['avg_score'], $subject_perf);

$portal_title = 'NED-SEMS | Teacher Performance';
$module_css   = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Performance Dashboard | NED-SEMS</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
  <style>
    .tpd-hero {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 24px;
      margin-bottom: 24px;
    }
    @media (max-width: 768px) { .tpd-hero { grid-template-columns: 1fr; } }

    .tpd-profile-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 24px 16px;
      text-align: center;
    }
    .tpd-avatar {
      width: 90px; height: 90px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--primary-dark);
      margin-bottom: 12px;
    }
    .tpd-score-ring {
      width: 100px; height: 100px;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      margin: 14px auto;
      border: 6px solid;
    }
    .tpd-score-ring--Outstanding { border-color: #f59e0b; background: #fef3c7; }
    .tpd-score-ring--Excellent   { border-color: #22c55e; background: #dcfce7; }
    .tpd-score-ring--VeryGood    { border-color: #3b82f6; background: #e0f2fe; }
    .tpd-score-ring--Good        { border-color: #94a3b8; background: #f1f5f9; }
    .tpd-score-ring--Fair        { border-color: #f97316; background: #ffedd5; }
    .tpd-score-ring--Poor        { border-color: #ef4444; background: #fee2e2; }

    .tpd-kpi-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    @media (max-width: 992px) { .tpd-kpi-grid { grid-template-columns: repeat(2,1fr); } }

    .tpd-kpi-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 14px 16px;
    }
    .tpd-kpi-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; }
    .tpd-kpi-value { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 2px 0; }
    .tpd-kpi-bar   { height: 6px; border-radius: 99px; background: #f1f5f9; overflow: hidden; margin-top: 6px; }
    .tpd-kpi-fill  { height: 100%; border-radius: 99px; }

    .tpd-section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }
    @media (max-width: 900px) { .tpd-section-grid { grid-template-columns: 1fr; } }

    .tpd-list-item {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      padding: 8px 0;
      border-bottom: 1px dashed #f1f5f9;
      font-size: 0.88rem;
    }
    .tpd-list-item:last-child { border-bottom: none; }
    .tpd-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      margin-top: 5px;
      flex-shrink: 0;
    }
    .tpd-dot--green  { background: #22c55e; }
    .tpd-dot--red    { background: #ef4444; }
    .tpd-dot--gold   { background: #f59e0b; }

    .hist-table td, .hist-table th {
      padding: 8px 12px;
      font-size: 0.85rem;
    }

    .badge-rstatus--Pending    { background:#fef3c7; color:#d97706; }
    .badge-rstatus--InProgress { background:#dbeafe; color:#1d4ed8; }
    .badge-rstatus--Resolved   { background:#dcfce7; color:#15803d; }

    canvas { max-width: 100%; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
<?php include __DIR__ . '/../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
  <div>
    <h1 class="page-title">Teacher Performance Dashboard</h1>
    <p class="page-subtitle">Comprehensive professional evaluation profile for <strong><?= htmlspecialchars($teacher['name']) ?></strong></p>
  </div>
  <div class="header-actions">
    <a href="teacher_rankings.php" class="btn btn-secondary">← Rankings</a>
    <a href="view_teacher.php?id=<?= $teacher_id ?>" class="btn btn-secondary">Profile</a>
    <a href="performance_review.php?teacher_id=<?= $teacher_id ?>" class="btn btn-primary">Log Review</a>
  </div>
</div>

<!-- ═══ HERO SECTION: Profile + Score Ring + KPIs ═══ -->
<div class="tpd-hero">
  <!-- Profile Card -->
  <div class="tpd-profile-card">
    <img src="<?= $teacher['profile_image']
        ? BASE_URL . '/uploads/profiles/' . htmlspecialchars($teacher['profile_image'])
        : BASE_URL . '/assets/images/default-avatar.png' ?>"
         alt="Profile" class="tpd-avatar"
         onerror="this.src='<?= BASE_URL ?>/assets/images/default-avatar.png'">
    <h3 style="margin:0 0 4px;"><?= htmlspecialchars($teacher['name']) ?></h3>
    <div style="font-size:0.8rem; color:#64748b; margin-bottom:12px;">
      <?= htmlspecialchars($teacher['teacher_category'] ?? 'Teacher') ?> &bull;
      <?= htmlspecialchars($teacher['qualification'] ?? '') ?>
    </div>
    
    <div class="tpd-score-ring tpd-score-ring--<?= $lvl_class ?>">
      <span style="font-size:1.5rem; font-weight:900; line-height:1;"><?= number_format($m['overall_score'],1) ?>%</span>
      <span style="font-size:0.65rem; font-weight:700; margin-top:2px;">OVERALL</span>
    </div>

    <div style="font-weight:700; font-size:1rem; color:#1e293b;"><?= $class_data['level'] ?></div>
    <div style="font-size:0.78rem; color:#64748b; margin:4px 0 12px;"><?= $class_data['recommendation'] ?></div>

    <div style="background:#f8fafc; border-radius:6px; padding:10px; text-align:left; font-size:0.82rem;">
      <div style="display:flex;justify-content:space-between;padding:4px 0;">
        <span style="color:#64748b;">School Rank</span>
        <strong>#<?= $school_rank ?> of <?= $total_teachers ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;">
        <span style="color:#64748b;">Major Subject</span>
        <strong><?= htmlspecialchars($teacher['major_subject'] ?? '—') ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;">
        <span style="color:#64748b;">Employment No.</span>
        <strong><?= htmlspecialchars($teacher['employment_number'] ?? '—') ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;">
        <span style="color:#64748b;">Total Logins</span>
        <strong><?= (int)$m['login_count'] ?></strong>
      </div>
    </div>
  </div>

  <!-- Right KPI Scores Grid -->
  <div>
    <div class="tpd-kpi-grid">
      <?php
      $kpi_items = [
        ['Exam Preparation',         $m['prep_score'],               '#7c3aed', "{$m['questions_written']} questions authored"],
        ['Question Quality',         $m['quality_score'],            '#0891b2', "Avg AI Score: " . ($m['avg_ai_quality_score'] !== null ? $m['avg_ai_quality_score'].'%' : 'N/A')],
        ['Moderation Success',        $m['moderation_success_score'], '#16a34a', "{$m['questions_approved']} of {$m['questions_written']} approved"],
        ['Marking Completion',        $m['marking_completion_score'], '#2563eb', "{$m['completed_assignments']} of {$m['total_assignments']} sheets done"],
        ['Submission Timeliness',     $m['timeliness_score'],         '#d97706', $m['late_submissions'] > 0 ? "{$m['late_submissions']} late submission(s)" : "All on time"],
        ['Professional Participation',$m['participation_score'],      '#db2777', "{$m['system_actions']} actions · {$m['login_count']} logins"],
      ];
      foreach ($kpi_items as [$label, $score, $color, $sub]):
      ?>
      <div class="tpd-kpi-card">
        <div class="tpd-kpi-label"><?= $label ?></div>
        <div class="tpd-kpi-value" style="color:<?= $color ?>;"><?= number_format($score, 1) ?>%</div>
        <div style="font-size:0.75rem; color:#94a3b8;"><?= htmlspecialchars($sub) ?></div>
        <div class="tpd-kpi-bar">
          <div class="tpd-kpi-fill" style="width:<?= min(100, $score) ?>%; background:<?= $color ?>;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Student academic impact metrics -->
    <div style="display:grid; grid-template-columns: repeat(3,1fr); gap:12px; margin-top:16px;">
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; text-align:center;">
        <div style="font-size:0.75rem; color:#64748b; font-weight:700;">AVG STUDENT SCORE</div>
        <div style="font-size:1.6rem; font-weight:900; color:#2563eb;"><?= $m['avg_student_score'] ?>%</div>
      </div>
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; text-align:center;">
        <div style="font-size:0.75rem; color:#64748b; font-weight:700;">STUDENT PASS RATE</div>
        <div style="font-size:1.6rem; font-weight:900; color:#15803d;"><?= $m['student_pass_rate'] ?>%</div>
      </div>
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; text-align:center;">
        <div style="font-size:0.75rem; color:#64748b; font-weight:700;">SCRIPTS MARKED</div>
        <div style="font-size:1.6rem; font-weight:900; color:#0f172a;"><?= $m['total_marked_students'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ STRENGTHS / WEAKNESSES / ACHIEVEMENTS ═══ -->
<div class="tpd-section-grid">
  <!-- Strengths -->
  <div class="card" style="padding:18px;">
    <h4 style="margin-top:0; color:#15803d; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Strengths</h4>
    <?php foreach ($insights['strengths'] as $s): ?>
      <div class="tpd-list-item">
        <span class="tpd-dot tpd-dot--green"></span>
        <span><?= htmlspecialchars($s) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Weaknesses -->
  <div class="card" style="padding:18px;">
    <h4 style="margin-top:0; color:#dc2626; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Areas for Improvement</h4>
    <?php foreach ($insights['weaknesses'] as $w): ?>
      <div class="tpd-list-item">
        <span class="tpd-dot tpd-dot--red"></span>
        <span><?= htmlspecialchars($w) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Achievements -->
<div class="card" style="padding:18px; margin-bottom:24px;">
  <h4 style="margin-top:0; color:#d97706; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Achievements &amp; Contributions</h4>
  <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:10px;">
    <?php foreach ($insights['achievements'] as $a): ?>
      <div style="background:#fefce8; border:1px solid #fde68a; border-radius:6px; padding:10px; font-size:0.88rem;">
        <span style="font-weight:700; color:#d97706; margin-right:4px;"></span>
        <?= htmlspecialchars($a) ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══ CHARTS SECTION ═══ -->
<div class="tpd-section-grid">
  <!-- Subject Average Scores -->
  <div class="card" style="padding:18px;">
    <h4 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Subject Average Scores</h4>
    <?php if (!empty($subject_perf)): ?>
      <?php foreach ($subject_perf as $sp): ?>
        <?php
          $pass_rate_sub = $sp['entries'] > 0 ? round(($sp['passed'] / $sp['entries']) * 100, 0) : 0;
          $avg = $sp['avg_score'];
          $bar_color = $avg >= 70 ? '#22c55e' : ($avg >= 45 ? '#f59e0b' : '#ef4444');
        ?>
        <div style="margin-bottom:14px;">
          <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; color:#1e293b; margin-bottom:3px;">
            <span><?= htmlspecialchars($sp['subject_name']) ?></span>
            <span><?= $avg ?>% &bull; Pass: <?= $pass_rate_sub ?>%</span>
          </div>
          <div style="height:8px; background:#f1f5f9; border-radius:99px; overflow:hidden;">
            <div style="width:<?= min(100, $avg) ?>%; height:100%; background:<?= $bar_color ?>; border-radius:99px; transition:width 0.5s;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-state">No approved marking records found.</p>
    <?php endif; ?>
  </div>

  <!-- Historical Trend -->
  <div class="card" style="padding:18px;">
    <h4 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Historical Performance Trend</h4>
    <?php if (!empty($history)): ?>
      <canvas id="histChart" height="200"></canvas>
      <script>
        window.addEventListener('DOMContentLoaded', function() {
          var ctx = document.getElementById('histChart');
          if (!ctx || typeof Chart === 'undefined') return;
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: <?= json_encode($hist_labels) ?>,
              datasets: [{
                label: 'Overall Score (%)',
                data: <?= json_encode($hist_scores) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.08)',
                borderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.35
              }]
            },
            options: {
              responsive: true,
              scales: { y: { min: 0, max: 100 } },
              plugins: { legend: { display: false } }
            }
          });
        });
      </script>
    <?php else: ?>
      <p class="empty-state" style="text-align:center; padding:30px 0;">No historical snapshot data yet. Use the <a href="teacher_rankings.php" style="color:var(--info-color);">Rankings page</a> to save a term snapshot.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ EXAM CONTRIBUTIONS ═══ -->
<?php if (!empty($recent_questions)): ?>
<div class="card" style="padding:18px; margin-bottom:24px;">
  <h4 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Question Bank Contributions</h4>
  <div class="rpt-table-wrap">
    <table class="rpt-table">
      <thead>
        <tr>
          <th>Question (excerpt)</th>
          <th>Exam</th>
          <th class="val-col">AI Score</th>
          <th>Moderation Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_questions as $q): ?>
          <?php
            $mod_status = $q['moderation_status'];
            $mod_color = ['approved' => '#15803d', 'pending' => '#92400e', 'revise' => '#c2410c', 'rejected' => '#b91c1c'][$mod_status] ?? '#334155';
            $mod_bg    = ['approved' => '#dcfce7', 'pending' => '#fef3c7', 'revise' => '#ffedd5', 'rejected' => '#fee2e2'][$mod_status] ?? '#f1f5f9';
          ?>
          <tr>
            <td style="max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              <?= htmlspecialchars(substr($q['question_text'], 0, 80)) ?>…
            </td>
            <td><?= htmlspecialchars($q['exam_name'] ?? 'N/A') ?></td>
            <td class="val-col"><?= $q['ai_quality_score'] !== null ? number_format($q['ai_quality_score'], 0).'%' : '—' ?></td>
            <td>
              <span style="background:<?= $mod_bg ?>; color:<?= $mod_color ?>; padding:3px 10px; border-radius:999px; font-size:0.75rem; font-weight:700;">
                <?= ucfirst($mod_status) ?>
              </span>
            </td>
            <td><?= date('d M Y', strtotime($q['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ═══ RECENT MARK SUBMISSIONS ═══ -->
<div class="card" style="padding:18px; margin-bottom:24px;">
  <h4 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Recent Script Mark Submissions</h4>
  <?php if (!empty($recent_entries)): ?>
    <div class="rpt-table-wrap">
      <table class="rpt-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Subject</th>
            <th class="val-col">Score</th>
            <th class="val-col">Grade</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_entries as $re): ?>
            <tr>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($re['student_name']) ?></div>
                <div style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($re['exam_number'] ?? '') ?> · <?= htmlspecialchars($re['class'] ?? '') ?></div>
              </td>
              <td><?= htmlspecialchars($re['subject_name']) ?></td>
              <td class="val-col"><strong><?= round($re['score'],1) ?>%</strong></td>
              <td class="val-col"><?= htmlspecialchars($re['grade'] ?? '—') ?></td>
              <td><span class="badge badge-<?= strtolower($re['status']) ?>"><?= ucfirst($re['status']) ?></span></td>
              <td><?= date('d M Y', strtotime($re['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-state">No recent mark submissions found.</p>
  <?php endif; ?>
</div>

<!-- ═══ PERFORMANCE REVIEW HISTORY ═══ -->
<?php if (!empty($reviews)): ?>
<div class="card" style="padding:18px; margin-bottom:24px;">
  <h4 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Performance Review Records</h4>
  <?php foreach ($reviews as $rv): ?>
    <?php $sc = str_replace(' ', '', $rv['review_status']); ?>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; margin-bottom:12px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <strong style="color:#1e293b;">Meeting: <?= date('d M Y', strtotime($rv['meeting_date'])) ?></strong>
        <span class="badge-rstatus--<?= $sc ?>" style="padding:3px 10px; border-radius:999px; font-size:0.75rem; font-weight:700;">
          <?= htmlspecialchars($rv['review_status']) ?>
        </span>
      </div>
      <?php if (!empty($rv['improvement_plan'])): ?>
        <div style="font-size:0.83rem; margin-bottom:4px;"><strong>Improvement Plan:</strong> <?= htmlspecialchars($rv['improvement_plan']) ?></div>
      <?php endif; ?>
      <?php if (!empty($rv['advice_given'])): ?>
        <div style="font-size:0.83rem; margin-bottom:4px;"><strong>Advice Given:</strong> <?= htmlspecialchars($rv['advice_given']) ?></div>
      <?php endif; ?>
      <?php if (!empty($rv['comments'])): ?>
        <div style="font-size:0.83rem; color:#475569;"><?= htmlspecialchars($rv['comments']) ?></div>
      <?php endif; ?>
      <?php if (!empty($rv['follow_up_date'])): ?>
        <div style="font-size:0.78rem; color:#64748b; margin-top:6px;">Follow-up: <?= date('d M Y', strtotime($rv['follow_up_date'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>
</body>
</html>
