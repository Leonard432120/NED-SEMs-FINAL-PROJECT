<div class="chart-box">

<?php
$total_status = array_sum($chart_data);

if ($total_status > 0):

    foreach ($chart_labels as $index => $label):

        $count = $chart_data[$index];
        $percent = round(($count / $total_status) * 100, 1);

        $colors = [
            '#22c55e',
            '#3b82f6',
            '#f59e0b',
            '#ef4444',
            '#8b5cf6',
            '#06b6d4'
        ];

        $color = $colors[$index % count($colors)];
?>

<div class="chart-row">

    <div class="chart-row-header">
        <span><?= htmlspecialchars($label) ?></span>
        <strong><?= $count ?></strong>
    </div>

    <div class="chart-progress">
        <div
            class="chart-progress-fill"
            style="width: <?= $percent ?>%; background: <?= $color ?>;">
        </div>
    </div>

    <small><?= $percent ?>%</small>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="empty-state">
    No examination statistics available.
</p>

<?php endif; ?>

</div>