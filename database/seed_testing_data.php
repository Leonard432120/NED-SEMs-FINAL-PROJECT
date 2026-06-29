<?php
/**
 * Database Seeder for Testing the AI Performance Predictor
 * Run this script to populate the database with realistic historical marks,
 * students, and marking assignments across all schools.
 *
 * Usage: Open in browser at http://localhost/NED-SEMs FINAL YEAR PROJECT/database/seed_testing_data.php
 *        or run from CLI: php database/seed_testing_data.php
 */

require_once dirname(__DIR__) . '/config/db.php';

// Set execution limits higher for bulk insert
ini_set('max_execution_time', 300);
set_time_limit(300);

echo "<h3>Starting Database Seeding for AI Predictor...</h3>";

$conn = get_db_connection();

// ─── 1. FETCH ALL SCHOOLS ───────────────────────────────────────────────────
$schools_res = $conn->query("SELECT school_id, school_name, school_type FROM schools WHERE status='active'");
$schools = [];
while ($row = $schools_res->fetch_assoc()) {
    $schools[] = $row;
}
echo "Found " . count($schools) . " active schools.<br>";

// ─── 2. FETCH ALL SUBJECTS ──────────────────────────────────────────────────
$subjects_res = $conn->query("SELECT subject_id, subject_name, category FROM subjects WHERE status='active'");
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) {
    $subjects[] = $row;
}
echo "Found " . count($subjects) . " active subjects.<br>";

// ─── 3. DEFINE EXAMS FOR SEEDING ─────────────────────────────────────────────
// We want to seed data for:
// - Exam 25: MSCE 2025 (historical)
// - Exam 26: JCE 2025 (historical)
// - Exam 27: JCE 2026 (current)
// - Exam 24: MSCE 2026 (current)
$exams_to_seed = [
    25 => ['class' => 'Form 4', 'type' => 'historical'],
    26 => ['class' => 'Form 2', 'type' => 'historical'],
    27 => ['class' => 'Form 2', 'type' => 'current'],
    24 => ['class' => 'Form 4', 'type' => 'current']
];

// ─── 4. SEED STUDENTS FOR EVERY SCHOOL AND CLASS ─────────────────────────────
echo "Seeding students...<br>";
$conn->query("SET FOREIGN_KEY_CHECKS=0");

$students_by_school_class = [];

foreach ($schools as $school) {
    $school_id = (int)$school['school_id'];
    
    foreach (['Form 2', 'Form 4'] as $class) {
        $students_by_school_class[$school_id][$class] = [];
        
        // Count existing active students
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE school_id = ? AND class = ? AND status='active'");
        $stmt->bind_param("is", $school_id, $class);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $students_by_school_class[$school_id][$class][] = (int)$row['student_id'];
        }
        
        // Seed if less than 8 students
        $needed = 8 - count($students_by_school_class[$school_id][$class]);
        if ($needed > 0) {
            $first_names = ['Limbani', 'Tiwonge', 'Chikondi', 'Tinashe', 'Memory', 'Sungeni', 'Chisomo', 'Esnart', 'Mayamiko', 'Wongani', 'Yamiko', 'Kelvin', 'Gift', 'Grace', 'Mercy', 'Peter'];
            $last_names = ['Banda', 'Phiri', 'Mwale', 'Chirwa', 'Gondwe', 'Msiska', 'Lungu', 'Zulu', 'Nkhoma', 'Kumwenda', 'Chilima', 'Tembo', 'Kawonga', 'Hara', 'Nyirenda'];
            
            for ($i = 0; $i < $needed; $i++) {
                $name = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
                $exam_num = "MW" . $school_id . rand(100000, 999999);
                
                $ins_stmt = $conn->prepare("INSERT INTO students (name, school_id, class, exam_number, status) VALUES (?, ?, ?, ?, 'active')");
                $ins_stmt->bind_param("siss", $name, $school_id, $class, $exam_num);
                $ins_stmt->execute();
                $new_id = $conn->insert_id;
                
                $students_by_school_class[$school_id][$class][] = $new_id;
            }
        }
    }
}
echo "Students seeding completed successfully.<br>";

// ─── 5. SEED HISTORICAL MARKS ───────────────────────────────────────────────
echo "Seeding historical marks (Exams 25 & 26)...<br>";
// We will generate realistic scores based on School Type:
// - CDSS: Average around 45%
// - DAY SECONDARY: Average around 52%
// - BOARDING: Average around 65%
// - PRIVATE: Average around 60%
// Subject adjustments:
// - sciences: -5 marks
// - humanities: +4 marks
// - languages: +2 marks

$marks_inserted = 0;

