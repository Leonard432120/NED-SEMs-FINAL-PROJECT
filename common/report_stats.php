<?php
/**
 * ============================================================
 * NED-SEMS Report Statistics Library
 * common/report_stats.php
 *
 * Reusable pure-PHP statistical functions used by all report
 * pages in the Analytics & Reports module.
 * ============================================================
 */

/**
 * Compute a full statistical summary of an array of numeric values.
 *
 * @param  float[] $values
 * @return array{count:int, mean:float, median:float, mode:float|null,
 *               min:float, max:float, range:float, variance:float,
 *               std_dev:float, cv:float, q1:float, q3:float, iqr:float,
 *               p10:float, p90:float, sum:float}
 */
function stats_summary(array $values): array {
    $values = array_values(array_filter($values, 'is_numeric'));
    $n = count($values);

    if ($n === 0) {
        return [
            'count'    => 0,   'mean'    => 0.0,  'median' => 0.0,
            'mode'     => null,'min'     => 0.0,  'max'    => 0.0,
            'range'    => 0.0, 'variance'=> 0.0,  'std_dev'=> 0.0,
            'cv'       => 0.0, 'q1'      => 0.0,  'q3'     => 0.0,
            'iqr'      => 0.0, 'p10'     => 0.0,  'p90'    => 0.0,
            'sum'      => 0.0,
        ];
    }

    sort($values);

    $sum  = array_sum($values);
    $mean = $sum / $n;

    // Median
    $mid    = (int)floor($n / 2);
    $median = ($n % 2 === 0)
        ? ($values[$mid - 1] + $values[$mid]) / 2
        : $values[$mid];

    // Mode (most frequent value)
    $freq = array_count_values(array_map('intval', $values));
    arsort($freq);
    $mode_val = key($freq);
    $mode     = (count($freq) < $n) ? (float)$mode_val : null;

    // Variance & Std Dev (population)
    $sq_diff  = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values));
    $variance = $sq_diff / $n;
    $std_dev  = sqrt($variance);
    $cv       = $mean != 0 ? ($std_dev / $mean) * 100 : 0.0;

    // Quartiles & percentiles
    $q1  = stats_percentile($values, 25);
    $q3  = stats_percentile($values, 75);
    $p10 = stats_percentile($values, 10);
    $p90 = stats_percentile($values, 90);

    return [
        'count'    => $n,
        'mean'     => round($mean, 2),
        'median'   => round($median, 2),
        'mode'     => $mode !== null ? round($mode, 2) : null,
        'min'      => round(min($values), 2),
        'max'      => round(max($values), 2),
        'range'    => round(max($values) - min($values), 2),
        'variance' => round($variance, 2),
        'std_dev'  => round($std_dev, 2),
        'cv'       => round($cv, 2),
        'q1'       => round($q1, 2),
        'q3'       => round($q3, 2),
        'iqr'      => round($q3 - $q1, 2),
        'p10'      => round($p10, 2),
        'p90'      => round($p90, 2),
        'sum'      => round($sum, 2),
    ];
}

/**
 * Compute a percentile value from a (pre-sorted) array.
 *
 * @param  float[] $sorted_values  Already sorted ascending
 * @param  float   $pct            Percentile 0–100
 * @return float
 */
function stats_percentile(array $sorted_values, float $pct): float {
    $n = count($sorted_values);
    if ($n === 0) return 0.0;
    if ($n === 1) return (float)$sorted_values[0];

    $index = ($pct / 100) * ($n - 1);
    $lower = (int)floor($index);
    $upper = (int)ceil($index);
    $frac  = $index - $lower;

    return $sorted_values[$lower] + $frac * ($sorted_values[$upper] - $sorted_values[$lower]);
}

/**
 * Calculate pass rate and fail rate from an array of scores.
 * Malawi threshold: score >= 25 means passing (grade 1–7).
 *
 * @param  float[] $scores
 * @param  float   $threshold   Default 25.0 (grade 1–7 boundary)
 * @return array{pass_count:int, fail_count:int, pass_rate:float, fail_rate:float, total:int}
 */
