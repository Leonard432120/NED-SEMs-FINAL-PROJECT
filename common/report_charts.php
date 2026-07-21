<?php
/**
 * ============================================================
 * NED-SEMS Report Charts Library
 * common/report_charts.php
 *
 * Pure SVG chart rendering functions — no external libraries.
 * All functions return an HTML string safe to echo directly.
 * ============================================================
 */

/**
 * ── Vertical Bar Chart ───────────────────────────────────────
 * @param string[] $labels
 * @param float[]  $values
 * @param array    $opts  width, height, y_suffix, max_val, auto_color, title, color
 */
function chart_bars(array $labels, array $values, array $opts = []): string {
    if (empty($values)) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $W         = (int)($opts['width']      ?? 520);
    $H         = (int)($opts['height']     ?? 240);
    $suffix    = $opts['y_suffix']  ?? '%';
    $max_val   = (float)($opts['max_val'] ?? max(max($values), 1));
    $auto_color= $opts['auto_color'] ?? true;
    $base_color= $opts['color']      ?? '#3b82f6';

    $padL = 44; $padR = 16; $padT = 22; $padB = 46;
    $aW   = $W - $padL - $padR;
    $aH   = $H - $padT - $padB;
    $n    = count($values);

    $bar_w = max(8, ($aW / $n) * 0.6);
    $gap   = ($aW / $n) - $bar_w;

    $svg  = "<svg viewBox=\"0 0 {$W} {$H}\" width=\"100%\" role=\"img\" aria-label=\"Bar chart\">\n";

    // Y-axis grid lines and labels
    for ($k = 0; $k <= 5; $k++) {
        $val  = ($max_val / 5) * $k;
        $y    = $padT + $aH - ($k / 5) * $aH;
        $svg .= "  <line x1=\"{$padL}\" y1=\"{$y}\" x2=\"" . ($W - $padR) . "\" y2=\"{$y}\" stroke=\"#f1f5f9\" stroke-width=\"1.5\"/>\n";
        $svg .= "  <text x=\"" . ($padL - 6) . "\" y=\"" . ($y + 4) . "\" text-anchor=\"end\" font-size=\"9\" fill=\"#94a3b8\">" . round($val) . $suffix . "</text>\n";
    }

    // Baseline
    $baseline_y = $padT + $aH;
    $svg .= "  <line x1=\"{$padL}\" y1=\"{$baseline_y}\" x2=\"" . ($W - $padR) . "\" y2=\"{$baseline_y}\" stroke=\"#e2e8f0\" stroke-width=\"1\"/>\n";

    // Bars
    for ($i = 0; $i < $n; $i++) {
        $val    = (float)($values[$i] ?? 0);
        $ratio  = $max_val > 0 ? min(1, $val / $max_val) : 0;
        $bH     = max(2, $ratio * $aH);
        $x      = $padL + $i * ($bar_w + $gap) + $gap / 2;
        $y      = $padT + $aH - $bH;

        if ($auto_color) {
            $color = $val >= 60 ? '#16a34a' : ($val >= 40 ? '#3b82f6' : ($val >= 25 ? '#f59e0b' : '#ef4444'));
        } else {
            $color = $base_color;
        }

        $lbl = htmlspecialchars($labels[$i] ?? '');
        $svg .= "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$bar_w}\" height=\"{$bH}\" rx=\"4\" fill=\"{$color}\" opacity=\"0.9\"/>\n";
        // Value label above bar
        $svg .= "  <text x=\"" . ($x + $bar_w / 2) . "\" y=\"" . ($y - 5) . "\" text-anchor=\"middle\" font-size=\"9\" font-weight=\"700\" fill=\"#374151\">" . round($val, 1) . $suffix . "</text>\n";
        // X-axis label
        $svg .= "  <text x=\"" . ($x + $bar_w / 2) . "\" y=\"" . ($baseline_y + 16) . "\" text-anchor=\"middle\" font-size=\"9\" fill=\"#64748b\">{$lbl}</text>\n";
    }

    $svg .= "</svg>\n";
    return $svg;
}

/**
 * ── Horizontal Bar Chart ─────────────────────────────────────
 * Best for ranked lists (schools, districts).
 * @param string[] $labels
 * @param float[]  $values
 * @param array    $opts  width, y_suffix, max_val, row_height
 */
