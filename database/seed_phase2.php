<?php
/**
 * NED-SEMS Phase 2 Seeder — runs steps 9–18 that failed in full_seed.php
 */
require_once dirname(__DIR__) . '/config/db.php';
ini_set('max_execution_time', 300);
set_time_limit(300);

$conn = get_db_connection();
$conn->query("SET FOREIGN_KEY_CHECKS=0");
$conn->query("SET SQL_MODE=''");

$errors  = [];
$inserts = 0;

function exec_sql($conn, $sql, &$inserts, &$errors) {
    if ($conn->query($sql)) {
        $inserts++;
    } else {
        $errors[] = $conn->error . " | SQL: " . substr($sql, 0, 120);
    }
}

echo "<pre style='font-family:monospace;font-size:13px;'>";
echo "=== NED-SEMS PHASE 2 SEEDER ===\n\n";

// ════════════════════════════════════════════════════════════
// 9. SEED TEACHER_PERFORMANCE_HISTORY
// Schema: history_id, teacher_id, school_id, term, year, overall_score, ranking, promotion_status, recommendation
// ════════════════════════════════════════════════════════════
echo "9. Seeding teacher_performance_history...\n";

// Compute per teacher per exam: avg score given → use as overall_score proxy
// Group by teacher + school + (term/year derived from exam)
$exam_term_year = [
    25 => ['term' => 'Term 1', 'year' => 2025],
    26 => ['term' => 'Term 1', 'year' => 2025],
    27 => ['term' => 'Term 1', 'year' => 2026],
    24 => ['term' => 'Term 2', 'year' => 2026],
];

$tph_sql = "
    SELECT 
        m.teacher_id,
        s.school_id,
        m.exam_id,
        COUNT(*) as marks_count,
        AVG(m.score) as avg_score
    FROM marks m
    JOIN students s ON s.student_id = m.student_id
    WHERE m.exam_id IN (24, 25, 26, 27)
    GROUP BY m.teacher_id, s.school_id, m.exam_id
";
$tph_res = $conn->query($tph_sql);

$tph_rows = [];
while ($tr = $tph_res->fetch_assoc()) {
    $tph_rows[] = $tr;
}

// Rank teachers within each school+exam
$grouped_tph = [];
foreach ($tph_rows as $tr) {
    $key = $tr['school_id'] . '_' . $tr['exam_id'];
    $grouped_tph[$key][] = $tr;
}
foreach ($grouped_tph as $key => &$grp) {
    usort($grp, fn($a,$b) => $b['avg_score'] <=> $a['avg_score']);
    foreach ($grp as $pos => &$rr) {
        $rr['rank'] = $pos + 1;
    }
}
unset($grp, $rr);

$promotion_levels = ['Excellent','Good','Satisfactory','Needs Improvement'];
$recommendations  = [
    'Recommend for leadership role in department.',
    'Continue current approach. Strong performance.',
    'Satisfactory. Encourage more CPD participation.',
    'Set improvement targets. Schedule review next term.',
];

