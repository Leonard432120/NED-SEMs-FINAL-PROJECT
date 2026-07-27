<?php
/**
 * assignment_access_guard.php
 * ─────────────────────────────────────────────────────────────────
 * Enforces the scheduled access window for item_writers/moderators.
 *
 * Include this at the TOP of any teacher page that requires a valid
 * active assignment:
 *
 *   require_once __DIR__ . '/../common/assignment_access_guard.php';
 *   enforce_assignment_access($conn, $exam_id, $subject_id, $role);
 *
 * Parameters
 *   $conn       — active mysqli connection
 *   $exam_id    — exam being accessed
 *   $subject_id — subject being accessed (0 = any)
 *   $role       — 'item_writer' | 'moderator'
 *
 * Behaviour
 *   ✓ Access within window   → continues silently
 *   ✗ No assignment found    → renders locked screen + exits
 *   ✗ Before access_from     → renders "Not yet open" screen + exits
 *   ✗ After  access_until    → renders "Window closed" screen + exits
 *   ✗ admin role             → always passes (admin can view anything)
 * ─────────────────────────────────────────────────────────────────
 */

function enforce_assignment_access(
    mysqli $conn,
    int    $exam_id,
    int    $subject_id,
    string $role
): void {
    /* Admins bypass all access checks */
    if (($_SESSION['role'] ?? '') === 'admin') return;

    $teacher_id = (int)($_SESSION['user_id'] ?? 0);
    $now        = time();

    /* Build query — subject_id = 0 means any subject for that exam */
    if ($subject_id > 0) {
        $stmt = $conn->prepare("
            SELECT access_from, access_until, status
            FROM   subject_assignments
            WHERE  teacher_id  = ?
              AND  exam_id     = ?
              AND  subject_id  = ?
              AND  role        = ?
              AND  status      = 'assigned'
            ORDER  BY assigned_at DESC
            LIMIT  1
        ");
        $stmt->bind_param("iiis", $teacher_id, $exam_id, $subject_id, $role);
    } else {
        $stmt = $conn->prepare("
            SELECT access_from, access_until, status
            FROM   subject_assignments
            WHERE  teacher_id  = ?
              AND  exam_id     = ?
              AND  role        = ?
              AND  status      = 'assigned'
            ORDER  BY assigned_at DESC
            LIMIT  1
        ");
        $stmt->bind_param("iis", $teacher_id, $exam_id, $role);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        _render_access_denied(
            'No Assignment Found',
            'You do not have an active assignment for this exam and role. Contact your administrator.',
            'no-assignment'
        );
    }

    $from  = $row['access_from']  ? strtotime($row['access_from'])  : null;
    $until = $row['access_until'] ? strtotime($row['access_until']) : null;

    if ($from && $now < $from) {
        $opens = date('d M Y \a\t H:i', $from);
        _render_access_denied(
            'Access Window Not Yet Open',
            "Your access window for this assignment opens on <strong>{$opens}</strong>. Please return then.",
            'not-yet'
        );
    }

    if ($until && $now > $until) {
        $closed = date('d M Y \a\t H:i', $until);
        _render_access_denied(
            'Access Window Closed',
            "Your access window for this assignment ended on <strong>{$closed}</strong>. All access has been automatically locked. Contact your administrator if you need an extension.",
            'expired'
        );
    }
    /* ✓ Within window — allow through */
}

function _render_access_denied(string $title, string $body, string $type): never
{
    $icon = match($type) {
        'not-yet' => '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'expired' => '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        default   => '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
    };
    $colour = match($type) {
        'not-yet'   => '#f59e0b',
        'expired'   => '#dc2626',
        default     => '#6b7280',
    };
    /* Log access attempt */
    error_log("[NED-SEMS] Blocked access: user={$_SESSION['user_id']} type={$type} url={$_SERVER['REQUEST_URI']}");
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access Restricted — NED-SEMS</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', system-ui, sans-serif; background: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .card { background: #1e293b; border-radius: 16px; padding: 48px 40px; max-width: 480px; width: 90%; text-align: center; border: 1px solid #334155; }
  .icon { font-size: 3.5rem; margin-bottom: 16px; }
  h1 { color: #f1f5f9; font-size: 1.35rem; margin-bottom: 10px; }
  p { color: #94a3b8; font-size: 0.92rem; line-height: 1.7; }
  p strong { color: #e2e8f0; }
  .badge { display: inline-block; margin: 20px auto 0; padding: 7px 18px; border-radius: 999px; background: {$colour}22; color: {$colour}; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
  .back { display: inline-block; margin-top: 28px; padding: 10px 22px; background: #334155; color: #cbd5e1; text-decoration: none; border-radius: 8px; font-size: 0.85rem; transition: background .2s; }
  .back:hover { background: #475569; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">{$icon}</div>
  <h1>{$title}</h1>
  <p>{$body}</p>
  <div class="badge">Exam Security System</div>
  <br>
  <a href="javascript:history.back()" class="back">← Go Back</a>
</div>
</body>
</html>
HTML;
    exit();
}
