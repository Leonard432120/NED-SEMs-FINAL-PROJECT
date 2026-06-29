<?php
session_start();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

http_response_code(404);

$role     = $_SESSION['role'] ?? '';
$dash_map = [
    'admin'               => BASE_URL . '/admin/dashboard.php',
    'headteacher'         => BASE_URL . '/headteacher/dashboard.php',
    'examination_officer' => BASE_URL . '/examination_officer/dashboard.php',
    'teacher'             => BASE_URL . '/teacher/dashboard.php',
];
$dashboard  = $dash_map[$role] ?? BASE_URL . '/login.php';
$dash_label = $role ? 'Go to Dashboard' : 'Go to Login';

/* Where the user came from — safe fallback */
$referrer   = $_SERVER['HTTP_REFERER'] ?? '';
$show_back  = $referrer !== '' && parse_url($referrer, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Page Not Found | NED-SEMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --dark:       #0f172a;
            --mid:        #1e293b;
            --slate:      #334155;
            --muted:      #64748b;
            --light:      #f1f5f9;
            --border:     #e2e8f0;
            --blue:       #3b82f6;
            --blue-dark:  #1d4ed8;
            --white:      #ffffff;
            --radius:     14px;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
        }

        /* ── animated background grid ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(59,130,246,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,.06) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 0;
            animation: gridDrift 20s linear infinite;
        }
        @keyframes gridDrift {
            from { background-position: 0 0; }
            to   { background-position: 48px 48px; }
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* ── card ── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow:
                0 4px 6px rgba(0,0,0,.04),
                0 20px 60px rgba(0,0,0,.10);
            padding: 56px 52px 48px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── logo ── */
        .logo {
            margin-bottom: 36px;
        }
        .logo img {
            max-width: 130px;
            height: auto;
            opacity: .9;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,.08));
        }

        /* ── big 404 ── */
        .code-wrap {
            position: relative;
            margin-bottom: 28px;
            display: inline-block;
        }
        .code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -6px;
            background: linear-gradient(135deg, var(--dark) 0%, var(--blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .85; }
        }

        /* floating dots around the 404 */
        .dot {
            position: absolute;
            border-radius: 50%;
            background: var(--blue);
            opacity: .18;
            animation: float 4s ease-in-out infinite;
        }
        .dot-1 { width: 14px; height: 14px; top: 6px;  right: -18px; animation-delay: 0s; }
        .dot-2 { width:  8px; height:  8px; top: 44px; left: -12px;  animation-delay: .8s; }
        .dot-3 { width: 10px; height: 10px; bottom: 4px; right: -8px; animation-delay: 1.5s; }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-8px); }
        }

        /* ── divider bar ── */
        .bar {
            width: 56px;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(90deg, var(--blue), var(--blue-dark));
            margin: 0 auto 22px;
        }

        /* ── text ── */
        .title {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
        }
        .subtitle {
            font-size: .95rem;
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 38px;
            max-width: 380px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ── from-page badge ── */
        .referer-hint {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 6px 16px;
            font-size: .8rem;
            font-weight: 600;
            color: var(--blue-dark);
            margin-bottom: 32px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .referer-hint svg {
            flex-shrink: 0;
        }

        /* ── buttons ── */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--dark) 0%, var(--mid) 100%);
            color: var(--white);
            font-family: inherit;
            font-size: .9rem;
            font-weight: 700;
            padding: 13px 32px;
            border-radius: 10px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s, box-shadow .15s, opacity .15s;
            width: 100%;
            max-width: 320px;
            box-shadow: 0 4px 14px rgba(15,23,42,.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15,23,42,.30);
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: transparent;
            color: var(--blue-dark);
            font-family: inherit;
            font-size: .875rem;
            font-weight: 600;
            padding: 11px 32px;
            border-radius: 10px;
            border: 1.5px solid #bfdbfe;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, border-color .15s, transform .15s;
            width: 100%;
            max-width: 320px;
            background: #eff6ff;
        }
        .btn-back:hover {
            background: #dbeafe;
            border-color: var(--blue);
            transform: translateY(-1px);
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--muted);
            font-family: inherit;
            font-size: .825rem;
            font-weight: 600;
            padding: 9px 24px;
            border-radius: 10px;
            border: 1px solid var(--border);
            text-decoration: none;
            cursor: pointer;
            background: var(--white);
            transition: background .15s, color .15s;
            width: 100%;
            max-width: 320px;
        }
        .btn-secondary:hover {
            background: var(--light);
            color: var(--dark);
        }

        /* ── footer note ── */
        .foot {
            margin-top: 36px;
            font-size: .775rem;
            color: #94a3b8;
        }

        @media (max-width: 480px) {
            .card { padding: 40px 24px 36px; }
            .code  { font-size: 6rem; letter-spacing: -4px; }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="card">

        <!-- Logo -->
        <div class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="NED-SEMS">
        </div>

        <!-- 404 number -->
        <div class="code-wrap">
            <div class="code">404</div>
            <span class="dot dot-1"></span>
            <span class="dot dot-2"></span>
            <span class="dot dot-3"></span>
        </div>

        <div class="bar"></div>

        <div class="title">Page Not Found</div>
        <p class="subtitle">
            The page you are looking for does not exist, may have been removed,
            or you do not have permission to view it.
        </p>

        <?php if ($show_back): ?>
        <!-- Where they came from -->
        <div style="display:flex; justify-content:center; margin-bottom:28px;">
            <span class="referer-hint">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                </svg>
                Redirected from: <?= htmlspecialchars(parse_url($referrer, PHP_URL_PATH)) ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="actions">
            <a href="<?= htmlspecialchars($dashboard) ?>" class="btn-primary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
                </svg>
                <?= $dash_label ?>
            </a>

            <?php if ($show_back): ?>
            <a href="<?= htmlspecialchars($referrer) ?>" class="btn-back">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Previous Page
            </a>
            <?php else: ?>
            <a href="javascript:history.back()" class="btn-back">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Go Back
            </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/login.php" class="btn-secondary">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Back to Login
            </a>
        </div>

        <p class="foot">NED-SEMS &mdash; Northern Education Division Smart Examination Management System</p>

    </div>
</div>

</body>
</html>
