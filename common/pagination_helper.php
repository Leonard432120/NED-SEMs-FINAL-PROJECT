<?php

/**
 * ==========================================
 * NED-SEMS PAGINATION HELPER (REUSABLE)
 * ==========================================
 * Works across all modules:
 * - Users
 * - Exams
 * - Results
 * - Schools
 * - Reports
 * ==========================================
 */

if (!function_exists('paginate')) {

    function paginate($total, $page, $per_page = 5, $max = 5)
    {
        $page = max(1, (int)$page);
        $per_page = max(1, (int)$per_page);

        $total_pages = max(1, ceil($total / $per_page));

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $start = max(1, $page - floor($max / 2));
        $end = min($total_pages, $start + $max - 1);

        if ($end - $start < $max) {
            $start = max(1, $end - $max + 1);
        }

        return [
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'start' => $start,
            'end' => $end,
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages,
            'prev_page' => max(1, $page - 1),
            'next_page' => min($total_pages, $page + 1)
        ];
    }
}


/**
 * ==========================================
 * BUILD QUERY STRING (PRESERVE FILTERS)
 * ==========================================
 * Keeps search filters when changing pages
 */
if (!function_exists('build_query')) {

    function build_query($params = [])
    {
        return http_build_query(array_merge($_GET, $params));
    }
}


/**
 * ==========================================
 * RENDER PAGINATION HTML (OPTIONAL BUT USEFUL)
 * ==========================================
 */
if (!function_exists('render_pagination')) {

    function render_pagination($pagination, $base_url = '')
    {
        $html = '<div class="pagination">';

        // Prev button
        if ($pagination['has_prev']) {
            $query = build_query(['page' => $pagination['prev_page']]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>‹ Prev</a>";
        }

        // First page shortcut
        if ($pagination['start'] > 1) {
            $query = build_query(['page' => 1]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>1</a>";
            $html .= "<span class='dots'>...</span>";
        }

        // Pages
        for ($i = $pagination['start']; $i <= $pagination['end']; $i++) {
            $active = ($i == $pagination['page']) ? 'active' : '';
            $query = build_query(['page' => $i]);

            $html .= "<a class='page-btn {$active}' href='{$base_url}?{$query}'>{$i}</a>";
        }

        // Last page shortcut
        if ($pagination['end'] < $pagination['total_pages']) {
            $query = build_query(['page' => $pagination['total_pages']]);
            $html .= "<span class='dots'>...</span>";
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>{$pagination['total_pages']}</a>";
        }

        // Next button
        if ($pagination['has_next']) {
            $query = build_query(['page' => $pagination['next_page']]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>Next ›</a>";
        }

        $html .= '</div>';

        return $html;
    }
}

/**
 * ==========================================
 * RENDER PAGINATION — MULTI-TAB SAFE
 * ==========================================
 * Same as render_pagination() but uses a custom
 * $page_key so multiple paginators can coexist on
 * one page without overwriting each other's `page`
 * query string parameter.
 *
 * Example:
 *   render_pagination_keyed($pag, 'reports.php', 'page_c')
 */
if (!function_exists('render_pagination_keyed')) {

    function render_pagination_keyed($pagination, $base_url = '', $page_key = 'page')
    {
        $html = '<div class="pagination">';

        if ($pagination['has_prev']) {
            $query = build_query([$page_key => $pagination['prev_page']]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>‹ Prev</a>";
        }

        if ($pagination['start'] > 1) {
            $query = build_query([$page_key => 1]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>1</a>";
            $html .= "<span class='dots'>...</span>";
        }

        for ($i = $pagination['start']; $i <= $pagination['end']; $i++) {
            $active = ($i == $pagination['page']) ? 'active' : '';
            $query  = build_query([$page_key => $i]);
            $html  .= "<a class='page-btn {$active}' href='{$base_url}?{$query}'>{$i}</a>";
        }

        if ($pagination['end'] < $pagination['total_pages']) {
            $query = build_query([$page_key => $pagination['total_pages']]);
            $html .= "<span class='dots'>...</span>";
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>{$pagination['total_pages']}</a>";
        }

        if ($pagination['has_next']) {
            $query = build_query([$page_key => $pagination['next_page']]);
            $html .= "<a class='page-btn' href='{$base_url}?{$query}'>Next ›</a>";
        }

        $html .= '</div>';
        return $html;
    }
}