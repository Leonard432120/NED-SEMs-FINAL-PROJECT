<?php
/**
 * NED-SEMS Full Database Seeder
 * Inserts realistic comprehensive data across all tables
 * Run: http://localhost/NED-SEMs FINAL YEAR PROJECT/database/full_seed.php
 */

require_once dirname(__DIR__) . '/config/db.php';
ini_set('max_execution_time', 600);
set_time_limit(600);

$conn = get_db_connection();
$conn->query("SET FOREIGN_KEY_CHECKS=0");
$conn->query("SET SQL_MODE=''");

$errors = [];
$inserts = 0;

function exec_sql($conn, $sql, &$inserts, &$errors) {
    if ($conn->query($sql)) {
        $inserts++;
    } else {
        $errors[] = $conn->error . " | SQL: " . substr($sql, 0, 120);
    }
}

echo "<pre style='font-family:monospace;font-size:13px;'>";
echo "=== NED-SEMS FULL DATABASE SEEDER ===\n\n";

// ════════════════════════════════════════════════════════════
// 1. FIX SCHOOL METADATA
// ════════════════════════════════════════════════════════════
echo "1. Fixing school metadata...\n";

$school_fixes = [
    [29, 'KARONGA COMMUNITY SECONDARY SCHOOL', 'DAY SECONDARY', 'North Karonga', 'KAR001', '0885001001', 'kcsss@edu.gov.mw', 'Mr. James Mwale'],
    [32, 'Maghemo Secondary School',           'CDSS',          'South Karonga', 'MAG001', '0885002002', 'maghemo@edu.gov.mw', 'Mrs. Grace Phiri'],
    [33, 'Maghemo Secondary School B',         'CDSS',          'South Karonga', 'MAG002', '0885002003', 'maghemo2@edu.gov.mw','Mr. Peter Banda'],
    [34, 'Mlare Secondary School',             'DAY SECONDARY', 'Central Karonga','MLA001','0885003003', 'mlare@edu.gov.mw',  'Mrs. Sarah Chirwa'],
    [35, 'Karonga Girls Secondary School',     'BOARDING',      'North Karonga', 'KGS001', '0885004004', 'kgss@edu.gov.mw',  'Ms. Alice Tembo'],
];