function stats_pass_rate(array $scores, float $threshold = 25.0): array {
    $scores      = array_filter($scores, 'is_numeric');
    $total       = count($scores);
    $pass_count  = count(array_filter($scores, fn($s) => (float)$s >= $threshold));
    $fail_count  = $total - $pass_count;
    $pass_rate   = $total > 0 ? round(($pass_count / $total) * 100, 1) : 0.0;
    $fail_rate   = $total > 0 ? round(($fail_count / $total) * 100, 1) : 0.0;

    return [
        'pass_count' => $pass_count,
        'fail_count' => $fail_count,
        'pass_rate'  => $pass_rate,
        'fail_rate'  => $fail_rate,
        'total'      => $total,
    ];
}

/**
 * Compute grade distribution (grades 1–9) from an array of percentage scores.
 * Uses the Malawi MSCE grading scale.
 *
 * @param  float[] $scores
 * @return array  Each element: ['grade'=>'1', 'label'=>'Distinction', 'count'=>N, 'pct'=>X.X]
 */
function stats_grade_distribution(array $scores): array {
    $scores = array_filter($scores, 'is_numeric');
    $n      = count($scores);

    $buckets = [
        '1' => ['label' => 'Distinction', 'min' => 80],
        '2' => ['label' => 'Distinction', 'min' => 70],
        '3' => ['label' => 'Merit',       'min' => 60],
        '4' => ['label' => 'Credit',      'min' => 50],
        '5' => ['label' => 'Credit',      'min' => 40],
        '6' => ['label' => 'Pass',        'min' => 33],
        '7' => ['label' => 'Pass',        'min' => 25],
        '8' => ['label' => 'Fail',        'min' => 20],
        '9' => ['label' => 'Fail',        'min' => 0],
    ];

    $counts = array_fill_keys(array_keys($buckets), 0);

    foreach ($scores as $s) {
        $s = (float)$s;
        if      ($s >= 80) $counts['1']++;
        elseif  ($s >= 70) $counts['2']++;
        elseif  ($s >= 60) $counts['3']++;
        elseif  ($s >= 50) $counts['4']++;
        elseif  ($s >= 40) $counts['5']++;
        elseif  ($s >= 33) $counts['6']++;
        elseif  ($s >= 25) $counts['7']++;
        elseif  ($s >= 20) $counts['8']++;
        else               $counts['9']++;
    }

    $result = [];
    foreach ($buckets as $g => $info) {
        $count    = $counts[$g];
        $pct      = $n > 0 ? round(($count / $n) * 100, 1) : 0.0;
        $result[] = [
            'grade' => $g,
            'label' => $info['label'],
            'count' => $count,
            'pct'   => $pct,
        ];
    }
    return $result;
}

/**
 * Calculate percentage growth rate from one value to the next.
 *
 * @return float  Positive = growth, negative = decline, 0 = no change
 */
function stats_growth_rate(float $prev, float $curr): float {
    if ($prev == 0) return $curr > 0 ? 100.0 : 0.0;
    return round((($curr - $prev) / abs($prev)) * 100, 2);
}

/**
 * Compute moving average over an array of values.
 *
 * @param  float[] $values
 * @param  int     $window   Number of periods in window
 * @return float[]
 */
function stats_moving_average(array $values, int $window = 3): array {
    $n      = count($values);
    $result = [];
    for ($i = 0; $i < $n; $i++) {
        $start  = max(0, $i - $window + 1);
        $slice  = array_slice($values, $start, $i - $start + 1);
        $result[] = round(array_sum($slice) / count($slice), 2);
    }
    return $result;
}

/**
 * Determine a human-readable performance category from an average score.
 *
 * @return string  'Excellent' | 'Good' | 'Average' | 'Needs Improvement' | 'Critical'
 */
function stats_performance_category(float $avg): string {
    if ($avg >= 70) return 'Excellent';
    if ($avg >= 55) return 'Good';
    if ($avg >= 40) return 'Average';
    if ($avg >= 25) return 'Needs Improvement';
    return 'Critical';
}

/**
 * Determine risk level from average score and pass rate.
 *
 * @return string  'Low' | 'Medium' | 'High' | 'Critical'
 */
function stats_risk_level(float $avg, float $pass_rate): string {
    if ($avg >= 60 && $pass_rate >= 80) return 'Low';
    if ($avg >= 45 && $pass_rate >= 60) return 'Medium';
    if ($avg >= 30 && $pass_rate >= 40) return 'High';
    return 'Critical';
}

