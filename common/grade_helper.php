<?php
/**
 * Grade Helper — Malawi National Examination grading
 * Used by: teacher, EO, HT, admin compile pages
 */

/**
 * Calculate grade from a percentage score.
 * MSCE (Form 4): Grades 1–9  |  JCE (Form 2): Grades A–E / F
 * We use a unified scale stored as grade string.
 */
function calcGrade(float $score, string $class = ''): string {
    // Unified MSCE-style grading (used for both forms internally)
    if ($score >= 80) return '1';
    if ($score >= 70) return '2';
    if ($score >= 60) return '3';
    if ($score >= 50) return '4';
    if ($score >= 40) return '5';
    if ($score >= 33) return '6';
    if ($score >= 25) return '7';
    if ($score >= 20) return '8';
    return '9';
}

function gradeLabel(string $grade): string {
    $labels = [
        '1' => 'Distinction',
        '2' => 'Distinction',
        '3' => 'Merit',
        '4' => 'Credit',
        '5' => 'Credit',
        '6' => 'Pass',
        '7' => 'Pass',
        '8' => 'Fail',
        '9' => 'Fail',
    ];
    return $labels[$grade] ?? '—';
}

function gradeColor(string $grade): string {
    if (in_array($grade, ['1','2','3'])) return 'success';
    if (in_array($grade, ['4','5'])) return 'info';
    if (in_array($grade, ['6','7'])) return 'warning';
    return 'danger';
}

function isPassing(string $grade): bool {
    return in_array($grade, ['1','2','3','4','5','6','7']);
}
