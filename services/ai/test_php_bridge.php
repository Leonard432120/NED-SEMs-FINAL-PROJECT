<?php
/**
 * Quick bridge test — run from browser or CLI to verify PHP → Python → JSON.
 * Delete or restrict access after debugging.
 */
require_once __DIR__ . '/compose_bridge.php';

header('Content-Type: application/json');

$result = analyze_question_for_teacher(
    'Explain the process of photosynthesis in plants.',
    5,
    []
);

echo json_encode([
    'ok' => ($result['status'] ?? '') === 'success',
    'python' => compose_ai_resolve_python(),
    'shell_exec_enabled' => function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions')))),
    'ai' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