/**
 * Determine trend direction.
 *
 * @param  float  $prev
 * @param  float  $curr
 * @param  float  $threshold  Minimum change to count as trend (default 1.0%)
 * @return string 'up' | 'down' | 'stable'
 */
function stats_trend(float $prev, float $curr, float $threshold = 1.0): string {
    $diff = $curr - $prev;
    if (abs($diff) < $threshold) return 'stable';
    return $diff > 0 ? 'up' : 'down';
}

/**
 * Generate a list of automatic text recommendations.
 *
 * @param  float  $avg
 * @param  float  $pass_rate
 * @param  string $trend     'up' | 'down' | 'stable'
 * @param  string $entity    'school' | 'district' | 'division' | 'subject'
 * @return string[]
 */
function stats_recommendations(float $avg, float $pass_rate, string $trend = 'stable', string $entity = 'school'): array {
    $recs = [];

    if ($avg < 30) {
        $recs[] = "Immediate intervention required — average score is critically low at {$avg}%.";
        $recs[] = "Deploy additional qualified teachers and remedial support programmes.";
        $recs[] = "Conduct urgent on-site inspection and resource assessment.";
    } elseif ($avg < 45) {
        $recs[] = "Performance is below the acceptable standard. Targeted support is recommended.";
        $recs[] = "Strengthen subject-specific teacher training, especially for low-scoring subjects.";
        $recs[] = "Investigate attendance and engagement levels among registered candidates.";
    } elseif ($avg < 60) {
        $recs[] = "Performance is at an acceptable level but has significant room for improvement.";
        $recs[] = "Focus on raising achievement in the 40–60% score band to push candidates to credit grades.";
        $recs[] = "Encourage peer learning programmes and in-school mentoring.";
    } else {
        $recs[] = "Performance is strong. Maintain current standards and recognise outstanding achievement.";
        $recs[] = "Share best practices from this {$entity} with lower-performing peers.";
    }

    if ($pass_rate < 50) {
        $recs[] = "More than half of candidates are failing. Review the quality of marking assignments and timely feedback.";
    } elseif ($pass_rate < 70) {
        $recs[] = "Pass rate is moderate. Focus on bridging support for candidates scoring in the 25–40% range.";
    }

    if ($trend === 'down') {
        $recs[] = "A declining performance trend has been detected. Investigate possible causes: teacher changes, absenteeism, or resource shortfalls.";
    } elseif ($trend === 'up') {
        $recs[] = "An improving trend is noted — sustain the momentum with continued investment and monitoring.";
    }

    return $recs;
}

/**
 * Return inline HTML badge for a performance category.
 */
function stats_badge(string $category): string {
    $map = [
        'Excellent'         => 'rpt-badge--excellent',
        'Good'              => 'rpt-badge--good',
        'Average'           => 'rpt-badge--average',
        'Needs Improvement' => 'rpt-badge--poor',
        'Critical'          => 'rpt-badge--critical',
    ];
    $cls = $map[$category] ?? 'rpt-badge--nodata';
    return '<span class="rpt-badge ' . $cls . '">' . htmlspecialchars($category) . '</span>';
}

/**
 * Return inline HTML badge for a risk level.
 */
function stats_risk_badge(string $risk): string {
    $map = [
        'Low'      => 'rpt-badge--excellent',
        'Medium'   => 'rpt-badge--average',
        'High'     => 'rpt-badge--poor',
        'Critical' => 'rpt-badge--critical',
    ];
    $cls = $map[$risk] ?? 'rpt-badge--nodata';
    return '<span class="rpt-badge ' . $cls . '">' . htmlspecialchars($risk) . ' Risk</span>';
}

/**
 * Return HTML span for a trend indicator.
 */
function stats_trend_html(string $trend): string {
    if ($trend === 'up')     return '<span class="rpt-trend rpt-trend--up">▲ Improving</span>';
    if ($trend === 'down')   return '<span class="rpt-trend rpt-trend--down">▼ Declining</span>';
    return '<span class="rpt-trend rpt-trend--stable">– Stable</span>';
}