function chart_hbars(array $labels, array $values, array $opts = []): string {
    if (empty($values)) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $W          = (int)($opts['width']      ?? 560);
    $suffix     = $opts['y_suffix']  ?? '%';
    $max_val    = (float)($opts['max_val'] ?? max(max($values), 1));
    $row_h      = (int)($opts['row_height'] ?? 30);
    $padL       = 140; $padR = 60; $padT = 10; $padB = 10;
    $n          = count($labels);
    $aW         = $W - $padL - $padR;
    $H          = $padT + $padB + $n * ($row_h + 8);

    $svg  = "<svg viewBox=\"0 0 {$W} {$H}\" width=\"100%\" role=\"img\" aria-label=\"Horizontal bar chart\">\n";

    // X-axis grid lines
    for ($k = 0; $k <= 4; $k++) {
        $val = ($max_val / 4) * $k;
        $x   = $padL + ($val / $max_val) * $aW;
        $svg .= "  <line x1=\"{$x}\" y1=\"{$padT}\" x2=\"{$x}\" y2=\"" . ($H - $padB) . "\" stroke=\"#f1f5f9\" stroke-width=\"1.5\"/>\n";
        $svg .= "  <text x=\"{$x}\" y=\"{$padT}\" text-anchor=\"middle\" font-size=\"9\" fill=\"#94a3b8\" dy=\"-3\">" . round($val) . $suffix . "</text>\n";
    }

    for ($i = 0; $i < $n; $i++) {
        $val   = (float)($values[$i] ?? 0);
        $ratio = $max_val > 0 ? min(1, $val / $max_val) : 0;
        $bW    = max(2, $ratio * $aW);
        $y     = $padT + $i * ($row_h + 8) + 4;
        $color = $val >= 60 ? '#16a34a' : ($val >= 40 ? '#3b82f6' : ($val >= 25 ? '#f59e0b' : '#ef4444'));
        $lbl   = htmlspecialchars(mb_strimwidth($labels[$i] ?? '', 0, 22, '…'));

        // Label
        $svg .= "  <text x=\"" . ($padL - 8) . "\" y=\"" . ($y + $row_h / 2 + 4) . "\" text-anchor=\"end\" font-size=\"10\" fill=\"#1e293b\" font-weight=\"600\">{$lbl}</text>\n";
        // Bar background
        $svg .= "  <rect x=\"{$padL}\" y=\"{$y}\" width=\"{$aW}\" height=\"{$row_h}\" rx=\"4\" fill=\"#f8fafc\"/>\n";
        // Bar fill
        $svg .= "  <rect x=\"{$padL}\" y=\"{$y}\" width=\"{$bW}\" height=\"{$row_h}\" rx=\"4\" fill=\"{$color}\" opacity=\"0.9\"/>\n";
        // Value label
        $tx   = $padL + $bW + 6;
        $svg .= "  <text x=\"{$tx}\" y=\"" . ($y + $row_h / 2 + 4) . "\" font-size=\"10\" font-weight=\"700\" fill=\"#374151\">" . round($val, 1) . $suffix . "</text>\n";
    }

    $svg .= "</svg>\n";
    return $svg;
}

/**
 * ── Line Chart (single or multi-series) ──────────────────────
 * @param string[] $labels
 * @param array[]  $datasets  Each: ['label'=>str, 'values'=>[float,...], 'color'=>'#hex']
 * @param array    $opts      width, height, y_suffix, show_legend, show_fill
 */
