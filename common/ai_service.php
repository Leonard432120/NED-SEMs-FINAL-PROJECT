<?php
/**
 * Shared PHP helper for executing Python AI scripts via shell.
 */

if (!function_exists('ai_log')) {
    function ai_log($message, $context = []) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = date('Y-m-d H:i:s') . ' | ' . $message;
        if (!empty($context)) {
            $entry .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $entry .= PHP_EOL;

        @file_put_contents($logDir . '/ai_service.log', $entry, FILE_APPEND);
    }
}

if (!function_exists('ai_debug_dir')) {
    function ai_debug_dir() {
        $dir = dirname(__DIR__) . '/services/ai';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('ai_write_debug')) {
    function ai_write_debug($outputRaw, $stderrRaw, $cmd, $exitCode, $jsonError = '') {
        $dir = ai_debug_dir();
        $stamp = date('Y-m-d H:i:s');

        $outEntry = $stamp . "\n"
            . "COMMAND: " . $cmd . "\n"
            . "EXIT CODE: " . $exitCode . "\n"
            . "RAW OUTPUT:\n" . ($outputRaw !== null && $outputRaw !== '' ? $outputRaw : '(empty)') . "\n"
            . str_repeat('-', 80) . "\n";

        $errEntry = $stamp . "\n"
            . "COMMAND: " . $cmd . "\n"
            . "JSON ERROR: " . ($jsonError ?: 'none') . "\n"
            . "STDERR:\n" . ($stderrRaw !== null && $stderrRaw !== '' ? $stderrRaw : '(empty)') . "\n"
            . str_repeat('-', 80) . "\n";

        @file_put_contents($dir . '/debug_output.txt', $outEntry, FILE_APPEND);
        @file_put_contents($dir . '/debug_error.txt', $errEntry, FILE_APPEND);
    }
}

if (!function_exists('ai_resolve_python')) {
    function ai_resolve_python() {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [];

        $configFile = dirname(__DIR__) . '/config/ai.php';
        if (file_exists($configFile)) {
            $AI_PYTHON_PATH = null;
            include $configFile;
            if (!empty($AI_PYTHON_PATH) && file_exists($AI_PYTHON_PATH)) {
                $candidates[] = $AI_PYTHON_PATH;
            }
        }

        $whereOut = [];
        @exec('where python 2>nul', $whereOut, $whereCode);
        foreach ($whereOut as $line) {
            $line = trim($line);
            if ($line && file_exists($line) && stripos($line, 'WindowsApps') === false) {
                $candidates[] = $line;
            }
        }

        $pyLauncher = [];
        @exec('where py 2>nul', $pyLauncher, $pyCode);
        if (!empty($pyLauncher[0])) {
            $candidates[] = trim($pyLauncher[0]) . ' -3';
        }

        $candidates = array_merge($candidates, ['python', 'py -3', 'python3']);

        foreach (array_unique($candidates) as $bin) {
            $test = [];
            @exec($bin . ' --version 2>&1', $test, $code);
            if ($code === 0 && !empty($test)) {
                $resolved = $bin;
                ai_log('Python resolved', ['path' => $bin, 'version' => implode(' ', $test)]);
                return $resolved;
            }
        }

        $resolved = false;
        ai_log('Python not found', ['candidates' => $candidates]);
        return $resolved;
    }
}

if (!function_exists('ai_extract_json')) {
    function ai_extract_json($raw) {
        if (!is_string($raw)) {
            if (is_array($raw)) {
                $raw = trim(implode("\n", $raw));
            } else {
                return null;
            }
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\R/', $raw);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

if (!function_exists('run_python_ai_script')) {
    /**
     * Execute a Python script in services/ai/ and return decoded JSON.
     *
     * @param string $scriptFilename e.g. analyze_question.py
     * @param array  $payload        Data passed as JSON file argument (Windows-safe)
     * @return array
     */
    function run_python_ai_script($scriptFilename, array $payload = []) {
        $repoRoot = dirname(__DIR__);
        $scriptPath = $repoRoot . '/services/ai/' . ltrim($scriptFilename, '/');
        $debugDir = ai_debug_dir();

        if (!function_exists('shell_exec')) {
            ai_write_debug('', 'shell_exec is disabled in php.ini', '', -1, 'shell_exec disabled');
            return [
                'status' => 'failed',
                'error' => 'shell_exec() is disabled in PHP configuration',
                'ai_status' => 'PHP Config Error',
            ];
        }

        if (!file_exists($scriptPath)) {
            ai_log('Script not found', ['script' => $scriptPath]);
            return [
                'status' => 'failed',
                'error' => 'AI script not found: ' . $scriptFilename,
                'ai_status' => 'Script Missing',
            ];
        }

        $python = ai_resolve_python();
        if ($python === false) {
            ai_write_debug('', 'Python executable not found on PATH', '', 127, 'python missing');
            return [
                'status' => 'failed',
                'error' => 'Python not found. Set $AI_PYTHON_PATH in config/ai.php',
                'ai_status' => 'Python Missing',
                'warnings' => ['Configure Python path in config/ai.php for WAMP/Apache.'],
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return [
                'status' => 'failed',
                'error' => 'Failed to encode request payload as JSON',
                'ai_status' => 'Payload Error',
            ];
        }

        $payloadFile = $debugDir . '/payload_' . uniqid('', true) . '.json';
        $stderrFile = $debugDir . '/run_stderr_' . uniqid('', true) . '.txt';

        if (@file_put_contents($payloadFile, $jsonPayload) === false) {
            return [
                'status' => 'failed',
                'error' => 'Could not write AI payload file',
                'ai_status' => 'IO Error',
            ];
        }

        // Pass JSON via file — avoids Windows CMD echo mangling quoted JSON on stdin pipe
        $cmd = $python . ' '
            . escapeshellarg($scriptPath) . ' '
            . escapeshellarg($payloadFile)
            . ' 2>' . escapeshellarg($stderrFile);

        $outputRaw = shell_exec($cmd);
        $stderrRaw = file_exists($stderrFile) ? (string) file_get_contents($stderrFile) : '';

        @unlink($payloadFile);
        @unlink($stderrFile);

        $exitCode = ($outputRaw === null && $stderrRaw === '') ? 1 : 0;
        $decoded = ai_extract_json($outputRaw ?? '');
        $jsonError = '';

        if (!is_array($decoded)) {
            $jsonError = json_last_error_msg();
        }

        ai_write_debug($outputRaw ?? '(null)', $stderrRaw, $cmd, $exitCode, $jsonError);

        if (is_array($decoded)) {
            ai_log('AI success', ['script' => $scriptFilename]);
            return $decoded;
        }

        ai_log('AI failed', [
            'script' => $scriptFilename,
            'cmd' => $cmd,
            'json_error' => $jsonError,
            'output' => $outputRaw,
            'stderr' => $stderrRaw,
        ]);

        $detail = trim($stderrRaw) !== '' ? trim($stderrRaw) : trim((string) $outputRaw);
        if ($detail === '') {
            $detail = $jsonError ?: 'Empty response from Python';
        }

        return [
            'status' => 'failed',
            'error' => 'AI script returned invalid or empty JSON',
            'ai_status' => 'AI Failed',
            'quality_score' => 0,
            'recommendations' => ['AI analysis unavailable: ' . substr($detail, 0, 300)],
            'warnings' => ['Could not parse AI response. See services/ai/debug_output.txt'],
            'debug' => [
                'json_error' => $jsonError,
                'stderr' => substr($stderrRaw, 0, 500),
                'stdout' => substr((string) $outputRaw, 0, 500),
            ],
        ];
    }
}

if (!function_exists('analyze_question_for_teacher')) {
    function analyze_question_for_teacher($questionText, $marks, array $existingQuestions = []) {
        return run_python_ai_script('analyze_question.py', [
            'question' => $questionText,
            'marks' => (int) $marks,
            'existing_questions' => array_values($existingQuestions),
        ]);
    }
}

if (!function_exists('analyze_question_for_moderator')) {
    function analyze_question_for_moderator($questionText, $marks, array $existingQuestions = []) {
        return run_python_ai_script('moderator_report.py', [
            'question' => $questionText,
            'marks' => (int) $marks,
            'existing_questions' => array_values($existingQuestions),
        ]);
    }
}

if (!function_exists('ensure_question_ai_columns')) {
    function ensure_question_ai_columns($conn) {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $columns = [
            'ai_difficulty' => "VARCHAR(20) NULL DEFAULT NULL",
            'ai_cognitive_level' => "VARCHAR(50) NULL DEFAULT NULL",
            'ai_recommendations' => "TEXT NULL",
            'ai_topic' => "VARCHAR(255) NULL DEFAULT NULL",
            'ai_quality_score' => "DECIMAL(5,2) NULL DEFAULT NULL",
            'ai_analysis_date' => "DATETIME NULL DEFAULT NULL",
        ];

        foreach ($columns as $name => $definition) {
            $safeName = $conn->real_escape_string($name);
            $result = $conn->query("SHOW COLUMNS FROM questions LIKE '$safeName'");
            if ($result && $result->num_rows === 0) {
                $conn->query("ALTER TABLE questions ADD COLUMN `$name` $definition");
                ai_log('Added column', ['column' => $name]);
            }
        }
    }
}

?>