foreach ($tph_rows as $tr) {
    $tid  = $tr['teacher_id'];
    $sid  = $tr['school_id'];
    $eid  = $tr['exam_id'];
    $avg  = round((float)$tr['avg_score'], 2);
    $key  = $sid . '_' . $eid;
    $rank = $tr['rank'] ?? 1;

    $term = $exam_term_year[$eid]['term'];
    $year = $exam_term_year[$eid]['year'];

    // overall_score: blend avg score given with a professionalism score
    $professionalism = rand(70, 95);
    $overall = round(($avg * 0.6 + $professionalism * 0.4), 2);

    if ($overall >= 80)     $promo_idx = 0;
    elseif ($overall >= 65) $promo_idx = 1;
    elseif ($overall >= 50) $promo_idx = 2;
    else                    $promo_idx = 3;

    $promotion = $promotion_levels[$promo_idx];
    $rec       = $recommendations[$promo_idx];

    $sql = "INSERT IGNORE INTO teacher_performance_history
            (teacher_id, school_id, term, year, overall_score, ranking, promotion_status, recommendation, created_at)
            VALUES ($tid, $sid, '$term', $year, $overall, $rank, '$promotion', '$rec', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   teacher_performance_history seeded: " . count($tph_rows) . " records.\n";

// ════════════════════════════════════════════════════════════
// 10. SEED TEACHER PERFORMANCE REVIEWS
// Schema: teacher_id, school_id, meeting_date, improvement_plan, advice_given, follow_up_date, review_status, comments, created_by
// ════════════════════════════════════════════════════════════
echo "10. Seeding teacher_performance_reviews...\n";

$conn->query("DELETE FROM teacher_performance_reviews WHERE created_by = 127");

$teachers_school39 = [
    [124, 39, 'Resolved',    'Maintain current marking standards.', 'Keep up excellent question drafting.'],
    [125, 39, 'Resolved',    'Submit marks before deadline each term.', 'Strong geography knowledge. Good rapport with students.'],
    [126, 39, 'Resolved',    'Improve question diversity in English assessments.', 'Solid performance. Literature sections need more variety.'],
    [129, 39, 'In Progress', 'Complete all assigned marksheets before end of term.', 'Capable teacher, needs better time management.'],
    [130, 39, 'Pending',     'Attend next CPD workshop on ICT pedagogy.', 'Good with technology. Needs stronger assessment skills.'],
];

foreach ($teachers_school39 as $tr) {
    $sql = "INSERT INTO teacher_performance_reviews
            (teacher_id, school_id, meeting_date, improvement_plan, advice_given, follow_up_date, review_status, comments, created_by, created_at)
            VALUES ({$tr[0]}, {$tr[1]}, '2026-07-01', '{$tr[3]}', '{$tr[4]}', '2026-08-01', '{$tr[2]}',
                    'Reviewed during end of term staff meeting.', 127, NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
// Add reviews for school 35 teachers
$teachers_school35 = [
    [123, 35, 'Resolved',    'Continue Biology lab work integration.', 'Excellent teacher. Highest student pass rates.'],
    [131, 35, 'In Progress', 'Submit Geography field trip reports.', 'Good classroom management skills.'],
];
foreach ($teachers_school35 as $tr) {
    $sql = "INSERT INTO teacher_performance_reviews
            (teacher_id, school_id, meeting_date, improvement_plan, advice_given, follow_up_date, review_status, comments, created_by, created_at)
            VALUES ({$tr[0]}, {$tr[1]}, '2026-07-01', '{$tr[3]}', '{$tr[4]}', '2026-08-01', '{$tr[2]}',
                    'Mid-year review conducted.', 122, NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Teacher reviews seeded.\n";

// ════════════════════════════════════════════════════════════
// 11. SEED HT FINDINGS
// ════════════════════════════════════════════════════════════
echo "11. Seeding HT findings...\n";

$conn->query("DELETE FROM ht_findings WHERE finding_id > 4");

$ht_findings_data = [
    [39, 26, 'declining', 38.50, 'teacher_absenteeism',   'Two teachers on sick leave during term.',           'remedial_classes',       'Saturday extra lessons arranged.',   'closed'],
    [39, 24, 'flat',      52.00, 'resource_shortage',     'Shortage of textbooks in Form 4.',                  'resource_procurement',   'Requested 60 books from DEO.',       'open'],
    [38, 25, 'improving', 61.00, 'positive_intervention', 'School feeding programme improved attendance.',      'celebration_recognition','Awarded best performing school.',    'closed'],
    [38, 26, 'flat',      55.00, 'curriculum_gap',         'Students weak in algebra topics.',                  'peer_mentoring',         'Peer tutoring sessions twice weekly.','open'],
    [35, 25, 'improving', 72.00, 'positive_intervention', 'Additional Science lab opened.',                    'celebration_recognition','Recognised by district.',            'closed'],
    [35, 26, 'declining', 44.00, 'student_discipline',    'Disruptions during revision period.',               'pastoral_support',       'Counselling sessions initiated.',    'open'],
    [32, 26, 'declining', 36.00, 'low_attendance',        'High absenteeism rate in Form 2.',                  'attendance_campaign',    'Parent meetings held monthly.',      'open'],
    [29, 25, 'flat',      49.00, 'assessment_irregularity','Marking inconsistencies noted.',                   'curriculum_revision',    'Standardised marking scheme shared.','closed'],
];

foreach ($ht_findings_data as $f) {
    $sql = "INSERT IGNORE INTO ht_findings
            (school_id, exam_id, recorded_by, trend, pass_rate_pct, cause_category, cause_detail,
             action_category, action_detail, lifecycle_status, created_at)
            VALUES ({$f[0]}, {$f[1]}, 127, '{$f[2]}', {$f[3]}, '{$f[4]}', '{$f[5]}',
                    '{$f[6]}', '{$f[7]}', '{$f[8]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   HT findings seeded.\n";

// ════════════════════════════════════════════════════════════
// 12. SEED HT FINDING OUTCOMES
// ════════════════════════════════════════════════════════════
echo "12. Seeding HT finding outcomes...\n";

$conn->query("DELETE FROM ht_finding_outcomes WHERE outcome_id > 2");

$fi_res = $conn->query("SELECT finding_id, school_id FROM ht_findings WHERE lifecycle_status='closed'");
while ($fi = $fi_res->fetch_assoc()) {
    $fid  = $fi['finding_id'];
    $fsid = $fi['school_id'];
    $outcomes = ['improved','improved','no_change'];
    $oc = $outcomes[rand(0,2)];
    $sql = "INSERT IGNORE INTO ht_finding_outcomes
            (finding_id, school_id, exam_id, recorded_by, outcome, outcome_detail, created_at)
            VALUES ($fid, $fsid, 27, 127, '$oc', 'Reviewed at end of term. Intervention worked.', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   HT finding outcomes seeded.\n";

// ════════════════════════════════════════════════════════════
// 13. SEED DIVISIONAL FINDINGS
// ════════════════════════════════════════════════════════════
echo "13. Seeding divisional findings...\n";

$conn->query("DELETE FROM div_findings WHERE finding_id > 2");

$div_data = [
    [25, 'flat',      45.50, 'teacher_shortage',            'Not enough qualified science teachers.',          'teacher_recruitment',    'Posted 12 teachers from Mzuzu.',    'closed'],
    [26, 'declining', 40.20, 'learning_material_deficiency','Books not delivered before exams.',               'textbook_distribution',  'Distributed 500 books to schools.', 'closed'],
    [24, 'flat',      55.00, 'curriculum_misalignment',     'Syllabus changed mid-year without training.',     'teacher_capacity_building','CPD sessions run in July 2026.',   'open'],
];
foreach ($div_data as $d) {
    $sql = "INSERT IGNORE INTO div_findings
            (exam_id, recorded_by, trend, avg_score_pct, cause_category, cause_detail,
             action_category, action_detail, lifecycle_status, created_at)
            VALUES ({$d[0]}, 2, '{$d[1]}', {$d[2]}, '{$d[3]}', '{$d[4]}',
                    '{$d[5]}', '{$d[6]}', '{$d[7]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}

// Outcomes for closed div findings
$conn->query("DELETE FROM div_finding_outcomes WHERE outcome_id > 1");
$div_fi_res = $conn->query("SELECT finding_id FROM div_findings WHERE lifecycle_status='closed'");
while ($dfi = $div_fi_res->fetch_assoc()) {
    $dfid = $dfi['finding_id'];
    $sql = "INSERT IGNORE INTO div_finding_outcomes
            (finding_id, exam_id, recorded_by, outcome, outcome_detail, created_at)
            VALUES ($dfid, 27, 2, 'improved', 'Improvement observed in next exam cycle.', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Divisional findings seeded.\n";

// ════════════════════════════════════════════════════════════
// 14. SEED ANNOUNCEMENTS
// ════════════════════════════════════════════════════════════
echo "14. Seeding announcements...\n";

$conn->query("DELETE FROM announcements WHERE id > 2");

$announcements = [
    [127, 39, 'JCE 2026 Marks Deadline Reminder', 'All teachers must submit marks for JCE 2026 by 20th July 2026. Late submissions will be flagged for compliance review.'],
    [2, null, 'System Maintenance Notice', 'NED-SEMS will undergo scheduled maintenance on 25th July 2026 from 22:00 to 02:00.'],
    [127, 39, 'Form 2 Result Review Meeting', 'Heads of Department meeting to review Form 2 performance is scheduled for 28th July 2026 at 09:00.'],
    [2, null, 'New Grading Rubric Published', 'Updated grading rubric for JCE and MSCE examinations is now available in the documents section.'],
    [2, null, 'Teacher CPD Workshop July 2026', 'All teachers are required to attend the CPD workshop on 30th July 2026. Attendance is mandatory.'],
];
foreach ($announcements as $a) {
    $school_val = $a[1] ? $a[1] : 'NULL';
    $sql = "INSERT INTO announcements (title, content, published_by, school_id, created_at)
            VALUES ('{$a[2]}', '{$a[3]}', {$a[0]}, $school_val, NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Announcements seeded.\n";

// ════════════════════════════════════════════════════════════
// 15. SEED SCHOOL_REPORTS
// ════════════════════════════════════════════════════════════
echo "15. Seeding school_reports...\n";

$conn->query("DELETE FROM school_reports WHERE report_id > 1");

$sr_data = [
    [39, 'Term 1', 2025, 58, 78.5, 52.0, 'Good term overall. Science subjects need improvement. Extra lessons recommended.'],
    [39, 'Term 2', 2025, 58, 82.0, 60.5, 'Improved attendance and pass rate compared to Term 1. Continue with Saturday lessons.'],
    [39, 'Term 1', 2026, 58, 75.0, 48.0, 'Early term results pending full compilation. Some teachers behind on marking.'],
    [38, 'Term 1', 2025, 55, 80.0, 65.0, 'Strong performance across all departments. Science stream leads.'],
    [38, 'Term 2', 2025, 55, 85.0, 70.0, 'Best term on record. School feeding programme contributing positively.'],
    [35, 'Term 1', 2025, 50, 88.0, 72.5, 'Boarding school advantage reflected in results. Science lab opened.'],
    [35, 'Term 2', 2025, 50, 90.0, 75.0, 'Science stream outperformed humanities. Distinction rate at 30%.'],
    [29, 'Term 1', 2025, 60, 72.0, 50.0, 'Average performance. Remedial sessions needed for weaker students.'],
    [32, 'Term 1', 2025, 45, 68.0, 42.0, 'Below average attendance impacting results. Parent engagement needed.'],
    [34, 'Term 1', 2025, 48, 74.0, 55.0, 'Moderate performance. New head of department showing early positive impact.'],
];
foreach ($sr_data as $sr) {
    $sql = "INSERT INTO school_reports (school_id, term, year, total_students, attendance_rate, pass_rate, comments, created_at)
            VALUES ({$sr[0]}, '{$sr[1]}', {$sr[2]}, {$sr[3]}, {$sr[4]}, {$sr[5]}, '{$sr[6]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   School reports seeded.\n";

// ════════════════════════════════════════════════════════════
// 16. UPDATE EXAM DATES
// ════════════════════════════════════════════════════════════
echo "16. Updating exam dates...\n";
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-05-15 00:00:00', start_date='2026-05-01', end_date='2026-05-14' WHERE exam_id=25", $inserts, $errors);
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-05-20 00:00:00', start_date='2026-05-01', end_date='2026-05-18' WHERE exam_id=26", $inserts, $errors);
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-11-01 00:00:00', start_date='2026-10-05', end_date='2026-11-20' WHERE exam_id=24", $inserts, $errors);
echo "   Exam dates updated.\n";

// ════════════════════════════════════════════════════════════
// 17. SEED SUBJECT_ASSIGNMENTS
// ════════════════════════════════════════════════════════════
echo "17. Seeding subject_assignments...\n";

$conn->query("DELETE FROM subject_assignments WHERE assignment_id > 51");

$sa_data = [
    [104, 127, 'science',    'item_writer'],
    [105, 129, 'science',    'item_writer'],
    [106, 130, 'science',    'item_writer'],
    [108, 130, 'science',    'item_writer'],
    [109, 126, 'language',   'item_writer'],
    [110, 124, 'humanities', 'item_writer'],
    [111, 126, 'language',   'item_writer'],
    [112, 130, 'science',    'item_writer'],
    [117, 125, 'humanities', 'item_writer'],
    [118, 125, 'humanities', 'item_writer'],
    [121, 126, 'language',   'item_writer'],
    [107, 124, 'science',    'moderator'],
    [109, 127, 'language',   'moderator'],
    [110, 125, 'humanities', 'moderator'],
];
foreach ($sa_data as $sa) {
    $sql = "INSERT IGNORE INTO subject_assignments
            (subject_id, teacher_id, teacher_category, role, assigned_by, assigned_at, email_sent, status)
            VALUES ({$sa[0]}, {$sa[1]}, '{$sa[2]}', '{$sa[3]}', 2, NOW(), 1, 'assigned')";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Subject assignments seeded.\n";

// ════════════════════════════════════════════════════════════
// 18. SEED RESULT WORKFLOW LOGS
// ════════════════════════════════════════════════════════════
echo "18. Seeding result_workflow_logs...\n";
$rwl_data = [
    ['compile_results', 2, 'ADMIN', null, 'submitted', 'Compiled exam ID 25: bulk compilation — all schools'],
    ['compile_results', 2, 'ADMIN', null, 'submitted', 'Compiled exam ID 26: bulk compilation — all schools'],
    ['publish_results', 2, 'ADMIN', 'submitted', 'published', 'Published results for exam ID 25'],
    ['publish_results', 2, 'ADMIN', 'submitted', 'published', 'Published results for exam ID 26'],
    ['compile_results', 2, 'ADMIN', null, 'draft', 'Compiled exam ID 27: partial marks (70% submitted)'],
    ['ht_approval',    127,'HT',    'draft', 'head_approved', 'HT approved submitted marks for JCE 2026'],
];
foreach ($rwl_data as $rwl) {
    $from = $rwl[3] ? "'{$rwl[3]}'" : 'NULL';
    $sql = "INSERT INTO result_workflow_logs
            (result_id, action, performed_by, role, from_status, to_status, notes, created_at)
            VALUES (0, '{$rwl[0]}', {$rwl[1]}, '{$rwl[2]}', $from, '{$rwl[4]}', '{$rwl[5]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Result workflow logs seeded.\n";

// ════════════════════════════════════════════════════════════
// 19. SEED COMMENTS
// ════════════════════════════════════════════════════════════
echo "19. Seeding comments...\n";

$conn->query("DELETE FROM comments WHERE comment_id > 6");

$comments_data = [
    [127, 39, 27, 'exam_comment', 'Term 1', 2026, 'Teachers are progressing well with JCE 2026 marking. Follow up needed for late submissions.'],
    [127, 39, 26, 'exam_comment', 'Term 1', 2025, 'JCE 2025 completed. Pass rate slightly lower than expected. Remedial plan in place.'],
    [2,   39, 25, 'exam_comment', 'Term 1', 2025, 'MSCE 2025: Form 4 performance strong in humanities, weak in science.'],
    [127, 39, null,'school_note', 'Term 1', 2026, 'School infrastructure improvement needed — leaking classrooms reported to DEO.'],
    [2,   38, null,'school_note', 'Term 2', 2025, 'Karonga Community Secondary showing consistent improvement across all departments.'],
];
foreach ($comments_data as $c) {
    $exam_val   = $c[2] ? $c[2] : 'NULL';
    $school_val = $c[1] ? $c[1] : 'NULL';
    $sql = "INSERT INTO comments (user_id, school_id, exam_id, comment_type, term, year, comment, created_at)
            VALUES ({$c[0]}, $school_val, $exam_val, '{$c[3]}', '{$c[4]}', {$c[5]}, '{$c[6]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Comments seeded.\n";

// ════════════════════════════════════════════════════════════
// DONE
// ════════════════════════════════════════════════════════════
$conn->query("SET FOREIGN_KEY_CHECKS=1");

echo "\n========================================\n";
echo "PHASE 2 COMPLETE\n";
echo "========================================\n";
echo "Total operations: $inserts\n";

// Final counts
$tables = ['marks','results','students','marking_assignments','ht_findings',
           'teacher_performance_history','teacher_performance_reviews',
           'school_reports','div_findings','announcements','comments'];
echo "\nFinal row counts:\n";
foreach ($tables as $t) {
    $cnt = $conn->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    echo "  $t: $cnt rows\n";
}

if ($errors) {
    echo "\nErrors (" . count($errors) . "):\n";
    foreach (array_unique(array_slice($errors, 0, 30)) as $e) {
        echo "  - $e\n";
    }
} else {
    echo "\nNo errors!\n";
}
$conn->close();
echo "</pre>";