function chart_line(array $labels, array $datasets, array $opts = []): string {
    if (empty($datasets) || empty($labels)) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $W         = (int)($opts['width']   ?? 520);
    $H         = (int)($opts['height']  ?? 240);
    $suffix    = $opts['y_suffix']      ?? '%';
    $show_leg  = $opts['show_legend']   ?? (count($datasets) > 1);
    $show_fill = $opts['show_fill']     ?? true;

    $padL = 44; $padR = 20; $padT = 24; $padB = 44;
    if ($show_leg) $padB += 24;
    $aW = $W - $padL - $padR;
    $aH = $H - $padT - $padB;

    // Find global max / min
    $all_vals = array_merge(...array_column($datasets, 'values'));
    $max_val  = max(array_merge([1], $all_vals));
    $min_val  = 0;
    $range    = $max_val - $min_val ?: 1;

    $default_colors = ['#3b82f6','#16a34a','#f59e0b','#ef4444','#7c3aed','#0d9488'];
    $n = count($labels);
    $rotate = ($n > 5) || !empty($opts['rotate_labels']);

    $padL = 44; $padR = 20; $padT = 24; 
    $padB = $rotate ? 62 : 44;
    if ($show_leg) $padB += 20;

    if ($rotate && $H < 260) {
        $H = 260;
    }

    $aW = $W - $padL - $padR;
    $aH = $H - $padT - $padB;

    $svg  = "<svg viewBox=\"0 0 {$W} {$H}\" width=\"100%\" role=\"img\" aria-label=\"Line chart\">\n";

    // Grid
    for ($k = 0; $k <= 5; $k++) {
        $val  = $min_val + ($range / 5) * $k;
        $y    = $padT + $aH - (($val - $min_val) / $range) * $aH;
        $svg .= "  <line x1=\"{$padL}\" y1=\"{$y}\" x2=\"" . ($W - $padR) . "\" y2=\"{$y}\" stroke=\"#f1f5f9\" stroke-width=\"1.5\"/>\n";
        $svg .= "  <text x=\"" . ($padL - 6) . "\" y=\"" . ($y + 4) . "\" text-anchor=\"end\" font-size=\"9\" fill=\"#94a3b8\">" . round($val) . $suffix . "</text>\n";
    }

    // X-axis baseline
    $by = $padT + $aH;
    $svg .= "  <line x1=\"{$padL}\" y1=\"{$by}\" x2=\"" . ($W - $padR) . "\" y2=\"{$by}\" stroke=\"#e2e8f0\" stroke-width=\"1\"/>\n";

    foreach ($datasets as $di => $ds) {
        $vals  = $ds['values'] ?? [];
        $color = $ds['color']  ?? $default_colors[$di % count($default_colors)];
        $m     = count($vals);
        if ($m < 1) continue;

        // Compute points
        $pts = [];
        for ($i = 0; $i < $m; $i++) {
            $px    = $padL + ($m === 1 ? $aW / 2 : ($i / ($m - 1)) * $aW);
            $py    = $padT + $aH - (((float)$vals[$i] - $min_val) / $range) * $aH;
            $pts[] = [$px, $py];
        }

        $point_str = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));

        // Fill polygon
        if ($show_fill && count($pts) > 1) {
            $last_x = $pts[count($pts)-1][0];
            $fill_str = $point_str . " {$last_x},{$by} {$padL},{$by}";
            $svg .= "  <polygon points=\"{$fill_str}\" fill=\"{$color}\" opacity=\"0.07\"/>\n";
        }

        // Line
        $svg .= "  <polyline points=\"{$point_str}\" fill=\"none\" stroke=\"{$color}\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>\n";

        // Points & values
        foreach ($pts as $i => $p) {
            $svg .= "  <circle cx=\"{$p[0]}\" cy=\"{$p[1]}\" r=\"4\" fill=\"{$color}\" stroke=\"#fff\" stroke-width=\"2\"/>\n";
            $val_label = round((float)($vals[$i] ?? 0), 1) . $suffix;
            $font_sz = $n > 8 ? "7.5" : "8.5";
            $svg .= "  <text x=\"{$p[0]}\" y=\"" . ($p[1] - 8) . "\" text-anchor=\"middle\" font-size=\"{$font_sz}\" font-weight=\"700\" fill=\"#374151\">{$val_label}</text>\n";
        }
    }

    // X-axis labels
    if ($rotate) {
        for ($i = 0; $i < $n; $i++) {
            $px  = $padL + ($n === 1 ? $aW / 2 : ($i / ($n - 1)) * $aW);
            $raw_lbl = $labels[$i] ?? '';
            $lbl = htmlspecialchars(mb_strimwidth($raw_lbl, 0, 18, '…'));
            $svg .= "  <text x=\"{$px}\" y=\"" . ($by + 14) . "\" transform=\"rotate(-35, {$px}, " . ($by + 14) . ")\" text-anchor=\"end\" font-size=\"8.5\" font-weight=\"600\" fill=\"#475569\">{$lbl}</text>\n";
        }
    } else {
        for ($i = 0; $i < $n; $i++) {
            $px  = $padL + ($n === 1 ? $aW / 2 : ($i / ($n - 1)) * $aW);
            $lbl = htmlspecialchars(mb_strimwidth($labels[$i] ?? '', 0, 14, '…'));
            $svg .= "  <text x=\"{$px}\" y=\"" . ($by + 16) . "\" text-anchor=\"middle\" font-size=\"9\" fill=\"#64748b\">{$lbl}</text>\n";
        }
    }

    // Legend
    if ($show_leg) {
        $lx  = $padL;
        $ly  = $H - 16;
        foreach ($datasets as $di => $ds) {
            $color = $ds['color'] ?? $default_colors[$di % count($default_colors)];
            $lbl   = htmlspecialchars($ds['label'] ?? "Series " . ($di + 1));
            $svg .= "  <rect x=\"{$lx}\" y=\"" . ($ly - 8) . "\" width=\"14\" height=\"8\" rx=\"2\" fill=\"{$color}\"/>\n";
            $svg .= "  <text x=\"" . ($lx + 18) . "\" y=\"{$ly}\" font-size=\"10\" fill=\"#374151\">{$lbl}</text>\n";
            $lx  += strlen($lbl) * 7 + 32;
        }
    }

    $svg .= "</svg>\n";
    return $svg;
}

