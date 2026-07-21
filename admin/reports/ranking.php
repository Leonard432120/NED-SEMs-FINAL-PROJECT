<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/ranking.php
   EDM/Admin: National & Divisional Leaderboards
   ────────────────────────────────────────────────────────────────
   Handles large volume of schools via:
   • District Filtering (Chitipa, Karonga, Likoma, Mzimba, Nkhata Bay, Rumphi)
   • School Name Search
   • Clean Pagination (10 schools per page)
   • Summary KPI Cards (Division Champion, Top District Leaders)
   • Performance Tiering & Rankings
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/report_stats.php';
require_once __DIR__ . '/../../common/report_charts.php';
require_once __DIR__ . '/../../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

// ── Dropdown Data ────────────────────────────────────────────────
$exams_list = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.year 
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    ORDER BY e.year DESC, e.exam_name ASC
")->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

// Filters & Tab Navigation
$active_tab   = isset($_GET['tab']) && in_array($_GET['tab'], ['schools', 'districts', 'subjects', 'improvements'], true) ? $_GET['tab'] : 'schools';
$filter_dist  = trim($_GET['district'] ?? '');
$search_query = trim($_GET['search'] ?? '');
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page     = 10;

$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);

/* ════════════════════════════════════════════════════════════════
   DATA COLLECTION
   ════════════════════════════════════════════════════════════════ */
$schools_ranking    = [];
$districts_ranking  = [];
$subjects_ranking   = [];
$improvements       = [];
$total_records      = 0;
$top_champion       = null;

