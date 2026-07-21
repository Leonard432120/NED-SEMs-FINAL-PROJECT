<?php
/**
 * NED-SEMs AI Compose Bridge (Restructured)
 * ==========================================
 * PHP layer that calls the Python AI engine and
 * handles all question save/moderation logic.
 *
 * Python model is trained locally — no internet required.
 */

// ─────────────────────────────────────────────────────────────────────────────
// LOGGING
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_ai_log')) {
    function compose_ai_log(string $message, array $context = []): void {
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $entry = date('Y-m-d H:i:s') . ' | AI | ' . $message;
        if ($context) {
            $entry .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents($logDir . '/ai_service.log', $entry . PHP_EOL, FILE_APPEND);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DIRECTORY
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_ai_dir')) {
    function compose_ai_dir(): string {
        return __DIR__;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PYTHON RESOLVER — finds installed Python on Windows/WAMP
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_ai_resolve_python')) {
    function compose_ai_resolve_python(): string|false {
        static $resolved = null;
        if ($resolved !== null) return $resolved;

        $cacheFile = compose_ai_dir() . '/python_path.cache';
        if (file_exists($cacheFile)) {
            $cached = trim((string)file_get_contents($cacheFile));
            if ($cached && is_file($cached)) {
                $resolved = $cached;
                return $resolved;
            }
        }

        $candidates = [];

        // Common Windows install paths
        $patterns = [
            'C:\\Users\\*\\AppData\\Local\\Programs\\Python\\Python*\\python.exe',
            'C:\\Program Files\\Python*\\python.exe',
            'C:\\Python*\\python.exe',
        ];
        $localAppData = getenv('LOCALAPPDATA') ?: '';
        if ($localAppData) {
            $patterns[] = $localAppData . '\\Programs\\Python\\Python*\\python.exe';
        }

        foreach ($patterns as $pat) {
            $matches = @glob($pat, GLOB_NOSORT);
            if (!is_array($matches)) continue;
            rsort($matches);
            foreach ($matches as $path) {
                $path = str_replace('/', '\\', $path);
                if (is_file($path) && stripos($path, 'WindowsApps') === false) {
                    $candidates[] = $path;
                }
            }
        }

        // PATH lookup
        $whereOut = [];
        @exec('where python 2>nul', $whereOut);
        foreach ($whereOut as $line) {
            $line = trim(str_replace('/', '\\', $line));
            if ($line && is_file($line) && stripos($line, 'WindowsApps') === false) {
                $candidates[] = $line;
            }
        }

        $candidates = array_merge($candidates, ['python', 'py -3', 'python3']);

        foreach (array_unique($candidates) as $bin) {
            $test = [];
            @exec($bin . ' --version 2>&1', $test, $code);
            if ($code === 0 && !empty($test)) {
                $resolved = $bin;
                compose_ai_log('Python resolved', ['path' => $bin]);
                @file_put_contents($cacheFile, $resolved);
                return $resolved;
            }
        }

        $resolved = false;
        compose_ai_log('Python not found');
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// RUN PYTHON — executes the AI script and returns raw output
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_run_ai')) {
    function compose_run_ai(array $payload): array {
        // Try calling the Python microservice first (very fast!)
        $url = 'http://127.0.0.1:5899/analyze';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $http_code === 200) {
            $result = json_decode($response, true);
            if (is_array($result) && ($result['status'] ?? '') === 'success') {
                compose_ai_log('AI microservice success', [
                    'quality' => $result['quality_score'] ?? 0,
                    'bloom' => $result['bloom_level'] ?? '?'
                ]);
                return $result;
            }
        }

        // Server is not responding. Let's spawn it asynchronously in the background.
        $python = compose_ai_resolve_python();
        if ($python !== false) {
            $serverScript = compose_ai_dir() . '/ai_server.py';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @pclose(@popen("start /B " . escapeshellarg($python) . " " . escapeshellarg($serverScript) . " > NUL 2> NUL", "r"));
            } else {
                @shell_exec(escapeshellarg($python) . " " . escapeshellarg($serverScript) . " > /dev/null 2>&1 &");
            }
            compose_ai_log('Spawned AI microservice server in background.');
        }

        // Fall back to rule-based analysis immediately so the interface remains fast
        return compose_ai_rule_based($payload);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// RULE-BASED FALLBACK — works even if Python fails completely
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_ai_rule_based')) {
    function compose_ai_rule_based(array $payload): array {
        $text  = trim($payload['question'] ?? '');
        $marks = (int)($payload['marks'] ?? 0);

        if (!$text) {
            return [
                'status' => 'failed',
                'ai_status' => 'Empty Question',
                'quality_score' => 0,
                'bloom_level' => 'Unknown',
                'cognitive_level' => 'Unknown',
                'difficulty_level' => 'Unknown',
                'topic' => '',
                'recommendations' => ['Please enter a question.'],
                'warnings' => ['Question text is empty.'],
            ];
        }

        $lower = strtolower($text);
        $words = str_word_count($text);
        $score = 100;
        $feedback = [];

        // Bloom from keywords
        $bloomMap = [
            'Create'   => ['design', 'create', 'develop', 'construct', 'propose', 'invent', 'devise', 'produce'],
            'Evaluate' => ['evaluate', 'justify', 'assess', 'critique', 'judge', 'defend', 'argue'],
            'Analyze'  => ['compare', 'analyze', 'analyse', 'differentiate', 'examine', 'contrast', 'distinguish'],
            'Apply'    => ['solve', 'calculate', 'compute', 'demonstrate', 'use', 'apply', 'determine', 'find'],
            'Understand'=> ['explain', 'describe', 'summarize', 'summarise', 'interpret', 'outline', 'paraphrase'],
            'Remember' => ['define', 'list', 'state', 'name', 'identify', 'recall', 'write', 'label'],
        ];
        $bloom = 'Remember';
        foreach ($bloomMap as $level => $verbs) {
            foreach ($verbs as $v) {
                if (strpos($lower, $v) !== false) { $bloom = $level; break 2; }
            }
        }

        // Quality deductions
        if ($words < 5)  { $score -= 30; $feedback[] = 'Question is too short.'; }
        if ($words > 80) { $score -= 10; $feedback[] = 'Question is too long.'; }
        if (!preg_match('/\b(what|where|when|who|why|how|define|explain|describe|list|state|name|calculate|solve|compare|evaluate|justify|design|create|use|show|find|determine)\b/i', $text)) {
            $score -= 15; $feedback[] = 'Include a clear action verb.';
        }
        if (!preg_match('/[.?:]$/', rtrim($text))) {
            $score -= 10; $feedback[] = 'End the question with punctuation.';
        }
        if (ctype_lower($text[0])) {
            $score -= 5; $feedback[] = 'Capitalize the first letter.';
        }
        $score = max(0, min(100, $score));

        $suggestions = ($score >= 75 && count($feedback) === 0)
            ? ['Question meets quality standards.']
            : $feedback;

        $markMap = ['Remember'=>2,'Understand'=>4,'Apply'=>6,'Analyze'=>8,'Evaluate'=>10,'Create'=>12];
        $suggestedMarks = $markMap[$bloom] ?? $marks;

        $moderation = ($score >= 75) ? 'approved' : 'revise';

        return [
            'status'            => 'success',
            'ai_status'         => 'Rule-Based Analysis',
            'quality_score'     => $score,
            'bloom_level'       => $bloom,
            'cognitive_level'   => $bloom . 'ing',
            'difficulty_level'  => $words <= 10 ? 'Easy' : ($words <= 25 ? 'Medium' : 'Hard'),
            'topic'             => 'General',
            'current_marks'     => $marks,
            'suggested_marks'   => $suggestedMarks,
            'original_question' => $text,
            'suggested_question'=> ucfirst($text) . (str_ends_with($text, '?') || str_ends_with($text, '.') ? '' : '.'),
            'grammar_corrections'=> [],
            'recommendations'   => $suggestions ?: ['Question is acceptable.'],
            'warnings'          => $score < 60 ? ['Low quality — revision recommended.'] : [],
            'duplicate_detection'=> ['is_duplicate' => false, 'similarity_score' => 0],
            'moderation_recommendation' => $moderation,
            'moderation_reason' => $moderation === 'approved'
                ? "Rule-based: Meets quality threshold ($score%)."
                : "Rule-based: Quality score $score% — revision needed.",
            'teacher_guidance'  => $feedback ?: ['Question is acceptable.'],
            'risk_level'        => $score >= 75 ? 'Low' : ($score >= 50 ? 'Medium' : 'High'),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// JSON EXTRACTOR
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_ai_extract_json')) {
    function compose_ai_extract_json(string $raw): ?array {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = preg_replace('/^Warning:.*\R/m', '', $raw);
        $raw = trim($raw);
        if ($raw === '') return null;

        $flags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
        $decoded = json_decode($raw, true, 512, $flags);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

        // Try to extract JSON object from mixed output
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end > $start) {
            $chunk = substr($raw, $start, $end - $start + 1);
            $decoded = json_decode($chunk, true, 512, $flags);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }

        // Try last JSON line
        foreach (array_reverse(preg_split('/\R/', $raw)) as $line) {
            $line = trim($line);
            if ($line && $line[0] === '{') {
                $decoded = json_decode($line, true, 512, $flags);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
            }
        }
        return null;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NORMALIZE AI — ensure the result has all fields PHP templates expect
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_normalize_ai')) {
    function compose_normalize_ai(array $ai): array {
        $defaults = [
            'status'            => 'success',
            'ai_status'         => 'Analysis Complete',
            'quality_score'     => 0,
            'bloom_level'       => 'Unknown',
            'cognitive_level'   => 'Unknown',
            'difficulty_level'  => 'Unknown',
            'complexity_score'  => 0,
            'topic'             => 'General',
            'suggested_marks'   => 0,
            'current_marks'     => 0,
            'original_question' => '',
            'suggested_question'=> '',
            'grammar_corrections'   => [],
            'recommendations'   => [],
            'warnings'          => [],
            'duplicate_detection'   => ['is_duplicate' => false, 'similarity_score' => 0],
            'readability'       => [],
            'teacher_guidance'  => [],
            'risk_level'        => 'Low',
            'moderation_recommendation' => 'pending',
            'moderation_reason' => '',
        ];
        return array_merge($defaults, $ai);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ANALYZE FOR TEACHER — main call from compose_exam.php
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('analyze_question_for_teacher')) {
    function analyze_question_for_teacher(string $questionText, int $marks, array $existingQuestions = []): array {
        $raw = compose_run_ai([
            'question'           => $questionText,
            'marks'              => $marks,
            'existing_questions' => array_values($existingQuestions),
        ]);
        return compose_normalize_ai($raw);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PERMISSION CHECK
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_teacher_can_write_exam')) {
    /**
     * Check if a teacher/admin can write questions for this exam+subject combination.
     * @param $conn  DB connection
     * @param int $exam_id  The exam ID
     * @param int $user_id  The user attempting access
     * @param int $subject_id  The subject ID (0 = any subject in this exam)
     */
    function compose_teacher_can_write_exam($conn, int $exam_id, int $user_id, int $subject_id = 0): bool {
        // Admin/headteacher/exam_officer override
        $stmt3 = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt3) {
            $stmt3->bind_param('i', $user_id);
            $stmt3->execute();
            $row = $stmt3->get_result()->fetch_assoc();
            $stmt3->close();
            if ($row && in_array($row['role'], ['admin', 'headteacher', 'examination_officer'])) {
                return true;
            }
        }

        // Primary check: subject_assignments with item_writer role scoped to subject
        if ($subject_id > 0) {
            $stmt = $conn->prepare("
                SELECT 1
                FROM subject_assignments sa
                INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
                WHERE es.exam_id = ?
                  AND es.subject_id = ?
                  AND sa.teacher_id = ?
                  AND sa.role = 'item_writer'
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('iii', $exam_id, $subject_id, $user_id);
                $stmt->execute();
                $allowed = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($allowed) return true;
            }
        } else {
            // Any subject in this exam
            $stmt = $conn->prepare("
                SELECT 1
                FROM subject_assignments sa
                INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
                WHERE es.exam_id = ?
                  AND sa.teacher_id = ?
                  AND sa.role = 'item_writer'
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('ii', $exam_id, $user_id);
                $stmt->execute();
                $allowed = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($allowed) return true;
            }
        }

        // Fallback: check if teacher created ANY question for this exam+subject
        if ($subject_id > 0) {
            $stmt2 = $conn->prepare("
                SELECT 1 FROM questions q
                JOIN exam_subjects es ON q.exam_subject_id = es.id
                WHERE es.exam_id = ? AND es.subject_id = ? AND q.created_by = ? LIMIT 1
            ");
            if ($stmt2) {
                $stmt2->bind_param('iii', $exam_id, $subject_id, $user_id);
                $stmt2->execute();
                $allowed = $stmt2->get_result()->num_rows > 0;
                $stmt2->close();
                if ($allowed) return true;
            }
        }

        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// AI COLUMNS — ensure DB has required columns
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('ensure_question_ai_columns')) {
    function ensure_question_ai_columns($conn): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $cols = [
            'ai_difficulty'      => 'VARCHAR(20) NULL DEFAULT NULL',
            'ai_cognitive_level' => 'VARCHAR(50) NULL DEFAULT NULL',
            'ai_recommendations' => 'TEXT NULL',
            'ai_topic'           => 'VARCHAR(255) NULL DEFAULT NULL',
            'ai_quality_score'   => 'DECIMAL(5,2) NULL DEFAULT NULL',
            'ai_analysis_date'   => 'DATETIME NULL DEFAULT NULL',
            'moderation_status'  => "ENUM('pending','approved','revise','rejected') NULL DEFAULT 'pending'",
            'moderator_comment'  => 'TEXT NULL',
        ];

        foreach ($cols as $name => $def) {
            $safeName = $conn->real_escape_string($name);
            $res = $conn->query("SHOW COLUMNS FROM questions LIKE '$safeName'");
            if ($res && $res->num_rows === 0) {
                $conn->query("ALTER TABLE questions ADD COLUMN `$name` $def");
                compose_ai_log("Added column: $name");
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// BUILD AI MAP — for page load (shows existing AI data per question)
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_build_ai_map')) {
    /**
     * Build an AI analysis map for a list of questions.
     * Pass exam_subject_id for subject-scoped caching lookups.
     */
    function compose_build_ai_map($conn, array $questions, int $exam_id, int $exam_subject_id = 0): array {
        $ai_map = [];
        if (empty($questions)) return $ai_map;

        // Load cached analysis from DB — prefer subject-scoped cache
        $cached = [];
        if ($exam_subject_id > 0) {
            $stmt = $conn->prepare("
                SELECT aa.question_id, aa.feedback
                FROM ai_analysis aa
                JOIN questions q ON aa.question_id = q.question_id
                WHERE q.exam_subject_id = ?
                  AND aa.analysis_type = 'question_compose'
                  AND aa.status = 'completed'
                ORDER BY aa.analysis_id DESC
            ");
            if ($stmt) {
                $stmt->bind_param('i', $exam_subject_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $qid = (int)$row['question_id'];
                    if (!isset($cached[$qid])) {
                        $cached[$qid] = $row['feedback'];
                    }
                }
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("
                SELECT question_id, feedback
                FROM ai_analysis
                WHERE exam_id = ?
                  AND analysis_type = 'question_compose'
                  AND status = 'completed'
                ORDER BY analysis_id DESC
            ");
            if ($stmt) {
                $stmt->bind_param('i', $exam_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $qid = (int)$row['question_id'];
                    if (!isset($cached[$qid])) {
                        $cached[$qid] = $row['feedback'];
                    }
                }
                $stmt->close();
            }
        }

        foreach ($questions as $q) {
            $qid = (int)$q['question_id'];

            // Use cached data if available
            if (isset($cached[$qid])) {
                $decoded = json_decode($cached[$qid], true);
                if (is_array($decoded)) {
                    $ai_map[$qid] = compose_normalize_ai($decoded);
                    continue;
                }
            }

            // Build from stored DB columns (fast, no Python needed)
            if (!empty($q['ai_quality_score'])) {
                $ai_map[$qid] = compose_normalize_ai([
                    'status'            => 'success',
                    'ai_status'         => 'Cached Analysis',
                    'quality_score'     => (float)$q['ai_quality_score'],
                    'bloom_level'       => $q['ai_cognitive_level'] ?? 'Unknown',
                    'cognitive_level'   => $q['ai_cognitive_level'] ?? 'Unknown',
                    'difficulty_level'  => $q['ai_difficulty'] ?? 'Unknown',
                    'topic'             => $q['ai_topic'] ?? 'General',
                    'recommendations'   => json_decode($q['ai_recommendations'] ?? '[]', true) ?: [],
                ]);
                continue;
            }

            $ai_map[$qid] = null; // Will be analysed on demand via JS
        }

        return $ai_map;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SAVE QUESTION — handles insert + update + AI moderation
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('compose_save_question')) {
    function compose_save_question($conn, int $user_id, array $post, array $ai_data): array {
        $exam_id          = (int)($post['exam_id']        ?? 0);
        $subject_id       = (int)($post['subject_id']     ?? 0);
        $exam_subject_id  = (int)($post['exam_subject_id'] ?? 0);
        $question_id      = (int)($post['question_id']    ?? 0);
        $question_text    = trim($post['question_text']   ?? '');
        $marks            = (int)($post['marks']          ?? 0);
        $section_name     = trim($post['section_name']    ?? 'Section A');
        $order            = (int)($post['order']          ?? 1);
        $option_a         = trim($post['option_a']        ?? '');
        $option_b         = trim($post['option_b']        ?? '');
        $option_c         = trim($post['option_c']        ?? '');
        $option_d         = trim($post['option_d']        ?? '');
        $correct_opt      = trim($post['correct_option']  ?? '');

        if (!$exam_id || !$question_text || $marks <= 0) {
            return ['success' => false, 'error' => 'Invalid input — exam, question text, and marks are required.'];
        }

        // Resolve exam_subject_id if not provided
        if ($exam_subject_id <= 0 && $subject_id > 0) {
            $esq = $conn->prepare("
                SELECT id FROM exam_subjects WHERE exam_id = ? AND subject_id = ? LIMIT 1
            ");
            if ($esq) {
                $esq->bind_param('ii', $exam_id, $subject_id);
                $esq->execute();
                $esr = $esq->get_result()->fetch_assoc();
                $esq->close();
                if ($esr) {
                    $exam_subject_id = (int)$esr['id'];
                }
            }
        }

        if ($exam_subject_id <= 0) {
            return ['success' => false, 'error' => 'Subject not selected. Please select a subject before saving a question.'];
        }

        if (!compose_teacher_can_write_exam($conn, $exam_id, $user_id, $subject_id)) {
            return ['success' => false, 'error' => 'Access denied. You are not assigned as item writer for this subject.'];
        }

        ensure_question_ai_columns($conn);

        // Run AI if we don't have fresh data
        if (empty($ai_data) || !isset($ai_data['quality_score'])) {
            $existing = [];
            $chk = $conn->prepare('SELECT question_text FROM questions WHERE exam_subject_id = ? AND question_id != ?');
            $chk->bind_param('ii', $exam_subject_id, $question_id);
            $chk->execute();
            $chkRes = $chk->get_result();
            while ($row = $chkRes->fetch_assoc()) $existing[] = $row['question_text'];
            $chk->close();

            $ai_data = analyze_question_for_teacher($question_text, $marks, $existing);
        } else {
            $ai_data = compose_normalize_ai($ai_data);
        }

        // Extract AI fields
        $ai_quality   = (float)($ai_data['quality_score'] ?? 0);
        $ai_bloom     = $ai_data['bloom_level']        ?? null;
        $ai_cognitive = $ai_data['cognitive_level']    ?? null;
        $ai_diff      = $ai_data['difficulty_level']   ?? null;
        $ai_topic     = $ai_data['topic']              ?? null;
        $ai_recs      = json_encode($ai_data['recommendations'] ?? [], JSON_UNESCAPED_UNICODE);
        $ai_date      = date('Y-m-d H:i:s');
        $ai_score     = $ai_quality;

        // Moderation decision
        $warnings      = $ai_data['warnings'] ?? [];
        $mod_rec       = $ai_data['moderation_recommendation'] ?? 'pending';
        $mod_reason    = $ai_data['moderation_reason'] ?? '';

        if ($ai_quality >= 75 && empty($warnings)) {
            $mod_status  = 'approved';
            $mod_comment = 'AI Auto-Approved: ' . $mod_reason;
        } elseif ($ai_quality < 60 || !empty($warnings)) {
            $mod_status  = 'revise';
            $issues      = array_unique(array_merge($warnings, array_slice($ai_data['recommendations'] ?? [], 0, 2)));
            $mod_comment = 'AI Flagged: ' . implode('; ', array_slice($issues, 0, 3));
        } else {
            $mod_status  = 'pending';
            $mod_comment = $mod_reason ?: 'Awaiting moderator review.';
        }

        // INSERT or UPDATE
        if ($question_id > 0) {
            // Verify it belongs to this exam+subject and is not already approved (locked)
            $chk = $conn->prepare('SELECT question_id, moderation_status FROM questions WHERE question_id = ? AND exam_subject_id = ?');
            $chk->bind_param('ii', $question_id, $exam_subject_id);
            $chk->execute();
            $chkRow = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$chkRow) {
                return ['success' => false, 'error' => 'Question not found for this subject.'];
            }
            if (($chkRow['moderation_status'] ?? '') === 'approved') {
                return ['success' => false, 'error' => 'This question has been approved by the moderator and is locked. It cannot be edited.', 'locked' => true];
            }

            $stmt = $conn->prepare("
                UPDATE questions SET
                    question_order = ?, question_text = ?, marks = ?,
                    section_name = ?, option_a = ?, option_b = ?,
                    option_c = ?, option_d = ?, correct_option = ?,
                    ai_score = ?, ai_difficulty = ?, ai_cognitive_level = ?,
                    ai_recommendations = ?, ai_topic = ?,
                    ai_quality_score = ?, ai_analysis_date = ?,
                    moderation_status = ?, moderator_comment = ?
                WHERE question_id = ? AND exam_subject_id = ?
            ");
            $stmt->bind_param(
                'isissssssdssssdsssii',
                $order, $question_text, $marks,
                $section_name, $option_a, $option_b,
                $option_c, $option_d, $correct_opt,
                $ai_score, $ai_diff, $ai_cognitive,
                $ai_recs, $ai_topic,
                $ai_quality, $ai_date,
                $mod_status, $mod_comment,
                $question_id, $exam_subject_id
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO questions (
                    exam_id, exam_subject_id, question_order, question_text, marks, created_by,
                    section_name, question_type, option_a, option_b, option_c, option_d,
                    correct_option, ai_score, ai_difficulty, ai_cognitive_level,
                    ai_recommendations, ai_topic, ai_quality_score, ai_analysis_date,
                    moderation_status, moderator_comment
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'structured', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'iiiiisssssssdssssdsss',
                $exam_id, $exam_subject_id, $order, $question_text, $marks, $user_id,
                $section_name, $option_a, $option_b, $option_c, $option_d,
                $correct_opt, $ai_score, $ai_diff, $ai_cognitive,
                $ai_recs, $ai_topic, $ai_quality, $ai_date,
                $mod_status, $mod_comment
            );
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'error' => 'Database error: ' . $err];
        }

        if ($question_id <= 0) {
            $question_id = (int)$stmt->insert_id;
        }
        $stmt->close();

        // Store in ai_analysis table
        $feedback_json = json_encode($ai_data, JSON_UNESCAPED_UNICODE);
        $ins = $conn->prepare("
            INSERT INTO ai_analysis (exam_id, question_id, analysis_type, score, feedback, status)
            VALUES (?, ?, 'question_compose', ?, ?, 'completed')
        ");
        if ($ins) {
            $ins->bind_param('iids', $exam_id, $question_id, $ai_quality, $feedback_json);
            $ins->execute();
            $ins->close();
        }

        // Audit log
        $action  = $post['question_id'] > 0 ? 'UPDATE_QUESTION' : 'CREATE_QUESTION';
        $details = "Q#$question_id in exam #$exam_id | AI score: $ai_quality% | Status: $mod_status";
        $aud = $conn->prepare('INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)');
        if ($aud) {
            $aud->bind_param('iss', $user_id, $action, $details);
            $aud->execute();
            $aud->close();
        }

        compose_ai_log('Question saved', [
            'question_id' => $question_id,
            'exam_id'     => $exam_id,
            'ai_quality'  => $ai_quality,
            'mod_status'  => $mod_status,
        ]);

        return [
            'success'     => true,
            'question_id' => $question_id,
            'mod_status'  => $mod_status,
            'mod_comment' => $mod_comment,
            'ai'          => $ai_data,
        ];
    }
}