/**
 * ── Donut Chart ───────────────────────────────────────────────
 * Built with SVG stroke-dasharray on a circle.
 * @param array[] $segments  Each: ['label'=>str, 'value'=>float, 'color'=>'#hex']
 * @param array   $opts      size, center_text, center_subtext, show_legend
 */
function chart_donut(array $segments, array $opts = []): string {
    if (empty($segments)) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $size       = (int)($opts['size']          ?? 200);
    $center_txt = $opts['center_text']          ?? '';
    $center_sub = $opts['center_subtext']       ?? '';
    $show_leg   = $opts['show_legend']          ?? true;
    $cx = $cy   = $size / 2;
    $r          = $size * 0.35;
    $circ       = 2 * M_PI * $r;
    $stroke_w   = $size * 0.14;

    $total = array_sum(array_column($segments, 'value'));
    if ($total <= 0) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $svg     = "<svg viewBox=\"0 0 {$size} {$size}\" width=\"{$size}\" height=\"{$size}\" style=\"display:block;margin:auto;\" role=\"img\" aria-label=\"Donut chart\">\n";
    $svg    .= "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"none\" stroke=\"#f1f5f9\" stroke-width=\"{$stroke_w}\"/>\n";

    $offset  = 0;
    foreach ($segments as $seg) {
        $val   = (float)($seg['value'] ?? 0);
        $dash  = ($val / $total) * $circ;
        $gap   = $circ - $dash;
        $color = $seg['color'] ?? '#3b82f6';
        $svg  .= "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"none\" stroke=\"{$color}\" stroke-width=\"{$stroke_w}\"\n";
        $svg  .= "    stroke-dasharray=\"{$dash} {$gap}\"\n";
        $svg  .= "    stroke-dashoffset=\"" . (-$offset + $circ / 4) . "\"\n";
        $svg  .= "    stroke-linecap=\"butt\"/>\n";
        $offset += $dash;
    }

    // Center text
    if ($center_txt !== '') {
        $svg .= "  <text x=\"{$cx}\" y=\"{$cy}\" text-anchor=\"middle\" dominant-baseline=\"middle\" font-size=\"" . ($size * 0.11) . "\" font-weight=\"700\" fill=\"#0f172a\">{$center_txt}</text>\n";
        if ($center_sub !== '') {
            $sy   = $cy + $size * 0.1;
            $svg .= "  <text x=\"{$cx}\" y=\"{$sy}\" text-anchor=\"middle\" dominant-baseline=\"middle\" font-size=\"" . ($size * 0.065) . "\" fill=\"#64748b\">{$center_sub}</text>\n";
        }
    }

    $svg .= "</svg>\n";

    // Legend
    if ($show_leg) {
        $svg .= "<div style=\"display:flex;flex-wrap:wrap;gap:10px 18px;justify-content:center;margin-top:10px;\">\n";
        foreach ($segments as $seg) {
            $color = $seg['color'] ?? '#3b82f6';
            $lbl   = htmlspecialchars($seg['label'] ?? '');
            $val   = round((float)($seg['value'] ?? 0), 1);
            $pct   = $total > 0 ? round(($val / $total) * 100, 1) : 0;
            $svg  .= "  <div style=\"display:flex;align-items:center;gap:6px;font-size:0.78rem;color:#374151;\">";
            $svg  .= "<span style=\"display:inline-block;width:12px;height:12px;border-radius:3px;background:{$color}\"></span>";
            $svg  .= "<span>{$lbl}: <strong>{$pct}%</strong></span></div>\n";
        }
        $svg .= "</div>\n";
    }

    return $svg;
}

/**
 * ── Grade Distribution Histogram ─────────────────────────────
 * @param array[] $grade_data  From stats_grade_distribution()
 * @param array   $opts        width, height
 */