if ($selected_exam_id > 0) {

    if ($active_tab === 'schools') {

        // 1. Full Schools Leaderboard (for calculating overall rank & total count)
        $where_parts = ["r.exam_id = ?", "r.status = 'published'"];
        $bind_types  = "i";
        $bind_values = [$selected_exam_id];

        if ($filter_dist !== '') {
            $where_parts[] = "sc.district = ?";
            $bind_types   .= "s";
            $bind_values[] = $filter_dist;
        }
        if ($search_query !== '') {
            $where_parts[] = "sc.school_name LIKE ?";
            $bind_types   .= "s";
            $bind_values[] = "%" . $search_query . "%";
        }

        $where_sql = implode(" AND ", $where_parts);

        // Fetch overall ranked list
        $all_schools_sql = "
            SELECT sc.school_id, sc.school_name, sc.district, sc.school_type,
                   AVG(r.average_score) as avg_score,
                   COUNT(r.result_id) as candidates_count
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            JOIN schools sc ON s.school_id = sc.school_id
            WHERE {$where_sql}
            GROUP BY sc.school_id
            ORDER BY avg_score DESC
        ";

        $stmt = $conn->prepare($all_schools_sql);
        $stmt->bind_param($bind_types, ...$bind_values);
        $stmt->execute();
        $all_schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Assign global ranks
        foreach ($all_schools as $idx => &$sch) {
            $sch['global_rank'] = $idx + 1;
            $sch['avg_score']   = round((float)$sch['avg_score'], 1);
            $sch['category']    = stats_performance_category($sch['avg_score']);
        }
        unset($sch);

        if (!empty($all_schools)) {
            $top_champion = $all_schools[0];
        }

        $total_records = count($all_schools);
        $pagination    = paginate($total_records, $current_page, $per_page);
        $offset        = ($pagination['page'] - 1) * $per_page;

        $schools_ranking = array_slice($all_schools, $offset, $per_page);

    } elseif ($active_tab === 'districts') {

        // 2. Districts Leaderboard
        $dist_stmt = $conn->prepare("
            SELECT sc.district,
                   AVG(r.average_score) as avg_score,
                   COUNT(r.result_id) as candidates_count,
                   COUNT(DISTINCT sc.school_id) as school_count
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            JOIN schools sc ON s.school_id = sc.school_id
            WHERE r.exam_id = ? AND r.status='published'
            GROUP BY sc.district
            ORDER BY avg_score DESC
        ");
        $dist_stmt->bind_param("i", $selected_exam_id);
        $dist_stmt->execute();
        $districts_ranking = $dist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $dist_stmt->close();

        foreach ($districts_ranking as $idx => &$ds) {
            $ds['global_rank'] = $idx + 1;
            $ds['avg_score']   = round((float)$ds['avg_score'], 1);
            $ds['category']    = stats_performance_category($ds['avg_score']);
        }
        unset($ds);
        $total_records = count($districts_ranking);
        $pagination    = paginate($total_records, 1, 50);

    } elseif ($active_tab === 'subjects') {

        // 3. Subjects Leaderboard
        $subj_stmt = $conn->prepare("
            SELECT s.subject_name, s.subject_code, s.category,
                   AVG(m.score) as avg_score,
                   COUNT(*) as marks_count
            FROM marks m
            JOIN subjects s ON m.subject_id = s.subject_id
            WHERE m.exam_id = ? AND m.status = 'approved'
            GROUP BY m.subject_id
            ORDER BY avg_score DESC
        ");
        $subj_stmt->bind_param("i", $selected_exam_id);
        $subj_stmt->execute();
        $subjects_ranking = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $subj_stmt->close();

        foreach ($subjects_ranking as $idx => &$sj) {
            $sj['global_rank'] = $idx + 1;
            $sj['avg_score']   = round((float)$sj['avg_score'], 1);
            $sj['perf_cat']    = stats_performance_category($sj['avg_score']);
        }
        unset($sj);
        $total_records = count($subjects_ranking);
        $pagination    = paginate($total_records, 1, 50);

    } elseif ($active_tab === 'improvements') {

        // 4. Improvements Leaderboard
        $curr_ex_stmt = $conn->prepare("SELECT class, year FROM exams WHERE exam_id = ?");
        $curr_ex_stmt->bind_param("i", $selected_exam_id);
        $curr_ex_stmt->execute();
        $curr_ex = $curr_ex_stmt->get_result()->fetch_assoc();
        $curr_ex_stmt->close();

        if ($curr_ex) {
            $prev_year = (int)$curr_ex['year'] - 1;
            $prev_ex_stmt = $conn->prepare("SELECT exam_id FROM exams WHERE class = ? AND year = ? LIMIT 1");
            $prev_ex_stmt->bind_param("si", $curr_ex['class'], $prev_year);
            $prev_ex_stmt->execute();
            $prev_exam_id = (int)($prev_ex_stmt->get_result()->fetch_assoc()['exam_id'] ?? 0);
            $prev_ex_stmt->close();

            if ($prev_exam_id > 0) {
                $where_parts = ["1=1"];
                $bind_types  = "ii";
                $bind_values = [$selected_exam_id, $prev_exam_id];

                if ($filter_dist !== '') {
                    $where_parts[] = "sc.district = ?";
                    $bind_types   .= "s";
                    $bind_values[] = $filter_dist;
                }
                if ($search_query !== '') {
                    $where_parts[] = "sc.school_name LIKE ?";
                    $bind_types   .= "s";
                    $bind_values[] = "%" . $search_query . "%";
                }

                $where_sql = implode(" AND ", $where_parts);

                $imp_sql = "
                    SELECT sc.school_id, sc.school_name, sc.district,
                           AVG(r_curr.average_score) as curr_avg,
                           AVG(r_prev.average_score) as prev_avg
                    FROM schools sc
                    JOIN students s_curr ON sc.school_id = s_curr.school_id
                    JOIN results r_curr ON s_curr.student_id = r_curr.student_id AND r_curr.exam_id = ? AND r_curr.status='published'
                    JOIN students s_prev ON sc.school_id = s_prev.school_id
                    JOIN results r_prev ON s_prev.student_id = r_prev.student_id AND r_prev.exam_id = ? AND r_prev.status='published'
                    WHERE {$where_sql}
                    GROUP BY sc.school_id
                ";

                $imp_stmt = $conn->prepare($imp_sql);
                $imp_stmt->bind_param($bind_types, ...$bind_values);
                $imp_stmt->execute();
                $imp_data = $imp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $imp_stmt->close();

                foreach ($imp_data as $row) {
                    $change = stats_growth_rate((float)$row['prev_avg'], (float)$row['curr_avg']);
                    $improvements[] = [
                        'school_name' => $row['school_name'],
                        'district'    => $row['district'],
                        'prev_avg'    => round((float)$row['prev_avg'], 1),
                        'curr_avg'    => round((float)$row['curr_avg'], 1),
                        'change'      => $change
                    ];
                }

                usort($improvements, fn($a, $b) => $b['change'] <=> $a['change']);

                foreach ($improvements as $idx => &$imp) {
                    $imp['global_rank'] = $idx + 1;
                }
                unset($imp);

                $total_records = count($improvements);
                $pagination    = paginate($total_records, $current_page, $per_page);
                $offset        = ($pagination['page'] - 1) * $per_page;
                $improvements  = array_slice($improvements, $offset, $per_page);
            }
        }
    }
}

$conn->close();

function perf_badge_style(string $cat): string {
    return match($cat) {
        'Excellent' => 'background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;',
        'Good' => 'background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;',
        'Satisfactory' => 'background: #fefce8; color: #ca8a04; border: 1px solid #fef08a;',
        'Needs Improvement' => 'background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5;',
        'Critical' => 'background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;',
        default => 'background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;'
    };
}

$portal_title = 'NED-SEMS | Leaderboard Rankings';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Divisional Leaderboard &amp; Rankings | NED-SEMS</title>
    <meta name="description" content="Searchable and filterable divisional performance rankings for schools, districts, subjects and annual improvements">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
        .tabs-header {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
            gap: 16px;
        }
        .tab-link {
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .tab-link:hover {
            color: var(--info-color);
        }
        .tab-link.active {
            color: var(--info-color);
            border-bottom-color: var(--info-color);
        }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 0.8rem;
            font-weight: 800;
        }
        .rank-gold { background: #fef3c7; color: #92400e; }
        .rank-silver { background: #e2e8f0; color: #334155; }
        .rank-bronze { background: #fed7aa; color: #9a3412; }
        .rank-default { background: #f1f5f9; color: #64748b; }
        .champion-card {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">

        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Divisional Rankings &amp; Improvements Leaderboard</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Northern Education Division | Administrator</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Rankings &amp; Performance Leaderboard</h2>
                <p class="page-subtitle">Divisional standings across schools, districts, subjects, and annual improvement trends</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Leaderboard</button>
            </div>
        </div>

        <!-- ═══ FILTER PANEL ═══ -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form" id="ranking-filter-form">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">

                <!-- Exam Selection -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="rf-exam">Examination Paper</label>
                    <select name="exam_id" id="rf-exam" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (in_array($active_tab, ['schools', 'improvements'], true)): ?>
                    <!-- District Selection -->
                    <div class="rpt-filter-group">
                        <label class="rpt-filter-label" for="rf-district">District</label>
                        <select name="district" id="rf-district" class="rpt-filter-select" onchange="this.form.submit()">
                            <option value="">All Districts</option>
                            <?php foreach ($districts_list as $d): ?>
                                <option value="<?= htmlspecialchars($d['district']) ?>" <?= $filter_dist === $d['district'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['district']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="rpt-filter-group">
                        <label class="rpt-filter-label" for="rf-search">Search School</label>
                        <input type="text" name="search" id="rf-search" class="rpt-filter-input" placeholder="School name..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                <?php endif; ?>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="ranking.php?tab=<?= htmlspecialchars($active_tab) ?>&exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- ═══ TABS NAVIGATION ═══ -->
        <div class="tabs-header">
            <?php
                $base_qs = "exam_id=" . $selected_exam_id . ($filter_dist ? "&district=" . urlencode($filter_dist) : "") . ($search_query ? "&search=" . urlencode($search_query) : "");
            ?>
            <a href="ranking.php?tab=schools&<?= $base_qs ?>" class="tab-link <?= $active_tab === 'schools' ? 'active' : '' ?>">School Rankings</a>
            <a href="ranking.php?tab=districts&<?= $base_qs ?>" class="tab-link <?= $active_tab === 'districts' ? 'active' : '' ?>">District Standings</a>
            <a href="ranking.php?tab=subjects&<?= $base_qs ?>" class="tab-link <?= $active_tab === 'subjects' ? 'active' : '' ?>">Subject Rankings</a>
            <a href="ranking.php?tab=improvements&<?= $base_qs ?>" class="tab-link <?= $active_tab === 'improvements' ? 'active' : '' ?>">Most Improved</a>
        </div>

        <!-- Top Champion Banner for Schools Tab -->
        <?php if ($active_tab === 'schools' && $top_champion && empty($filter_dist) && empty($search_query) && $current_page === 1): ?>
            <div class="champion-card">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700; margin-bottom: 4px;">Division Leader #1 Ranked School</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #ffffff;"><?= htmlspecialchars($top_champion['school_name']) ?></div>
                    <div style="font-size: 0.85rem; color: #cbd5e1; margin-top: 2px;">
                        <?= htmlspecialchars($top_champion['district']) ?> District · <?= $top_champion['candidates_count'] ?> candidates assessed
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 2rem; font-weight: 900; color: #4ade80;"><?= $top_champion['avg_score'] ?>%</div>
                    <div style="font-size: 0.78rem; color: #94a3b8;">Division Mean Score</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ═══ MAIN LEADERBOARD CARD ═══ -->
        <div class="card">
            <?php if ($active_tab === 'schools'): ?>
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 style="margin: 0;">School Performance Rankings</h3>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                            Displaying <strong><?= count($schools_ranking) ?></strong> of <strong><?= $total_records ?></strong> participating school centers
                            <?= $filter_dist ? "in <strong>" . htmlspecialchars($filter_dist) . "</strong>" : "" ?>
                        </p>
                    </div>
                    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>School Name</th>
                                <th>District</th>
                                <th>School Type</th>
                                <th class="val-col">Candidates Sat</th>
                                <th class="val-col">Mean Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schools_ranking)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No compiled school rankings found matching your filter criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schools_ranking as $sch): ?>
                                    <?php $rc = $sch['global_rank'] === 1 ? 'rank-gold' : ($sch['global_rank'] === 2 ? 'rank-silver' : ($sch['global_rank'] === 3 ? 'rank-bronze' : 'rank-default')); ?>
                                    <tr>
                                        <td class="rank-col">
                                            <span class="rank-badge <?= $rc ?>"><?= $sch['global_rank'] ?></span>
                                        </td>
                                        <td style="font-weight:700; color: #0f172a;"><?= htmlspecialchars($sch['school_name']) ?></td>
                                        <td><?= htmlspecialchars($sch['district']) ?></td>
                                        <td>
                                            <span style="font-size:0.75rem; background:#f1f5f9; padding:3px 8px; border-radius:4px; font-weight:700; color: #475569;">
                                                <?= htmlspecialchars($sch['school_type'] ?: 'Secondary') ?>
                                            </span>
                                        </td>
                                        <td class="val-col"><?= $sch['candidates_count'] ?></td>
                                        <td class="val-col" style="font-weight: 800; color: <?= $sch['avg_score'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                            <?= number_format($sch['avg_score'], 1) ?>%
                                        </td>
                                        <td><?= stats_badge($sch['category']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <div style="margin-top: 16px;">
                        <?= render_pagination($pagination, 'ranking.php') ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($active_tab === 'districts'): ?>
                <div class="section-header" style="margin-bottom: 16px;">
                    <h3>District Leaderboard Standings</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">Aggregated average scores across all districts in the division</p>
                </div>
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>District</th>
                                <th class="val-col">Schools Count</th>
                                <th class="val-col">Total Candidates Sat</th>
                                <th class="val-col">District Mean Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($districts_ranking)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No compiled district rankings found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($districts_ranking as $ds): ?>
                                    <?php $rc = $ds['global_rank'] === 1 ? 'rank-gold' : ($ds['global_rank'] === 2 ? 'rank-silver' : ($ds['global_rank'] === 3 ? 'rank-bronze' : 'rank-default')); ?>
                                    <tr>
                                        <td class="rank-col">
                                            <span class="rank-badge <?= $rc ?>"><?= $ds['global_rank'] ?></span>
                                        </td>
                                        <td style="font-weight:700; color: #0f172a;"><?= htmlspecialchars($ds['district']) ?></td>
                                        <td class="val-col"><?= $ds['school_count'] ?></td>
                                        <td class="val-col"><?= number_format($ds['candidates_count']) ?></td>
                                        <td class="val-col" style="font-weight: 800; color: <?= $ds['avg_score'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                            <?= number_format($ds['avg_score'], 1) ?>%
                                        </td>
                                        <td><?= stats_badge($ds['category']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($active_tab === 'subjects'): ?>
                <div class="section-header" style="margin-bottom: 16px;">
                    <h3>Subject Syllabus Leaderboard Standings</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">Divisional subject standings ordered by mean score</p>
                </div>
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>Subject Code</th>
                                <th>Subject Syllabus</th>
                                <th>Category</th>
                                <th class="val-col">Entries</th>
                                <th class="val-col">Syllabus Mean Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects_ranking)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No compiled subject rankings found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subjects_ranking as $sj): ?>
                                    <?php $rc = $sj['global_rank'] === 1 ? 'rank-gold' : ($sj['global_rank'] === 2 ? 'rank-silver' : ($sj['global_rank'] === 3 ? 'rank-bronze' : 'rank-default')); ?>
                                    <tr>
                                        <td class="rank-col">
                                            <span class="rank-badge <?= $rc ?>"><?= $sj['global_rank'] ?></span>
                                        </td>
                                        <td style="font-weight:700; color:var(--info-color);"><?= htmlspecialchars($sj['subject_code']) ?></td>
                                        <td style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($sj['subject_name']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($sj['category'] ?: 'Theory')) ?></td>
                                        <td class="val-col"><?= number_format($sj['marks_count']) ?></td>
                                        <td class="val-col" style="font-weight: 800; color:#16a34a;"><?= number_format($sj['avg_score'], 1) ?>%</td>
                                        <td><?= stats_badge($sj['perf_cat']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($active_tab === 'improvements'): ?>
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 style="margin:0;">Annual School Improvements Leaderboard</h3>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">Comparing current exam average with the previous year's cycle for same class</p>
                    </div>
                    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>School Name</th>
                                <th>District</th>
                                <th class="val-col">Previous Cycle Avg</th>
                                <th class="val-col">Current Cycle Avg</th>
                                <th class="val-col">YoY Change</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($improvements)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No historical previous year comparison data found to calculate improvements.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($improvements as $imp): ?>
                                    <?php $rc = $imp['global_rank'] === 1 ? 'rank-gold' : ($imp['global_rank'] === 2 ? 'rank-silver' : ($imp['global_rank'] === 3 ? 'rank-bronze' : 'rank-default')); ?>
                                    <tr>
                                        <td class="rank-col">
                                            <span class="rank-badge <?= $rc ?>"><?= $imp['global_rank'] ?></span>
                                        </td>
                                        <td style="font-weight:700; color: #0f172a;"><?= htmlspecialchars($imp['school_name']) ?></td>
                                        <td><?= htmlspecialchars($imp['district']) ?></td>
                                        <td class="val-col"><?= $imp['prev_avg'] ?>%</td>
                                        <td class="val-col" style="font-weight:bold;"><?= $imp['curr_avg'] ?>%</td>
                                        <td class="val-col" style="font-weight:800; color: <?= $imp['change'] >= 0 ? '#16a34a' : '#dc2626' ?>;">
                                            <?= $imp['change'] >= 0 ? '+' : '' ?><?= $imp['change'] ?>%
                                        </td>
                                        <td>
                                            <?= stats_trend_html($imp['change'] >= 0 ? 'up' : 'down') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <div style="margin-top: 16px;">
                        <?= render_pagination($pagination, 'ranking.php') ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>