<?php
/**
 * headteacher/teacher_rankings.php
 * ─────────────────────────────────────────────────────────────────
 * HT: Comprehensive Teacher Rankings Dashboard
 * Renders active teacher evaluation standings with configurable
 * weights, detailed statistics, smart insights, and snapshot saving.
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/teacher_performance_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$head_id   = (int)$_SESSION['user_id'];

// Fetch school info
$stmt = $conn->prepare("SELECT school_name, district FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// ── 1. CONFIGURABLE WEIGHTS SETUP ─────────────────────────────────
$w_prep    = isset($_GET['w_prep'])    ? (float)$_GET['w_prep']    : (isset($_SESSION['w_prep'])    ? (float)$_SESSION['w_prep']    : 20.0);
$w_quality = isset($_GET['w_quality']) ? (float)$_GET['w_quality'] : (isset($_SESSION['w_quality']) ? (float)$_SESSION['w_quality'] : 20.0);
$w_mod     = isset($_GET['w_mod'])     ? (float)$_GET['w_mod']     : (isset($_SESSION['w_mod'])     ? (float)$_SESSION['w_mod']     : 15.0);
$w_marking = isset($_GET['w_marking']) ? (float)$_GET['w_marking'] : (isset($_SESSION['w_marking']) ? (float)$_SESSION['w_marking'] : 20.0);
$w_time    = isset($_GET['w_time'])    ? (float)$_GET['w_time']    : (isset($_SESSION['w_time'])    ? (float)$_SESSION['w_time']    : 15.0);
$w_part    = isset($_GET['w_part'])    ? (float)$_GET['w_part']    : (isset($_SESSION['w_part'])    ? (float)$_SESSION['w_part']    : 10.0);

$total_w = $w_prep + $w_quality + $w_mod + $w_marking + $w_time + $w_part;
if ($total_w <= 0) {
    $w_prep = 20.0; $w_quality = 20.0; $w_mod = 15.0; $w_marking = 20.0; $w_time = 15.0; $w_part = 10.0;
    $total_w = 100.0;
}

$weights = [
    'prep'    => $w_prep,
    'quality' => $w_quality,
    'mod'     => $w_mod,
    'marking' => $w_marking,
    'time'    => $w_time,
    'part'    => $w_part
];

// Persist in session
$_SESSION['w_prep']    = $w_prep;
$_SESSION['w_quality'] = $w_quality;
$_SESSION['w_mod']     = $w_mod;
$_SESSION['w_marking'] = $w_marking;
$_SESSION['w_time']    = $w_time;
$_SESSION['w_part']    = $w_part;

// ── 2. GET ACTIVE TEACHERS AND CALCULATE PERFORMANCE ──────────────
$teachers_query = $conn->query("
    SELECT user_id, name, email, role, major_subject, minor_subject, teacher_category, status
    FROM users
    WHERE school_id = $school_id AND role = 'teacher' AND status = 'active'
    ORDER BY name ASC
");

$teachers_list = [];
if ($teachers_query) {
    while ($t = $teachers_query->fetch_assoc()) {
        $t['metrics'] = calculate_teacher_metrics($t['user_id'], $school_id, $weights, $conn);
        $teachers_list[] = $t;
    }
}

// Sort by overall score descending
usort($teachers_list, function($a, $b) {
    return $b['metrics']['overall_score'] <=> $a['metrics']['overall_score'];
});

// Generate smart insights
$insights = generate_school_smart_insights($teachers_list, $conn);

// ── 3. SAVE HISTORY SNAPSHOT PROCESS ──────────────────────────────
$snapshot_msg = '';
$snapshot_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_snapshot'])) {
    $term = trim($_POST['snapshot_term'] ?? '');
    $year = (int)($_POST['snapshot_year'] ?? 0);

    if (empty($term) || $year <= 0) {
        $snapshot_err = 'Please select a valid term and year.';
    } else {
        $conn->begin_transaction();
        try {
            foreach ($teachers_list as $rank => $t) {
                $teacher_id = $t['user_id'];
                $score      = $t['metrics']['overall_score'];
                $position   = $rank + 1;
                $level      = $t['metrics']['performance_level'];
                $recomm     = $t['metrics']['promotion_recommendation'];

                $stmt = $conn->prepare("
                    INSERT INTO teacher_performance_history
                        (teacher_id, school_id, term, year, overall_score, ranking, promotion_status, recommendation)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        overall_score    = VALUES(overall_score),
                        ranking          = VALUES(ranking),
                        promotion_status = VALUES(promotion_status),
                        recommendation   = VALUES(recommendation)
                ");
                $stmt->bind_param("iiiidiss", $teacher_id, $school_id, $term, $year, $score, $position, $level, $recomm);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            $snapshot_msg = "Successfully saved performance snapshot for $term, $year!";
        } catch (Exception $e) {
            $conn->rollback();
            $snapshot_err = 'Failed to save snapshot: ' . $e->getMessage();
        }
    }
}

$conn->close();

$portal_title = 'NED-SEMS | Teacher Rankings';
$module_css   = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Performance Rankings | NED-SEMS Headteacher</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
  <style>
    .tpm-weights-form {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
    }
    .tpm-weights-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 12px;
      margin-bottom: 12px;
    }
    @media (max-width: 992px) {
      .tpm-weights-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }
    @media (max-width: 600px) {
      .tpm-weights-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    .weight-item {
      display: flex;
      flex-direction: column;
    }
    .weight-item label {
      font-size: 0.78rem;
      font-weight: 600;
      color: #475569;
      margin-bottom: 4px;
    }
    .weight-item input {
      padding: 6px 10px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 0.88rem;
    }
    .insights-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }
    @media (max-width: 768px) {
      .insights-grid {
        grid-template-columns: 1fr;
      }
    }
    .insights-box {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
    }
    .insights-box h4 {
      margin-top: 0;
      margin-bottom: 12px;
      color: #0f172a;
      border-bottom: 1px solid #f1f5f9;
      padding-bottom: 8px;
    }
    .insight-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      font-size: 0.88rem;
      border-bottom: 1px dashed #f1f5f9;
    }
    .insight-row:last-child {
      border-bottom: none;
    }
    .insight-row strong {
      color: #1e293b;
    }
    .badge-level {
      padding: 4px 8px;
      font-size: 0.75rem;
      border-radius: 9999px;
      font-weight: 600;
      display: inline-block;
    }
    .badge-level--Outstanding { background: #fef3c7; color: #d97706; }
    .badge-level--Excellent { background: #dcfce7; color: #15803d; }
    .badge-level--VeryGood { background: #e0f2fe; color: #0369a1; }
    .badge-level--Good { background: #f1f5f9; color: #475569; }
    .badge-level--Fair { background: #ffedd5; color: #c2410c; }
    .badge-level--Poor { background: #fee2e2; color: #b91c1c; }
    .snapshot-panel {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
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
        <h1 class="page-title">Teacher Rankings</h1>
        <p class="page-subtitle">Weighted professional evaluations for<?= htmlspecialchars($school_info['school_name'] ?? '') ?></p>
      </div>
      <div class="header-actions">
        <a href="<?= BASE_URL ?>/headteacher/performance_review.php" class="btn btn-secondary">Performance Reviews</a>
      </div>
    </div>

    <!-- Alert banners -->
    <?php if ($snapshot_msg): ?>
      <div class="htf-alert htf-alert--success" style="margin-bottom:20px;"><?= htmlspecialchars($snapshot_msg) ?></div>
    <?php endif; ?>
    <?php if ($snapshot_err): ?>
      <div class="htf-alert htf-alert--error" style="margin-bottom:20px;"><?= htmlspecialchars($snapshot_err) ?></div>
    <?php endif; ?>

    <!-- ─── SLIDERS / CONFIGURATOR ──────────────────────────────── -->
    <details class="tpm-weights-form" open>
      <summary style="cursor:pointer; font-weight:700; color:#1e293b; margin-bottom:8px;">
        ⚙️ Configurable Evaluation Weights (Sum: <span id="weight-sum-label"><?= $total_w ?></span>%)
      </summary>
      <form method="GET" style="margin-top:12px;" id="weights-form">
        <div class="tpm-weights-grid">
          <div class="weight-item">
            <label for="w_prep">Exam Prep</label>
            <input type="number" name="w_prep" id="w_prep" class="weight-input" min="0" max="100" value="<?= $w_prep ?>" oninput="updateSum()">
          </div>
          <div class="weight-item">
            <label for="w_quality">Item Quality</label>
            <input type="number" name="w_quality" id="w_quality" class="weight-input" min="0" max="100" value="<?= $w_quality ?>" oninput="updateSum()">
          </div>
          <div class="weight-item">
            <label for="w_mod">Moderation</label>
            <input type="number" name="w_mod" id="w_mod" class="weight-input" min="0" max="100" value="<?= $w_mod ?>" oninput="updateSum()">
          </div>
          <div class="weight-item">
            <label for="w_marking">Marking Comp.</label>
            <input type="number" name="w_marking" id="w_marking" class="weight-input" min="0" max="100" value="<?= $w_marking ?>" oninput="updateSum()">
          </div>
          <div class="weight-item">
            <label for="w_time">Timeliness</label>
            <input type="number" name="w_time" id="w_time" class="weight-input" min="0" max="100" value="<?= $w_time ?>" oninput="updateSum()">
          </div>
          <div class="weight-item">
            <label for="w_part">Participation</label>
            <input type="number" name="w_part" id="w_part" class="weight-input" min="0" max="100" value="<?= $w_part ?>" oninput="updateSum()">
          </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span id="weight-warn" style="color:#ef4444; font-size:0.8rem; font-weight:600; display:none;">Weights must sum to exactly 100%</span>
          <div>
            <button type="submit" class="btn btn-primary" id="apply-weights-btn">Apply &amp; Recalculate</button>
            <a href="teacher_rankings.php" class="btn btn-secondary">Reset to Defaults</a>
          </div>
        </div>
      </form>
    </details>

    <!-- ─── SMART INSIGHTS PANEL ────────────────────────────────── -->
    <div class="insights-grid">
      <div class="insights-box">
        <h4>Operational Standout Achievements</h4>
        <div class="insight-row">
          <span>Best Performing Teacher:</span>
          <strong><?= $insights['best_performing'] ? htmlspecialchars($insights['best_performing']['name']) . ' (' . $insights['best_performing']['metrics']['overall_score'] . '%)' : 'N/A' ?></strong>
        </div>
        <div class="insight-row">
          <span>Highest Student Pass Rate:</span>
          <strong><?= $insights['highest_student_pass_rate'] ? htmlspecialchars($insights['highest_student_pass_rate']['name']) . ' (' . $insights['highest_student_pass_rate']['metrics']['student_pass_rate'] . '%)' : 'N/A' ?></strong>
        </div>
        <div class="insight-row">
          <span>Fastest Marking Submissions:</span>
          <strong><?= $insights['fastest_marker'] ? htmlspecialchars($insights['fastest_marker']['name']) . ' (' . $insights['fastest_marker']['metrics']['timeliness_score'] . '%)' : 'N/A' ?></strong>
        </div>
        <div class="insight-row">
          <span>Best Exam Composer:</span>
          <strong><?= $insights['best_exam_composer'] ? htmlspecialchars($insights['best_exam_composer']['name']) : 'N/A' ?></strong>
        </div>
        <div class="insight-row">
          <span>Highest Moderation Rate:</span>
          <strong><?= $insights['highest_moderation_success'] ? htmlspecialchars($insights['highest_moderation_success']['name']) . ' (' . $insights['highest_moderation_success']['metrics']['moderation_success_score'] . '%)' : 'N/A' ?></strong>
        </div>
        <?php if ($insights['most_improved']): ?>
          <div class="insight-row">
            <span>Most Improved Teacher:</span>
            <strong><?= htmlspecialchars($insights['most_improved']['teacher']['name']) ?> (+<?= $insights['most_improved']['improvement'] ?>% vs last term)</strong>
          </div>
        <?php endif; ?>
      </div>

      <div class="insights-box">
        <h4>Promotion &amp; Mentoring Summaries</h4>
        <div class="insight-row">
          <span>Eligible for Promotion (Score &gt;= 95%):</span>
          <strong><?= count($insights['promotion_eligible']) ?> Teachers</strong>
        </div>
        <div class="insight-row">
          <span>At Risk (Score &lt; 70%):</span>
          <strong style="color:<?= count($insights['at_risk']) > 0 ? '#ef4444' : '#1e293b' ?>;"><?= count($insights['at_risk']) ?> Teachers</strong>
        </div>
        <div class="insight-row">
          <span>Missed Multiple Deadlines:</span>
          <strong><?= count($insights['missed_deadlines']) ?> Teachers</strong>
        </div>
      </div>
    </div>

    <!-- ─── SNAPSHOT ARCHIVING PANEL ───────────────────────────── -->
    <div class="snapshot-panel no-print">
      <div>
        <h4 style="margin:0; color:#1e3a8a;">Archive Standings Snapshot</h4>
        <p style="margin:4px 0 0; font-size:0.8rem; color:#1e40af;">Freeze current scores and positions into performance records for historical lookup.</p>
      </div>
      <form method="POST" style="display:flex; gap:10px; align-items:center;">
        <select name="snapshot_term" class="rpt-filter-select" required>
          <option value="">— Select Term —</option>
          <option value="Term 1">Term 1</option>
          <option value="Term 2">Term 2</option>
          <option value="Term 3">Term 3</option>
        </select>
        <select name="snapshot_year" class="rpt-filter-select" required>
          <option value="">— Select Year —</option>
          <?php for($y = date('Y'); $y >= date('Y')-2; $y--): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" name="save_snapshot" class="btn btn-primary">Save Snapshots</button>
      </form>
    </div>

    <!-- ─── RANKINGS TABLE ──────────────────────────────────────── -->
    <div class="card">
      <div class="section-header">
        <h3>Performance Leaderboard Standings</h3>
      </div>
      <div class="rpt-table-wrap">
        <table class="rpt-table">
          <thead>
            <tr>
              <th style="width:70px;">Rank</th>
              <th>Teacher Details</th>
              <th>Department / Major</th>
              <th class="val-col">Authored</th>
              <th class="val-col">Marking Comp.</th>
              <th class="val-col">Timeliness</th>
              <th class="val-col" style="font-weight:700;">Overall Score</th>
              <th>Performance Level</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($teachers_list)): ?>
              <tr>
                <td colspan="9" class="empty-state">No active teachers found at this school.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($teachers_list as $index => $t): ?>
                <?php 
                  $rank = $index + 1;
                  $medal = '';
                  if ($rank === 1) $medal = ' ';
                  elseif ($rank === 2) $medal = '';
                  elseif ($rank === 3) $medal = '';
                  
                  $m = $t['metrics'];
                  $lvl_class = str_replace(' ', '', $m['performance_level']);
                ?>
                <tr>
                  <td style="font-weight:700; font-size:1.1rem; text-align:center;">
                    <?= $medal ? $medal : $rank ?>
                  </td>
                  <td>
                    <div style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($t['name']) ?></div>
                    <div style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($t['email']) ?> · Category: <?= htmlspecialchars($t['teacher_category']) ?></div>
                  </td>
                  <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($t['major_subject'] ?? 'General') ?></div>
                    <?php if (!empty($t['minor_subject'])): ?>
                      <div style="font-size:0.75rem; color:#64748b;">Minor: <?= htmlspecialchars($t['minor_subject']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="val-col">
                    <?= $m['questions_written'] ?> items
                    <div style="font-size:0.72rem; color:#64748b;">Avg AI Quality: <?= $m['avg_ai_quality_score'] !== null ? $m['avg_ai_quality_score'].'%' : 'N/A' ?></div>
                  </td>
                  <td class="val-col">
                    <?= number_format($m['marking_completion_score'], 0) ?>%
                    <div style="font-size:0.72rem; color:#64748b;"><?= $m['completed_assignments'] ?> / <?= $m['total_assignments'] ?> sheets</div>
                  </td>
                  <td class="val-col">
                    <?= number_format($m['timeliness_score'], 0) ?>%
                    <?php if ($m['late_submissions'] > 0): ?>
                      <div style="font-size:0.72rem; color:#ef4444; font-weight:600;"><?= $m['late_submissions'] ?> Late</div>
                    <?php else: ?>
                      <div style="font-size:0.72rem; color:#15803d;">All On-time</div>
                    <?php endif; ?>
                  </td>
                  <td class="val-col" style="font-weight:700; font-size:1.05rem; color:#1d4ed8;">
                    <?= number_format($m['overall_score'], 1) ?>%
                  </td>
                  <td>
                    <span class="badge-level badge-level--<?= $lvl_class ?>"><?= $m['performance_level'] ?></span>
                    <div style="font-size:0.72rem; color:#64748b; margin-top:2px; font-weight:600;"><?= $m['promotion_recommendation'] ?></div>
                  </td>
                  <td>
                    <a href="teacher_performance.php?id=<?= $t['user_id'] ?>" class="btn btn-secondary btn-small">Dashboard</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>

<script>
  function updateSum() {
    var sum = 0;
    var inputs = document.getElementsByClassName('weight-input');
    for (var i = 0; i < inputs.length; i++) {
      var val = parseFloat(inputs[i].value) || 0;
      sum += val;
    }
    document.getElementById('weight-sum-label').innerText = sum;
    
    var warn = document.getElementById('weight-warn');
    var btn = document.getElementById('apply-weights-btn');
    if (sum !== 100) {
      warn.style.display = 'inline';
      btn.disabled = true;
    } else {
      warn.style.display = 'none';
      btn.disabled = false;
    }
  }
  
  // Run onload to verify sum is 100
  window.onload = function() {
    updateSum();
  };
</script>
</body>
</html>