function chart_grade_histogram(array $grade_data, array $opts = []): string {
    if (empty($grade_data)) return '<p style="color:var(--text-muted);text-align:center;padding:40px 0;">No data available.</p>';

    $W   = (int)($opts['width']  ?? 520);
    $H   = (int)($opts['height'] ?? 220);
    $padL = 44; $padR = 16; $padT = 22; $padB = 44;
    $aW  = $W - $padL - $padR;
    $aH  = $H - $padT - $padB;
    $n   = count($grade_data);

    $max_pct = max(array_column($grade_data, 'pct') + [1]);
    $bar_w   = ($aW / $n) * 0.65;
    $gap     = ($aW / $n) * 0.35;

    // Grade colors
    $grade_colors = [
        '1'=>'#15803d','2'=>'#16a34a','3'=>'#4ade80',
        '4'=>'#3b82f6','5'=>'#60a5fa',
        '6'=>'#f59e0b','7'=>'#fbbf24',
        '8'=>'#ef4444','9'=>'#b91c1c',
    ];

    $svg  = "<svg viewBox=\"0 0 {$W} {$H}\" width=\"100%\" role=\"img\" aria-label=\"Grade distribution\">\n";

    // Grid
    for ($k = 0; $k <= 4; $k++) {
        $val  = ($max_pct / 4) * $k;
        $y    = $padT + $aH - ($k / 4) * $aH;
        $svg .= "  <line x1=\"{$padL}\" y1=\"{$y}\" x2=\"" . ($W - $padR) . "\" y2=\"{$y}\" stroke=\"#f1f5f9\" stroke-width=\"1.5\"/>\n";
        $svg .= "  <text x=\"" . ($padL - 6) . "\" y=\"" . ($y + 4) . "\" text-anchor=\"end\" font-size=\"9\" fill=\"#94a3b8\">" . round($val) . "%</text>\n";
    }

    $by = $padT + $aH;
    $svg .= "  <line x1=\"{$padL}\" y1=\"{$by}\" x2=\"" . ($W - $padR) . "\" y2=\"{$by}\" stroke=\"#e2e8f0\" stroke-width=\"1\"/>\n";

    foreach ($grade_data as $i => $gd) {
        $pct   = (float)$gd['pct'];
        $ratio = $max_pct > 0 ? min(1, $pct / $max_pct) : 0;
        $bH    = max(2, $ratio * $aH);
        $x     = $padL + $i * ($bar_w + $gap) + $gap / 2;
        $y     = $by - $bH;
        $color = $grade_colors[$gd['grade']] ?? '#64748b';
        $grade_lbl = 'G' . $gd['grade'];
        $count_lbl = $gd['count'];

        $svg .= "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$bar_w}\" height=\"{$bH}\" rx=\"4\" fill=\"{$color}\" opacity=\"0.85\"/>\n";
        if ($pct > 0) {
            $svg .= "  <text x=\"" . ($x + $bar_w / 2) . "\" y=\"" . ($y - 5) . "\" text-anchor=\"middle\" font-size=\"8.5\" font-weight=\"700\" fill=\"#374151\">{$pct}%</text>\n";
        }
        $svg .= "  <text x=\"" . ($x + $bar_w / 2) . "\" y=\"" . ($by + 14) . "\" text-anchor=\"middle\" font-size=\"9\" fill=\"#64748b\">{$grade_lbl}</text>\n";
        $svg .= "  <text x=\"" . ($x + $bar_w / 2) . "\" y=\"" . ($by + 26) . "\" text-anchor=\"middle\" font-size=\"8\" fill=\"#94a3b8\">({$count_lbl})</text>\n";
    }

    $svg .= "</svg>\n";
    return $svg;
}

/**
 * ── Sparkline ─────────────────────────────────────────────────
 * Tiny inline trend line. Returns an SVG string.
 * @param float[] $values
 * @param array   $opts  width, height, color
 */
function chart_sparkline(array $values, array $opts = []): string {
    $n     = count($values);
    if ($n < 2) return '';
    $W     = (int)($opts['width']  ?? 80);
    $H     = (int)($opts['height'] ?? 28);
    $color = $opts['color']        ?? '#3b82f6';
    $pad   = 3;
    $aW    = $W - $pad * 2;
    $aH    = $H - $pad * 2;

    $max   = max($values) ?: 1;
    $min   = min($values);
    $range = $max - $min ?: 1;

    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $x      = $pad + ($i / ($n - 1)) * $aW;
        $y      = $pad + $aH - (($values[$i] - $min) / $range) * $aH;
        $pts[]  = "$x,$y";
    }
    $point_str = implode(' ', $pts);

    // Determine trend color
    $trend_color = ($values[$n-1] >= $values[0]) ? '#16a34a' : '#ef4444';

    return "<svg viewBox=\"0 0 {$W} {$H}\" width=\"{$W}\" height=\"{$H}\" style=\"display:inline-block;vertical-align:middle;\">"
         . "<polyline points=\"{$point_str}\" fill=\"none\" stroke=\"{$trend_color}\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>"
         . "<circle cx=\"{$pts[$n-1]}\" r=\"2.5\" fill=\"{$trend_color}\"/>"
         . "</svg>";
}