foreach ($schools as $school) {
    $school_id = (int)$school['school_id'];
    $school_type = $school['school_type'] ?: 'CDSS';
    
    // Determine base score
    $base_score = 45;
    if ($school_type === 'BOARDING') $base_score = 65;
    elseif ($school_type === 'PRIVATE') $base_score = 60;
    elseif ($school_type === 'DAY SECONDARY') $base_score = 52;
    
    foreach ($exams_to_seed as $exam_id => $exam_meta) {
        if ($exam_meta['type'] !== 'historical') continue; // only historical exams (25 & 26)
        
        $class = $exam_meta['class'];
        $students_list = $students_by_school_class[$school_id][$class] ?? [];
        
        if (empty($students_list)) continue;
        
        // Let's seed marks for each student in 6-8 random subjects
        foreach ($students_list as $student_id) {
            // Select random subjects
            $subj_keys = array_rand($subjects, min(8, count($subjects)));
            if (!is_array($subj_keys)) {
                $subj_keys = [$subj_keys];
            }
            
            foreach ($subj_keys as $key) {
                $subject = $subjects[$key];
                $subject_id = (int)$subject['subject_id'];
                $category = $subject['category'] ?: 'language';
                
                // Check if mark already exists
                $chk = $conn->prepare("SELECT mark_id FROM marks WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
                $chk->bind_param("iii", $student_id, $exam_id, $subject_id);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    continue;
                }
                
                // Adjust score based on category
                $adj = 0;
                if ($category === 'science') $adj = -5;
                elseif ($category === 'humanities') $adj = 4;
                elseif ($category === 'language') $adj = 2;
                
                // Add noise
                $score = $base_score + $adj + rand(-12, 12);
                $score = max(10, min(99, $score)); // clamp 10-99
                
                $grade = $score >= 80 ? 'D1' : ($score >= 70 ? 'D2' : ($score >= 60 ? 'C4' : ($score >= 50 ? 'C6' : ($score >= 40 ? 'P7' : 'F9'))));
                
                $ins_stmt = $conn->prepare("INSERT INTO marks (student_id, exam_id, subject_id, score, grade, status, submission_status) VALUES (?, ?, ?, ?, ?, 'approved', 'forwarded_to_edm')");
                $ins_stmt->bind_param("iiids", $student_id, $exam_id, $subject_id, $score, $grade);
                $ins_stmt->execute();
                
                $marks_inserted++;
            }
        }
    }
}
echo "Seeded " . $marks_inserted . " historical marks successfully.<br>";

// ─── 6. SEED COMPILED RESULTS TABLE ──────────────────────────────────────────
echo "Compiling and seeding results table...<br>";

// We populate results summary for historical exams (25 & 26)
$results_inserted = 0;

$marks_summary_res = $conn->query("
    SELECT student_id, exam_id, COUNT(*) AS total_sub, SUM(score) AS total_sc, AVG(score) AS avg_sc
    FROM marks
    WHERE exam_id IN (25, 26)
    GROUP BY student_id, exam_id
");

while ($row = $marks_summary_res->fetch_assoc()) {
    $student_id = (int)$row['student_id'];
    $exam_id = (int)$row['exam_id'];
    $total_subjects = (int)$row['total_sub'];
    $total_score = (float)$row['total_sc'];
    $average_score = (float)$row['avg_sc'];
    
    // Check if result already exists
    $chk = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND exam_id = ?");
    $chk->bind_param("ii", $student_id, $exam_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        continue;
    }
    
    // Fetch class
    $stud = $conn->query("SELECT class FROM students WHERE student_id = $student_id")->fetch_assoc();
    $class = $stud['class'] ?? 'Form 2';
    
    $grade = $average_score >= 70 ? 'D1' : ($average_score >= 55 ? 'C4' : ($average_score >= 40 ? 'P7' : 'F9'));
    
    $ins = $conn->prepare("INSERT INTO results (student_id, exam_id, term, year, class, total_subjects, total_score, average_score, grade, status, locked) VALUES (?, ?, 'Term 3', 2025, ?, ?, ?, ?, ?, 'published', 1)");
    $ins->bind_param("iisidds", $student_id, $exam_id, $class, $total_subjects, $total_score, $average_score, $grade);
    $ins->execute();
    $results_inserted++;
}
echo "Seeded " . $results_inserted . " compiled results summaries.<br>";

// ─── 7. SEED MARKING ASSIGNMENTS ─────────────────────────────────────────────
echo "Seeding marking assignments for active and historical exams...<br>";
// We assign active teachers to some subjects
$teachers_res = $conn->query("SELECT user_id, school_id FROM users WHERE role='teacher' AND status='active'");
$teachers = [];
while ($t = $teachers_res->fetch_assoc()) {
    $teachers[] = $t;
}

$ma_inserted = 0;
if (!empty($teachers)) {
    foreach ($schools as $school) {
        $school_id = (int)$school['school_id'];
        
        // Find teachers at this school, if none, use any teacher
        $school_teachers = array_filter($teachers, fn($t) => (int)$t['school_id'] === $school_id);
        if (empty($school_teachers)) {
            $school_teachers = $teachers;
        }
        
        foreach ([24, 25, 26, 27] as $ex_id) {
            foreach ($subjects as $subj) {
                $subject_id = (int)$subj['subject_id'];
                
                // Check uniqueness
                $chk = $conn->prepare("SELECT assignment_id FROM marking_assignments WHERE exam_id = ? AND subject_id = ? AND school_id = ?");
                $chk->bind_param("iii", $ex_id, $subject_id, $school_id);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    continue;
                }
                
                // Select a random teacher
                $teacher = $school_teachers[array_rand($school_teachers)];
                $teacher_id = (int)$teacher['user_id'];
                
                // Status can be completed for old exams, and assigned/submitted for active ones
                $status = ($ex_id === 25 || $ex_id === 26) ? 'completed' : (rand(0, 1) ? 'assigned' : 'submitted');
                
                $ins = $conn->prepare("INSERT INTO marking_assignments (school_id, exam_id, subject_id, teacher_id, assigned_by, status) VALUES (?, ?, ?, ?, 2, ?)");
                $ins->bind_param("iiiis", $school_id, $ex_id, $subject_id, $teacher_id, $status);
                $ins->execute();
                $ma_inserted++;
            }
        }
    }
}
echo "Seeded " . $ma_inserted . " marking assignments.<br>";

$conn->query("SET FOREIGN_KEY_CHECKS=1");
echo "<h3>✓ Seeding Completed Successfully!</h3>";
echo "Your AI predictor now has a rich database of student performance data to generate accurate, varied, and beautiful predictions.<br>";
echo "<a href='../admin/predictions.php'>Go to AI Predictor Dashboard</a>";
$conn->close();
?>