foreach ($school_fixes as $s) {
    $sql = "UPDATE schools SET 
        school_type='{$s[2]}', cluster_name='{$s[3]}', school_number='{$s[4]}',
        phone='{$s[5]}', email='{$s[6]}', headteacher_name='{$s[7]}'
        WHERE school_id={$s[0]}";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Schools updated.\n";

// ════════════════════════════════════════════════════════════
// 2. FIX SUBJECT CATEGORIES
// ════════════════════════════════════════════════════════════
echo "2. Fixing subject categories...\n";
exec_sql($conn, "UPDATE subjects SET category='humanities' WHERE subject_id=113", $inserts, $errors); // Technical Drawing
exec_sql($conn, "UPDATE subjects SET category='science'    WHERE subject_id=115", $inserts, $errors); // Home Economics
exec_sql($conn, "UPDATE subjects SET category='humanities' WHERE subject_id=116", $inserts, $errors); // Metalwork
echo "   Subject categories fixed.\n";

// ════════════════════════════════════════════════════════════
// 3. GET REFERENCE DATA
// ════════════════════════════════════════════════════════════
$schools_res = $conn->query("SELECT school_id, school_name, school_type FROM schools WHERE status='active'");
$schools = [];
while ($r = $schools_res->fetch_assoc()) $schools[] = $r;

$subjects_res = $conn->query("SELECT subject_id, subject_name, category FROM subjects WHERE status='active'");
$subjects = [];
while ($r = $subjects_res->fetch_assoc()) $subjects[] = $r;

$subject_ids = array_column($subjects, 'subject_id');

// Exams
$exams_info = [
    25 => ['class' => 'Form 4', 'label' => 'MSCE 2025'],
    26 => ['class' => 'Form 2', 'label' => 'JCE 2025'],
    27 => ['class' => 'Form 2', 'label' => 'JCE 2026'],
    24 => ['class' => 'Form 4', 'label' => 'MSCE 2026'],
];

// Subjects linked to each exam via exam_subjects
$exam_subjects_map = [];
$res = $conn->query("SELECT exam_id, subject_id, id as es_id FROM exam_subjects");
while ($r = $res->fetch_assoc()) {
    $exam_subjects_map[$r['exam_id']][] = ['subject_id' => $r['subject_id'], 'es_id' => $r['es_id']];
}

// Students per school per class
$students_map = []; // [school_id][class] => [student_ids]
$res = $conn->query("SELECT student_id, school_id, class FROM students WHERE status='active'");
while ($r = $res->fetch_assoc()) {
    $students_map[$r['school_id']][$r['class']][] = $r['student_id'];
}

// Teachers per school
$teachers_map = []; // [school_id] => [user_ids]
$res = $conn->query("SELECT user_id, school_id FROM users WHERE role='teacher' AND status='active'");
while ($r = $res->fetch_assoc()) {
    if ($r['school_id']) $teachers_map[$r['school_id']][] = $r['user_id'];
}

// ════════════════════════════════════════════════════════════
// 4. SEED ADDITIONAL STUDENTS (ensure each school has 15+ per class)
// ════════════════════════════════════════════════════════════
echo "3. Seeding students...\n";

$first_names = ['Limbani','Tiwonge','Chikondi','Tinashe','Memory','Sungeni','Chisomo','Esnart',
                'Mayamiko','Wongani','Yamiko','Kelvin','Gift','Grace','Mercy','Peter','John',
                'Alice','Rose','Patrick','Francis','Dorothy','Agnes','Charles','Emmanuel'];
$last_names  = ['Banda','Phiri','Mwale','Chirwa','Gondwe','Msiska','Lungu','Zulu','Nkhoma',
                'Kumwenda','Chilima','Tembo','Kawonga','Hara','Nyirenda','Mvula','Mkandawire',
                'Mbewe','Kamanga','Kalowekamo'];

$student_counter = 127; // next available
foreach ($schools as $school) {
    $sid = $school['school_id'];
    foreach (['Form 2','Form 4'] as $cls) {
        $existing = isset($students_map[$sid][$cls]) ? count($students_map[$sid][$cls]) : 0;
        $needed   = max(0, 15 - $existing);
        $gender_cycle = ['Male','Female','Male','Female','Male'];
        for ($i = 0; $i < $needed; $i++) {
            $nm  = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
            $en  = "MW{$sid}" . str_pad($student_counter, 6, '0', STR_PAD_LEFT);
            $gen = $gender_cycle[$i % 5];
            $sql = "INSERT IGNORE INTO students (student_id, name, school_id, class, exam_number, status, gender, special_needs)
                    VALUES ($student_counter, '$nm', $sid, '$cls', '$en', 'active', '$gen', 'None')";
            if ($conn->query($sql)) {
                $students_map[$sid][$cls][] = $student_counter;
                $inserts++;
            }
            $student_counter++;
        }
    }
}
echo "   Students seeded.\n";

// ════════════════════════════════════════════════════════════
// 5. ENSURE EXAM_SUBJECTS FOR ALL EXAMS
// ════════════════════════════════════════════════════════════
echo "4. Ensuring exam_subjects links...\n";

$form2_subjects = [104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,121];
$form4_subjects = [104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,121];

$exam_class = [24=>'Form 4', 25=>'Form 4', 26=>'Form 2', 27=>'Form 2'];
foreach ($exam_class as $eid => $cls) {
    $subs = ($cls === 'Form 2') ? $form2_subjects : $form4_subjects;
    // Use 58 candidates for Form 2, 55 for Form 4
    $reg = ($cls === 'Form 2') ? 58 : 55;
    foreach ($subs as $subid) {
        $sql = "INSERT IGNORE INTO exam_subjects (subject_id, exam_id, class, status, duration_minutes, total_marks, registered_candidates)
                VALUES ($subid, $eid, '$cls', 'active', 120, 100, $reg)";
        exec_sql($conn, $sql, $inserts, $errors);
    }
}

// Reload exam_subjects map
$exam_subjects_map = [];
$res = $conn->query("SELECT exam_id, subject_id, id as es_id FROM exam_subjects");
while ($r = $res->fetch_assoc()) {
    $exam_subjects_map[$r['exam_id']][] = ['subject_id' => $r['subject_id'], 'es_id' => $r['es_id']];
}
echo "   exam_subjects ready.\n";

// ════════════════════════════════════════════════════════════
// 6. ASSIGN EXTRA TEACHERS TO SCHOOLS THAT NEED THEM
// ════════════════════════════════════════════════════════════
echo "5. Ensuring teacher coverage...\n";

// School 29 → teacher 120 (examination_officer but we'll add some teachers)
// We'll assign teacher 119 (Alice Banda) to school 29
exec_sql($conn, "UPDATE users SET school_id=29 WHERE user_id=119", $inserts, $errors);
exec_sql($conn, "UPDATE users SET school_id=32 WHERE user_id=121", $inserts, $errors);

// Reload teachers
$teachers_map = [];
$res = $conn->query("SELECT user_id, school_id FROM users WHERE role IN ('teacher','examination_officer') AND status='active'");
while ($r = $res->fetch_assoc()) {
    if ($r['school_id']) $teachers_map[$r['school_id']][] = $r['user_id'];
}
// Fallback teacher (use user 2 admin as fallback if school has no teachers)
$default_teacher = 127;

echo "   Teachers mapped.\n";

// ════════════════════════════════════════════════════════════
// 7. SEED MARKING ASSIGNMENTS
// ════════════════════════════════════════════════════════════
echo "6. Seeding marking assignments...\n";

$deadlines = [25=>'2026-05-15', 26=>'2026-05-20', 27=>'2026-07-20', 24=>'2026-11-01'];

foreach ($exams_info as $eid => $einfo) {
    $cls     = $einfo['class'];
    $deadline= $deadlines[$eid];

    if (!isset($exam_subjects_map[$eid])) continue;

    foreach ($schools as $school) {
        $scd  = $school['school_id'];
        $t_pool = $teachers_map[$scd] ?? [$default_teacher];
        $t_idx  = 0;

        foreach ($exam_subjects_map[$eid] as $es) {
            $subid = $es['subject_id'];
            $tid   = $t_pool[$t_idx % count($t_pool)];
            $t_idx++;

            // Statuses: exams 25,26 = completed; exam 27 = assigned; exam 24 = assigned
            $status = ($eid <= 26) ? 'completed' : 'assigned';

            $sql = "INSERT IGNORE INTO marking_assignments 
                    (school_id, exam_id, subject_id, teacher_id, deadline, override_lock, unlock_requested, assigned_by, assigned_at, status)
                    VALUES ($scd, $eid, $subid, $tid, '$deadline', 0, 0, $default_teacher, NOW(), '$status')";
            exec_sql($conn, $sql, $inserts, $errors);
        }
    }
}
echo "   Marking assignments seeded.\n";

// ════════════════════════════════════════════════════════════
// 8. SEED MARKS — THE MAIN EVENT
// ════════════════════════════════════════════════════════════
echo "7. Seeding marks (this is the big one)...\n";

// Score profiles by school type and subject category
$school_type_base = [
    'CDSS'          => 48,
    'DAY SECONDARY' => 56,
    'BOARDING'      => 65,
    'PRIVATE'       => 70,
];
$category_mod = [
    'science'    => -3,
    'language'   => +2,
    'humanities' => +1,
];

function rand_score($base, $std = 12) {
    // Box-Muller normal distribution
    $u1 = mt_rand(1, 1000000) / 1000000;
    $u2 = mt_rand(1, 1000000) / 1000000;
    $z  = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    $score = $base + $z * $std;
    return max(5, min(100, round($score, 1)));
}

function score_to_grade($s) {
    if ($s >= 80) return 'D1';
    if ($s >= 70) return 'D2';
    if ($s >= 65) return 'D3';
    if ($s >= 60) return 'C4';
    if ($s >= 55) return 'C5';
    if ($s >= 50) return 'C6';
    if ($s >= 40) return 'P7';
    if ($s >= 35) return 'P8';
    return 'F9';
}

// School type lookup
$school_type_map = [];
foreach ($schools as $s) {
    $school_type_map[$s['school_id']] = $s['school_type'] ?? 'CDSS';
}
// Subject category lookup
$subject_cat_map = [];
foreach ($subjects as $s) {
    $subject_cat_map[$s['subject_id']] = $s['category'] ?? 'science';
}

$mark_id = $conn->query("SELECT MAX(mark_id) as m FROM marks")->fetch_assoc()['m'] + 1;

$exam_status_map = [
    25 => ['mark_status' => 'approved', 'sub_status' => 'received',       'submitted_at' => '2026-05-16'],
    26 => ['mark_status' => 'approved', 'sub_status' => 'received',       'submitted_at' => '2026-05-21'],
    27 => ['mark_status' => 'submitted','sub_status' => 'submitted',      'submitted_at' => '2026-07-20'],
    24 => ['mark_status' => 'draft',    'sub_status' => 'submitted',      'submitted_at' => null],
];

$total_marks_inserted = 0;

foreach ($exams_info as $eid => $einfo) {
    $cls      = $einfo['class'];
    $estatuses= $exam_status_map[$eid];
    $m_status = $estatuses['mark_status'];
    $s_status = $estatuses['sub_status'];
    $sub_at   = $estatuses['submitted_at'] ? "'{$estatuses['submitted_at']} 14:00:00'" : 'NULL';

    if (!isset($exam_subjects_map[$eid])) continue;
    $exam_subject_list = $exam_subjects_map[$eid];

    foreach ($schools as $school) {
        $scd       = $school['school_id'];
        $stype     = $school_type_map[$scd] ?? 'CDSS';
        $base_score= $school_type_base[$stype] ?? 50;
        $t_pool    = $teachers_map[$scd] ?? [$default_teacher];
        $t_idx     = 0;

        if (!isset($students_map[$scd][$cls])) continue;
        $student_ids = $students_map[$scd][$cls];

        foreach ($exam_subject_list as $es) {
            $subid    = $es['subject_id'];
            $cat      = $subject_cat_map[$subid] ?? 'science';
            $cat_mod  = $category_mod[$cat] ?? 0;
            $subject_base = $base_score + $cat_mod;

            $tid = $t_pool[$t_idx % count($t_pool)];
            $t_idx++;

            // For exam 27, only insert marks for 70% of students to simulate partial completion
            $student_sample = ($eid === 27) ? array_slice($student_ids, 0, max(1, (int)(count($student_ids) * 0.70))) : $student_ids;
            // For exam 24, only 30% (early/partial)
            if ($eid === 24) $student_sample = array_slice($student_ids, 0, max(1, (int)(count($student_ids) * 0.30)));

            foreach ($student_sample as $stid) {
                $score = rand_score($subject_base, 14);
                $grade = score_to_grade($score);
                $created= date('Y-m-d H:i:s', strtotime('-' . rand(1,90) . ' days'));

                $sql = "INSERT IGNORE INTO marks 
                        (student_id, exam_id, subject_id, teacher_id, score, grade, status, 
                         submitted_at, created_at, submission_status, submitted_by, received_by, received_at)
                        VALUES 
                        ($stid, $eid, $subid, $tid, $score, '$grade', '$m_status',
                         $sub_at, '$created', '$s_status', $tid,
                         " . ($m_status === 'approved' ? $default_teacher : 'NULL') . ",
                         " . ($m_status === 'approved' ? "'$estatuses[submitted_at] 16:00:00'" : 'NULL') . ")";
                exec_sql($conn, $sql, $inserts, $errors);
                $total_marks_inserted++;
            }
        }
    }
    echo "   Exam $eid ({$einfo['label']}): marks inserted for all schools.\n";
}

echo "   Total marks: ~$total_marks_inserted\n";

// ════════════════════════════════════════════════════════════
// 9. COMPILE RESULTS (aggregate from marks)
// ════════════════════════════════════════════════════════════
echo "8. Compiling results...\n";

// Delete old sparse results for exams we're seeding
$conn->query("DELETE FROM results WHERE exam_id IN (24,25,26,27)");

// Aggregate marks → results
$agg_sql = "
    SELECT 
        m.student_id,
        m.exam_id,
        s.class,
        COUNT(DISTINCT m.subject_id) AS total_subjects,
        SUM(m.score) AS total_score,
        AVG(m.score) AS average_score
    FROM marks m
    JOIN students s ON s.student_id = m.student_id
    WHERE m.exam_id IN (24,25,26,27)
    GROUP BY m.student_id, m.exam_id, s.class
";
$agg_res = $conn->query($agg_sql);

$result_rows = [];
while ($r = $agg_res->fetch_assoc()) {
    $result_rows[] = $r;
}

// Rank within exam+class+school
// group by exam_id + class + school
$groups = [];
foreach ($result_rows as &$r) {
    $key = $r['exam_id'] . '_' . $r['class'];
    $groups[$key][] = &$r;
}
unset($r);

foreach ($groups as $key => &$grp) {
    usort($grp, fn($a,$b) => $b['average_score'] <=> $a['average_score']);
    foreach ($grp as $pos => &$rr) {
        $rr['position'] = $pos + 1;
    }
}
unset($grp, $rr);

$exam_terms = [24=>'Term 1', 25=>'Term 1', 26=>'Term 1', 27=>'Term 1'];
$exam_years = [24=>'2026', 25=>'2025', 26=>'2025', 27=>'2026'];
$exam_status_res = [24=>'draft', 25=>'published', 26=>'published', 27=>'submitted'];

$result_id = $conn->query("SELECT MAX(result_id) as m FROM results")->fetch_assoc()['m'] + 1;

foreach ($result_rows as $r) {
    $eid  = $r['exam_id'];
    $stid = $r['student_id'];
    $cls  = $r['class'];
    $tot  = round((float)$r['total_score'], 2);
    $avg  = round((float)$r['average_score'], 2);
    $nsub = (int)$r['total_subjects'];
    $pos  = $r['position'];
    $grade = score_to_grade($avg);
    $term  = $exam_terms[$eid];
    $year  = $exam_years[$eid];
    $stat  = $exam_status_res[$eid];
    $locked= ($stat === 'published') ? 1 : 0;

    $sql = "INSERT IGNORE INTO results
            (student_id, exam_id, term, year, class, total_subjects, total_score, average_score,
             grade, position_in_class, compiled_by, compiled_at, status, locked)
            VALUES 
            ($stid, $eid, '$term', '$year', '$cls', $nsub, $tot, $avg,
             '$grade', $pos, 2, NOW(), '$stat', $locked)";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Results compiled: " . count($result_rows) . " records.\n";

// ════════════════════════════════════════════════════════════
// 10. SEED TEACHER PERFORMANCE HISTORY
// ════════════════════════════════════════════════════════════
echo "9. Seeding teacher_performance_history...\n";

// Compute per teacher per exam: avg score given, marks submitted, on_time
$tph_sql = "
    SELECT 
        m.teacher_id,
        m.exam_id,
        s.school_id,
        COUNT(*) as marks_count,
        AVG(m.score) as avg_score,
        SUM(CASE WHEN m.status='submitted' OR m.status='approved' THEN 1 ELSE 0 END) as submitted_count
    FROM marks m
    JOIN students s ON s.student_id = m.student_id
    WHERE m.exam_id IN (25,26,27,24)
    GROUP BY m.teacher_id, m.exam_id, s.school_id
";
$tph_res = $conn->query($tph_sql);

while ($tr = $tph_res->fetch_assoc()) {
    $tid   = $tr['teacher_id'];
    $eid   = $tr['exam_id'];
    $sid   = $tr['school_id'];
    $cnt   = $tr['marks_count'];
    $avg   = round((float)$tr['avg_score'], 2);
    $subm  = $tr['submitted_count'];
    $ontime= ($eid <= 26) ? 1 : (rand(0,1));  // historical = on time; current = random

    // Check if table has these columns; use a safe insert
    $sql = "INSERT IGNORE INTO teacher_performance_history 
            (teacher_id, school_id, exam_id, marks_entered, avg_score_given, submitted_on_time, recorded_at)
            VALUES ($tid, $sid, $eid, $cnt, $avg, $ontime, NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Teacher performance history seeded.\n";

// ════════════════════════════════════════════════════════════
// 11. SEED HT FINDINGS FOR MULTIPLE EXAMS
// ════════════════════════════════════════════════════════════
echo "10. Seeding HT findings...\n";

$ht_findings_data = [
    // [school_id, exam_id, trend, pass_rate, cause, cause_detail, action, action_detail, lifecycle]
    [39, 26, 'declining', 38.50, 'teacher_absenteeism', 'Two teachers on sick leave during term.', 'remedial_classes', 'Saturday extra lessons arranged.', 'closed'],
    [39, 24, 'flat',       52.00, 'resource_shortage',   'Shortage of textbooks in Form 4.', 'resource_procurement', 'Requested 60 books from DEO.', 'open'],
    [38, 25, 'improving',  61.00, 'positive_intervention','School feeding programme improved attendance.', 'celebration_recognition', 'Awarded best performing school cluster.', 'closed'],
    [38, 26, 'flat',       55.00, 'curriculum_gap',       'Students weak in algebra topics.', 'peer_mentoring', 'Peer tutoring sessions twice a week.', 'open'],
    [35, 25, 'improving',  72.00, 'positive_intervention','Additional Science lab opened.', 'celebration_recognition', 'Recognised by district.', 'closed'],
    [35, 26, 'declining',  44.00, 'student_discipline',   'Disruptions during revision period.', 'pastoral_support', 'Counselling sessions initiated.', 'open'],
    [32, 26, 'declining',  36.00, 'low_attendance',       'High absenteeism rate in Form 2.', 'attendance_campaign', 'Parent meetings held monthly.', 'open'],
    [29, 25, 'flat',       49.00, 'assessment_irregularity','Marking inconsistencies noted.', 'curriculum_revision', 'Standardised marking scheme shared.', 'closed'],
];

foreach ($ht_findings_data as $f) {
    $sql = "INSERT IGNORE INTO ht_findings 
            (school_id, exam_id, recorded_by, trend, pass_rate_pct, cause_category, cause_detail, action_category, action_detail, lifecycle_status, created_at)
            VALUES ({$f[0]}, {$f[1]}, 127, '{$f[2]}', {$f[3]}, '{$f[4]}', '{$f[5]}', '{$f[6]}', '{$f[7]}', '{$f[8]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   HT findings seeded.\n";

// ════════════════════════════════════════════════════════════
// 12. SEED HT FINDING OUTCOMES
// ════════════════════════════════════════════════════════════
echo "11. Seeding HT finding outcomes...\n";
// Get finding_ids for closed ones
$fi_res = $conn->query("SELECT finding_id, school_id FROM ht_findings WHERE lifecycle_status='closed'");
while ($fi = $fi_res->fetch_assoc()) {
    $fid = $fi['finding_id'];
    $fsid= $fi['school_id'];
    $outcomes = ['improved','improved','no_change'];
    $oc = $outcomes[array_rand($outcomes)];
    $sql = "INSERT IGNORE INTO ht_finding_outcomes 
            (finding_id, school_id, exam_id, recorded_by, outcome, outcome_detail, created_at)
            VALUES ($fid, $fsid, 27, 127, '$oc', 'Reviewed at end of term.', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   HT finding outcomes seeded.\n";

// ════════════════════════════════════════════════════════════
// 13. SEED DIVISIONAL FINDINGS
// ════════════════════════════════════════════════════════════
echo "12. Seeding divisional findings...\n";

$div_data = [
    [25, 'flat',      45.50, 'teacher_shortage',            'Not enough qualified science teachers.', 'teacher_recruitment',    'Posted 12 teachers from Mzuzu.', 'closed'],
    [26, 'declining', 40.20, 'learning_material_deficiency','Books not delivered before exams.', 'textbook_distribution',  'Distributed 500 books to schools.', 'closed'],
    [24, 'flat',      55.00, 'curriculum_misalignment',     'Syllabus changed mid-year.', 'teacher_capacity_building','CPD sessions run in July 2026.', 'open'],
];

foreach ($div_data as $d) {
    $sql = "INSERT IGNORE INTO div_findings 
            (exam_id, recorded_by, trend, avg_score_pct, cause_category, cause_detail, action_category, action_detail, lifecycle_status, created_at)
            VALUES ({$d[0]}, 2, '{$d[1]}', {$d[2]}, '{$d[3]}', '{$d[4]}', '{$d[5]}', '{$d[6]}', '{$d[7]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}

// Outcomes for closed
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
echo "13. Seeding announcements...\n";
$announcements = [
    [127, 39, 'JCE 2026 Marks Deadline Reminder', 'All teachers must submit marks for JCE 2026 by 20th July 2026. Late submissions will be flagged.'],
    [2,   null,'System Maintenance Notice',         'NED-SEMS will undergo scheduled maintenance on 25th July 2026 from 22:00 to 02:00.'],
    [127, 39, 'Form 2 Result Review Meeting',       'Head of Department meeting to review Form 2 performance scheduled for 28th July 2026.'],
    [2,   null,'New Grading Rubric Published',      'Updated grading rubric for JCE and MSCE examinations is now available in the documents section.'],
];
foreach ($announcements as $a) {
    $school_val = $a[1] ? $a[1] : 'NULL';
    $sql = "INSERT INTO announcements (title, content, published_by, school_id, created_at)
            VALUES ('{$a[2]}', '{$a[3]}', {$a[0]}, $school_val, NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Announcements seeded.\n";

// ════════════════════════════════════════════════════════════
// 15. SEED SCHOOL REPORTS
// ════════════════════════════════════════════════════════════
echo "14. Seeding school_reports...\n";

$sr_data = [
    [39, 'Term 1', 2025, 58, 78.5, 52.0, 'Good term overall. Science subjects need improvement.'],
    [39, 'Term 2', 2025, 58, 82.0, 60.5, 'Improved attendance and pass rate compared to Term 1.'],
    [39, 'Term 1', 2026, 58, 75.0, 48.0, 'Early term, results pending full compilation.'],
    [38, 'Term 1', 2025, 55, 80.0, 65.0, 'Strong performance across all departments.'],
    [38, 'Term 2', 2025, 55, 85.0, 70.0, 'Best term on record for the school.'],
    [35, 'Term 1', 2025, 50, 88.0, 72.5, 'Boarding school advantage reflected in results.'],
    [35, 'Term 2', 2025, 50, 90.0, 75.0, 'Science stream outperformed humanities.'],
    [29, 'Term 1', 2025, 60, 72.0, 50.0, 'Average performance; remedial sessions needed.'],
];
foreach ($sr_data as $sr) {
    $sql = "INSERT INTO school_reports (school_id, term, year, total_students, attendance_rate, pass_rate, comments, created_at)
            VALUES ({$sr[0]}, '{$sr[1]}', {$sr[2]}, {$sr[3]}, {$sr[4]}, {$sr[5]}, '{$sr[6]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   School reports seeded.\n";

// ════════════════════════════════════════════════════════════
// 16. UPDATE EXAM MARKS DEADLINES & DATES
// ════════════════════════════════════════════════════════════
echo "15. Updating exam deadlines...\n";
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-05-15 00:00:00', start_date='2026-05-01', end_date='2026-05-14' WHERE exam_id=25", $inserts, $errors);
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-05-20 00:00:00', start_date='2026-05-01', end_date='2026-05-18' WHERE exam_id=26", $inserts, $errors);
exec_sql($conn, "UPDATE exams SET marks_deadline='2026-11-01 00:00:00', start_date='2026-10-05', end_date='2026-11-20' WHERE exam_id=24", $inserts, $errors);
echo "   Exam dates updated.\n";

// ════════════════════════════════════════════════════════════
// 17. SEED SUBJECT_ASSIGNMENTS (more teacher-subject links)
// ════════════════════════════════════════════════════════════
echo "16. Seeding subject_assignments...\n";

$sa_data = [
    // [subject_id, teacher_id, category, role]
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
    [104, 125, 'science',    'moderator'],
    [107, 124, 'science',    'moderator'],
    [109, 127, 'language',   'moderator'],
];
foreach ($sa_data as $sa) {
    $sql = "INSERT IGNORE INTO subject_assignments (subject_id, teacher_id, teacher_category, role, assigned_by, assigned_at, email_sent, status)
            VALUES ({$sa[0]}, {$sa[1]}, '{$sa[2]}', '{$sa[3]}', 2, NOW(), 1, 'assigned')";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Subject assignments seeded.\n";

// ════════════════════════════════════════════════════════════
// 18. SEED TEACHER PERFORMANCE REVIEWS
// ════════════════════════════════════════════════════════════
echo "17. Seeding teacher performance reviews...\n";

$teachers_school39 = [124, 125, 126, 129, 130];
$review_notes = [
    'Consistently submits marks on time. Strong question quality.',
    'Good classroom performance but marks submitted slightly late.',
    'Excellent collaboration with examination officer.',
    'Needs improvement in meeting deadlines.',
    'Strong subject knowledge, high quality exam questions.',
];
foreach ($teachers_school39 as $idx => $tid) {
    $rating = rand(3, 5);
    $note = $review_notes[$idx % count($review_notes)];
    $sql = "INSERT IGNORE INTO teacher_performance_reviews (teacher_id, reviewed_by, school_id, rating, notes, review_date)
            VALUES ($tid, 127, 39, $rating, '$note', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Teacher performance reviews seeded.\n";

// ════════════════════════════════════════════════════════════
// 19. SEED RESULT WORKFLOW LOGS
// ════════════════════════════════════════════════════════════
echo "18. Seeding result_workflow_logs...\n";
$rwl_data = [
    [0, 'compile_results', 2, 'ADMIN', null, 'submitted', 'Compiled exam ID 25: bulk compilation'],
    [0, 'compile_results', 2, 'ADMIN', null, 'submitted', 'Compiled exam ID 26: bulk compilation'],
    [0, 'publish_results', 2, 'ADMIN', 'submitted', 'published', 'Published results for exam ID 25'],
    [0, 'publish_results', 2, 'ADMIN', 'submitted', 'published', 'Published results for exam ID 26'],
    [0, 'compile_results', 2, 'ADMIN', null, 'draft', 'Compiled exam ID 27: partial'],
];
foreach ($rwl_data as $rwl) {
    $from = $rwl[4] ? "'{$rwl[4]}'" : 'NULL';
    $sql = "INSERT INTO result_workflow_logs (result_id, action, performed_by, role, from_status, to_status, notes, created_at)
            VALUES ({$rwl[0]}, '{$rwl[1]}', {$rwl[2]}, '{$rwl[3]}', $from, '{$rwl[5]}', '{$rwl[6]}', NOW())";
    exec_sql($conn, $sql, $inserts, $errors);
}
echo "   Result workflow logs seeded.\n";

// ════════════════════════════════════════════════════════════
// DONE — SUMMARY
// ════════════════════════════════════════════════════════════
$conn->query("SET FOREIGN_KEY_CHECKS=1");
$conn->close();

echo "\n========================================\n";
echo "SEEDING COMPLETE\n";
echo "========================================\n";
echo "Total SQL operations: $inserts\n";

// Final counts
$conn2 = get_db_connection();
$tables = ['marks','results','students','marking_assignments','ht_findings','teacher_performance_history','school_reports'];
echo "\nFinal row counts:\n";
foreach ($tables as $t) {
    $cnt = $conn2->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    echo "  $t: $cnt\n";
}
$conn2->close();

if ($errors) {
    echo "\nErrors (" . count($errors) . "):\n";
    foreach (array_unique(array_slice($errors, 0, 20)) as $e) {
        echo "  - $e\n";
    }
} else {
    echo "\nNo errors!\n";
}
echo "</pre>";
