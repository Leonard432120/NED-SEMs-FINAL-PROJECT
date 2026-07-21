<?php
/**
 * common/teacher_performance_helper.php
 * ─────────────────────────────────────────────────────────────────
 * Unified Teacher Performance Management calculation engine.
 * Computes scores across 6 dimensions, maps promotion recommendations,
 * generates dynamic strengths/weaknesses/insights, and handles snapshots.
 * ─────────────────────────────────────────────────────────────────
 */

if (!function_exists('get_db_connection')) {
    require_once __DIR__ . '/../config/db.php';
}

/**
 * Calculates metrics and overall score for a specific teacher.
 * 
 * @param int $teacher_id
 * @param int $school_id
 * @param array $weights Association of component weights (prep, quality, mod, marking, time, part)
 * @param mysqli $conn
 * @return array Detailed metrics and scores
 */
function calculate_teacher_metrics($teacher_id, $school_id, array $weights, $conn) {
    $teacher_id = (int)$teacher_id;
    $school_id  = (int)$school_id;

    // Standard Default Weights if not provided or incorrect
    $w_prep    = isset($weights['prep'])    ? (float)$weights['prep']    : 20.0;
    $w_quality = isset($weights['quality']) ? (float)$weights['quality'] : 20.0;
    $w_mod     = isset($weights['mod'])     ? (float)$weights['mod']     : 15.0;
    $w_marking = isset($weights['marking']) ? (float)$weights['marking'] : 20.0;
    $w_time    = isset($weights['time'])    ? (float)$weights['time']    : 15.0;
    $w_part    = isset($weights['part'])    ? (float)$weights['part']    : 10.0;

    $total_weight = $w_prep + $w_quality + $w_mod + $w_marking + $w_time + $w_part;
    if ($total_weight <= 0) {
        $w_prep = 20.0; $w_quality = 20.0; $w_mod = 15.0; $w_marking = 20.0; $w_time = 15.0; $w_part = 10.0;
        $total_weight = 100.0;
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. EXAM PREPARATION (20%)
    // Number of questions authored. Target is 5 questions per exam cycle.
    // ─────────────────────────────────────────────────────────────────
    $prep_stmt = $conn->prepare("
        SELECT COUNT(question_id) AS total_written, COUNT(DISTINCT exam_id) AS distinct_exams
        FROM questions
        WHERE created_by = ?
    ");
    $prep_stmt->bind_param("i", $teacher_id);
    $prep_stmt->execute();
    $prep_data = $prep_stmt->get_result()->fetch_assoc();
    $prep_stmt->close();

    $questions_written = (int)($prep_data['total_written'] ?? 0);
    $distinct_exams    = (int)($prep_data['distinct_exams'] ?? 0);
    // Score calculation: min(100, (questions / 5) * 100)
    $prep_score = $questions_written > 0 ? min(100.0, ($questions_written / 5.0) * 100.0) : 0.0;


    // ─────────────────────────────────────────────────────────────────
    // 2. QUESTION QUALITY (20%)
    // Average AI quality score + moderator approval rate
    // ─────────────────────────────────────────────────────────────────
    $qual_stmt = $conn->prepare("
        SELECT 
            AVG(ai_quality_score) AS avg_ai_score,
            SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN moderation_status = 'revise' THEN 1 ELSE 0 END) AS revise_count,
            SUM(CASE WHEN moderation_status = 'rejected' THEN 1 ELSE 0 END) AS reject_count
        FROM questions
        WHERE created_by = ?
    ");
    $qual_stmt->bind_param("i", $teacher_id);
    $qual_stmt->execute();
    $qual_data = $qual_stmt->get_result()->fetch_assoc();
    $qual_stmt->close();

    $avg_ai_score   = $qual_data['avg_ai_score'] !== null ? (float)$qual_data['avg_ai_score'] : null;
    $approved_count = (int)($qual_data['approved_count'] ?? 0);
    $revise_count   = (int)($qual_data['revise_count'] ?? 0);
    $reject_count   = (int)($qual_data['reject_count'] ?? 0);

    if ($questions_written > 0) {
        $ai_comp = $avg_ai_score !== null ? $avg_ai_score : 75.0; // Default AI component if NULL
        $mod_comp = ($approved_count / $questions_written) * 100.0;
        $quality_score = 0.5 * $ai_comp + 0.5 * $mod_comp;
    } else {
        $quality_score = 80.0; // Default neutral if no questions assigned
    }


    // ─────────────────────────────────────────────────────────────────
    // 3. MODERATION SUCCESS RATE (15%)
    // Percentage of authored questions approved without revision/rejection.
    // Also include their moderation activity details.
    // ─────────────────────────────────────────────────────────────────
    if ($questions_written > 0) {
        $moderation_success_score = ($approved_count / $questions_written) * 100.0;
    } else {
        $moderation_success_score = 85.0; // Default neutral if no questions authored
    }

    // Fetch details of questions they moderated themselves
    $mod_act_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_moderated
        FROM question_moderation
        WHERE moderator_id = ?
    ");
    $mod_act_stmt->bind_param("i", $teacher_id);
    $mod_act_stmt->execute();
    $total_moderated = (int)($mod_act_stmt->get_result()->fetch_assoc()['total_moderated'] ?? 0);
    $mod_act_stmt->close();


    // ─────────────────────────────────────────────────────────────────
    // 4. MARKING COMPLETION RATE (20%)
    // Percentage of marking assignments fully completed.
    // ─────────────────────────────────────────────────────────────────
    $mark_stmt = $conn->prepare("
        SELECT 
            COUNT(assignment_id) AS total_assignments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_assignments
        FROM marking_assignments
        WHERE teacher_id = ? AND school_id = ?
    ");
    $mark_stmt->bind_param("ii", $teacher_id, $school_id);
    $mark_stmt->execute();
    $mark_data = $mark_stmt->get_result()->fetch_assoc();
    $mark_stmt->close();

    $total_assignments     = (int)($mark_data['total_assignments'] ?? 0);
    $completed_assignments = (int)($mark_data['completed_assignments'] ?? 0);

    if ($total_assignments > 0) {
        $marking_completion_score = ($completed_assignments / $total_assignments) * 100.0;
    } else {
        $marking_completion_score = 100.0; // Default neutral if no marking assignments
    }


    // ─────────────────────────────────────────────────────────────────
    // 5. RESULT SUBMISSION TIMELINESS (15%)
    // Deductions for late submissions and rejected marks.
    // ─────────────────────────────────────────────────────────────────
    $late_submissions     = 0;
    $rejected_submissions = 0;

    if ($total_assignments > 0) {
        // Query marking assignments and determine if they were submitted late
        $time_stmt = $conn->prepare("
            SELECT 
                ma.deadline, ma.status,
                (SELECT MAX(m.submitted_at) 
                 FROM marks m 
                 JOIN students st ON m.student_id = st.student_id
                 WHERE m.exam_id = ma.exam_id 
                   AND m.subject_id = ma.subject_id 
                   AND m.teacher_id = ma.teacher_id
                   AND st.school_id = ma.school_id) AS max_submitted_at
            FROM marking_assignments ma
            WHERE ma.teacher_id = ? AND ma.school_id = ?
        ");
        $time_stmt->bind_param("ii", $teacher_id, $school_id);
        $time_stmt->execute();
        $assignments_list = $time_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $time_stmt->close();

        $current_date = date('Y-m-d');
        foreach ($assignments_list as $asg) {
            $deadline = $asg['deadline'];
            $max_sub  = $asg['max_submitted_at'];

            if ($deadline && $deadline !== '0000-00-00' && $deadline !== '1970-01-01') {
                if ($asg['status'] === 'completed' || $asg['status'] === 'submitted') {
                    if ($max_sub) {
                        $sub_date = date('Y-m-d', strtotime($max_sub));
                        if ($sub_date > $deadline) {
                            $late_submissions++;
                        }
                    }
                } else {
                    // Pending and overdue
                    if ($current_date > $deadline) {
                        $late_submissions++;
                    }
                }
            }
        }

        // Count rejected mark submissions (indicating inaccuracy/revisions requested)
        $reject_stmt = $conn->prepare("
            SELECT COUNT(DISTINCT exam_id, subject_id) AS rejs
            FROM marks
            WHERE teacher_id = ? AND status = 'rejected'
        ");
        $reject_stmt->bind_param("i", $teacher_id);
        $reject_stmt->execute();
        $rejected_submissions = (int)($reject_stmt->get_result()->fetch_assoc()['rejs'] ?? 0);
        $reject_stmt->close();

        // 15 points penalty per late, 10 points penalty per rejection
        $timeliness_score = max(0.0, 100.0 - ($late_submissions * 15.0) - ($rejected_submissions * 10.0));
    } else {
        $timeliness_score = 100.0; // Default neutral if no marking assignments
    }


    // ─────────────────────────────────────────────────────────────────
    // 6. PROFESSIONAL PARTICIPATION (10%)
    // Login count, last login recency, and audit logs count.
    // ─────────────────────────────────────────────────────────────────
    $part_stmt = $conn->prepare("
        SELECT login_count, last_login
        FROM users
        WHERE user_id = ?
    ");
    $part_stmt->bind_param("i", $teacher_id);
    $part_stmt->execute();
    $part_data = $part_stmt->get_result()->fetch_assoc();
    $part_stmt->close();

    $login_count = (int)($part_data['login_count'] ?? 0);
    $last_login  = $part_data['last_login'] ?? '';

    // Login count sub-score: target is 20 logins, cap at 100%
    $login_score = min(100.0, ($login_count / 20.0) * 100.0);

    // Recency sub-score
    $recency_score = 0.0;
    if (!empty($last_login)) {
        $days_ago = (time() - strtotime($last_login)) / (60 * 60 * 24);
        if ($days_ago <= 7)       $recency_score = 100.0;
        elseif ($days_ago <= 14)  $recency_score = 80.0;
        elseif ($days_ago <= 30)  $recency_score = 50.0;
        else                      $recency_score = 20.0;
    }

    // Audit logs count sub-score
    $log_stmt = $conn->prepare("
        SELECT COUNT(*) AS act_count
        FROM audit_logs
        WHERE user_id = ?
    ");
    $log_stmt->bind_param("i", $teacher_id);
    $log_stmt->execute();
    $act_count = (int)($log_stmt->get_result()->fetch_assoc()['act_count'] ?? 0);
    $log_stmt->close();

    // Target is 10 system actions
    $act_score = min(100.0, ($act_count / 10.0) * 100.0);

    $participation_score = (0.4 * $login_score) + (0.3 * $recency_score) + (0.3 * $act_score);


    // ─────────────────────────────────────────────────────────────────
    // OVERALL SCORE CALCULATION
    // ─────────────────────────────────────────────────────────────────
    $overall_score = (
        ($prep_score * $w_prep) +
        ($quality_score * $w_quality) +
        ($moderation_success_score * $w_mod) +
        ($marking_completion_score * $w_marking) +
        ($timeliness_score * $w_time) +
        ($participation_score * $w_part)
    ) / $total_weight;

    $overall_score = round($overall_score, 1);

    // ─────────────────────────────────────────────────────────────────
    // DYNAMIC METRICS FOR REPORTS (Average Student Scores & Pass Rates)
    // ─────────────────────────────────────────────────────────────────
    $acad_stmt = $conn->prepare("
        SELECT 
            AVG(score) AS avg_student_score,
            SUM(CASE WHEN score >= 40 THEN 1 ELSE 0 END) AS passed_students,
            COUNT(mark_id) AS total_marked_students
        FROM marks
        WHERE teacher_id = ? AND status = 'approved'
    ");
    $acad_stmt->bind_param("i", $teacher_id);
    $acad_stmt->execute();
    $acad_data = $acad_stmt->get_result()->fetch_assoc();
    $acad_stmt->close();

    $avg_student_score = $acad_data['avg_student_score'] !== null ? round((float)$acad_data['avg_student_score'], 1) : 0.0;
    $passed_students   = (int)($acad_data['passed_students'] ?? 0);
    $total_marked      = (int)($acad_data['total_marked_students'] ?? 0);
    $student_pass_rate = $total_marked > 0 ? round(($passed_students / $total_marked) * 100, 1) : 0.0;

    // Map Performance Levels and Recommendations
    $class_data = classify_performance($overall_score);

    return [
        'teacher_id'               => $teacher_id,
        'prep_score'               => round($prep_score, 1),
        'quality_score'            => round($quality_score, 1),
        'moderation_success_score' => round($moderation_success_score, 1),
        'marking_completion_score' => round($marking_completion_score, 1),
        'timeliness_score'         => round($timeliness_score, 1),
        'participation_score'      => round($participation_score, 1),
        'overall_score'            => $overall_score,
        'performance_level'        => $class_data['level'],
        'promotion_recommendation' => $class_data['recommendation'],
        
        // Contextual counts/data
        'questions_written'        => $questions_written,
        'distinct_exams'           => $distinct_exams,
        'avg_ai_quality_score'     => $avg_ai_score !== null ? round($avg_ai_score, 1) : null,
        'questions_approved'       => $approved_count,
        'questions_revise'         => $revise_count,
        'questions_rejected'       => $reject_count,
        'total_moderated'          => $total_moderated,
        'total_assignments'        => $total_assignments,
        'completed_assignments'    => $completed_assignments,
        'late_submissions'         => $late_submissions,
        'rejected_submissions'     => $rejected_submissions,
        'login_count'              => $login_count,
        'last_login'               => $last_login,
        'system_actions'           => $act_count,
        'avg_student_score'        => $avg_student_score,
        'student_pass_rate'        => $student_pass_rate,
        'total_marked_students'    => $total_marked
    ];
}

/**
 * Classifies teacher performance score into categories and recommendation actions.
 */
function classify_performance($score) {
    if ($score >= 95.0) {
        return ['level' => 'Outstanding', 'recommendation' => 'Eligible for Promotion'];
    } elseif ($score >= 90.0) {
        return ['level' => 'Excellent', 'recommendation' => 'Eligible for Leadership Roles'];
    } elseif ($score >= 80.0) {
        return ['level' => 'Very Good', 'recommendation' => 'Recognition Award'];
    } elseif ($score >= 70.0) {
        return ['level' => 'Good', 'recommendation' => 'Maintain Current Position'];
    } elseif ($score >= 60.0) {
        return ['level' => 'Fair', 'recommendation' => 'Professional Development Required'];
    } else {
        return ['level' => 'Poor', 'recommendation' => 'Immediate Intervention Required'];
    }
}

/**
 * Returns dynamic strengths, weaknesses, and achievement lists for a teacher based on their performance scores.
 */
function generate_teacher_insights(array $metrics) {
    $strengths = [];
    $weaknesses = [];
    $achievements = [];

    // Exam Preparation
    if ($metrics['prep_score'] >= 90) {
        $strengths[] = "Highly proactive exam preparation, consistently authoring items ahead of timelines.";
    } elseif ($metrics['prep_score'] < 60) {
        $weaknesses[] = "Low contribution rate to the school examination question bank.";
    }

    // Question Quality
    if ($metrics['quality_score'] >= 90) {
        $strengths[] = "Exceptional quality of assessment items with high AI quality feedback ratings.";
        $achievements[] = "Drafted high-quality questions requiring zero revisions on moderation.";
    } elseif ($metrics['quality_score'] < 70) {
        $weaknesses[] = "Lower question draft scores; review cognitive levels and grammar structures.";
    }

    // Moderation
    if ($metrics['moderation_success_score'] >= 90 && $metrics['questions_written'] > 0) {
        $strengths[] = "Excellent alignment with examination standards; questions are consistently approved.";
    } elseif ($metrics['questions_rejected'] > 0) {
        $weaknesses[] = "Authored questions flagged with rejections during structural moderation review.";
    }

    // Marking Completion
    if ($metrics['marking_completion_score'] >= 100 && $metrics['total_assignments'] > 0) {
        $strengths[] = "Flawless marking assignment record with 100% sheet finalization.";
        $achievements[] = "Completed all allocated script markings successfully.";
    } elseif ($metrics['marking_completion_score'] < 80) {
        $weaknesses[] = "Incomplete marking assignments; multiple sheets remain pending review.";
    }

    // Timeliness
    if ($metrics['timeliness_score'] >= 95 && $metrics['total_assignments'] > 0) {
        $strengths[] = "Consistent adherence to marking schedules and prompt results submissions.";
        $achievements[] = "Maintained a 100% on-time submission rate.";
    } elseif ($metrics['late_submissions'] > 0) {
        $weaknesses[] = "Recorded late mark submissions which delays final grades release.";
    }

    // Participation
    if ($metrics['participation_score'] >= 85) {
        $strengths[] = "High levels of professional participation and frequent portal usage.";
    } elseif ($metrics['participation_score'] < 50) {
        $weaknesses[] = "Low portal engagement frequency and system interaction.";
    }

    // Student performance accomplishments
    if ($metrics['student_pass_rate'] >= 85 && $metrics['total_marked_students'] > 0) {
        $achievements[] = "Achieved an outstanding student pass rate of " . $metrics['student_pass_rate'] . "% in marked subjects.";
    }

    // Fallbacks
    if (empty($strengths)) {
        $strengths[] = "Maintains satisfactory standard performance across key responsibilities.";
    }
    if (empty($weaknesses)) {
        $weaknesses[] = "No critical performance gaps or intervention flags identified.";
    }
    if (empty($achievements)) {
        $achievements[] = "Contributed to overall school markings pipeline activities.";
    }

    return [
        'strengths' => $strengths,
        'weaknesses' => $weaknesses,
        'achievements' => $achievements
    ];
}

/**
 * Generates smart insights for all teachers.
 * 
 * @param array $all_teachers Array of teachers with their metrics included
 * @param mysqli $conn
 * @return array Collection of key analytics categories
 */
function generate_school_smart_insights(array $all_teachers, $conn) {
    $insights = [
        'best_performing'           => null,
        'most_improved'             => null,
        'highest_student_pass_rate' => null,
        'fastest_marker'            => null,
        'best_exam_composer'        => null,
        'highest_moderation_success'=> null,
        'most_outstanding'          => null,
        'at_risk'                   => [],
        'missed_deadlines'          => [],
        'promotion_eligible'        => []
    ];

    if (empty($all_teachers)) {
        return $insights;
    }

    // Sort by overall score descending
    usort($all_teachers, function($a, $b) {
        return $b['metrics']['overall_score'] <=> $a['metrics']['overall_score'];
    });
    $insights['best_performing'] = $all_teachers[0];

    // Find highest student pass rate
    $max_pass_rate = -1.0;
    foreach ($all_teachers as $t) {
        if ($t['metrics']['total_marked_students'] > 0 && $t['metrics']['student_pass_rate'] > $max_pass_rate) {
            $max_pass_rate = $t['metrics']['student_pass_rate'];
            $insights['highest_student_pass_rate'] = $t;
        }
    }

    // Fastest marker (highest completion + lowest late submissions + highest total assignments)
    $best_marking_score = -1.0;
    $max_marking_volume = -1;
    foreach ($all_teachers as $t) {
        if ($t['metrics']['total_assignments'] > 0) {
            $score = $t['metrics']['marking_completion_score'] * 0.6 + $t['metrics']['timeliness_score'] * 0.4;
            if ($score > $best_marking_score || ($score === $best_marking_score && $t['metrics']['total_assignments'] > $max_marking_volume)) {
                $best_marking_score = $score;
                $max_marking_volume = $t['metrics']['total_assignments'];
                $insights['fastest_marker'] = $t;
            }
        }
    }

    // Best Exam Composer (highest questions authored + best quality)
    $best_composer_score = -1.0;
    foreach ($all_teachers as $t) {
        if ($t['metrics']['questions_written'] > 0) {
            $score = $t['metrics']['prep_score'] * 0.4 + $t['metrics']['quality_score'] * 0.6;
            if ($score > $best_composer_score) {
                $best_composer_score = $score;
                $insights['best_exam_composer'] = $t;
            }
        }
    }

    // Highest Moderation Success Rate
    $best_mod_success = -1.0;
    foreach ($all_teachers as $t) {
        if ($t['metrics']['questions_written'] > 0) {
            if ($t['metrics']['moderation_success_score'] > $best_mod_success) {
                $best_mod_success = $t['metrics']['moderation_success_score'];
                $insights['highest_moderation_success'] = $t;
            }
        }
    }

    // Most Outstanding Contributions (overall active engagement in both questions, moderating and marking)
    $max_contributions = -1;
    foreach ($all_teachers as $t) {
        $total_cont = $t['metrics']['questions_written'] + $t['metrics']['total_assignments'] + $t['metrics']['total_moderated'];
        if ($total_cont > $max_contributions) {
            $max_contributions = $total_cont;
            $insights['most_outstanding'] = $t;
        }
    }

    // Filter lists
    foreach ($all_teachers as $t) {
        // At Risk
        if ($t['metrics']['overall_score'] < 70.0) {
            $insights['at_risk'][] = $t;
        }
        // Missed multiple deadlines
        if ($t['metrics']['late_submissions'] > 1) {
            $insights['missed_deadlines'][] = $t;
        }
        // Eligible for promotion
        if ($t['metrics']['overall_score'] >= 95.0) {
            $insights['promotion_eligible'][] = $t;
        }
    }

    // Most Improved Teacher: compare to the latest snapshot in teacher_performance_history
    $best_improvement = 0.0;
    foreach ($all_teachers as $t) {
        $hist_stmt = $conn->prepare("
            SELECT overall_score 
            FROM teacher_performance_history
            WHERE teacher_id = ?
            ORDER BY year DESC, term DESC
            LIMIT 1
        ");
        $hist_stmt->bind_param("i", $t['user_id']);
        $hist_stmt->execute();
        $hist_res = $hist_stmt->get_result()->fetch_assoc();
        $hist_stmt->close();

        if ($hist_res) {
            $prev_score = (float)$hist_res['overall_score'];
            $improvement = $t['metrics']['overall_score'] - $prev_score;
            if ($improvement > $best_improvement) {
                $best_improvement = $improvement;
                $insights['most_improved'] = [
                    'teacher' => $t,
                    'improvement' => round($improvement, 1),
                    'prev_score' => $prev_score
                ];
            }
        }
    }

    return $insights;
}
