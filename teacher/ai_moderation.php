<?php
header('Content-Type: application/json');
require_once __DIR__ . '/teacher_init.php';

$question_text = trim($_POST['question_text'] ?? $_GET['question_text'] ?? '');
$marks = isset($_POST['marks']) ? (int)$_POST['marks'] : 0;

if (!$question_text) {
    echo json_encode(['error' => 'Question text is required.']);
    exit();
}

function shell_escape($value) {
    return '"' . str_replace('"', '\\"', $value) . '"';
}

$repoRoot = dirname(__DIR__);
$cli = $repoRoot . '/ai_moderation_cli.py';
$question_arg = shell_escape($question_text);
$marks_arg = shell_escape((string)$marks);

$pythonCandidates = ['python', 'py -3', 'python3'];
$output = [];
$exitCode = 1;

foreach ($pythonCandidates as $python) {
    $output = [];
    $command = 'cd /d ' . shell_escape($repoRoot) . ' && ' . $python . ' ' . shell_escape($cli) . ' --question ' . $question_arg . ' --marks ' . $marks_arg . ' 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode === 0) {
        break;
    }
}

if ($exitCode !== 0) {
    echo json_encode([
        'error' => 'AI analysis unavailable.',
        'details' => implode("\n", $output)
    ]);
    exit();
}

echo implode("\n", $output);
