<?php
/* ════════════════════════════════════════════════════════════════
   NED-SEMS DYNAMIC FORENSIC WATERMARK & ANTI-LEAK SECURITY HELPER
   ────────────────────────────────────────────────────────────────
   Overlays dynamic forensic text containing user identity, IP address,
   and live timestamp across exam paper authoring and moderation views.
   Includes screen-unfocus blurring, right-click blocking, and shortcut
   shielding to prevent camera & screenshot leaks.
   ════════════════════════════════════════════════════════════════ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$wm_user_id   = (int)($_SESSION['user_id'] ?? 0);
$wm_user_name = htmlspecialchars($_SESSION['name'] ?? 'System User');
$wm_user_role = htmlspecialchars($_SESSION['role'] ?? 'User');
$wm_ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if ($wm_ip === '::1') { $wm_ip = '127.0.0.1'; }
$wm_timestamp = date('Y-m-d H:i:s');

$wm_text = "CONFIDENTIAL — {$wm_user_name} (ID: {$wm_user_id}) — IP: {$wm_ip} — {$wm_timestamp}";
?>

<!-- Dynamic Forensic Watermark CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/watermark.css">

<!-- Screen Unfocus Shield Notice -->
<div id="security-unfocus-shield">
    <h3>SECURITY SHIELD ACTIVE</h3>
    <p>Exam material blurred for security while browser window is out of focus. Return focus to this window to resume working.</p>
</div>

<!-- Forensic Watermark Grid Canvas -->
<div class="forensic-watermark-overlay" id="forensicWatermarkOverlay">
    <?php for ($i = 0; $i < 15; $i++): ?>
        <div class="forensic-watermark-unit"><?= $wm_text ?></div>
    <?php endfor; ?>
</div>

<!-- Security Shielding JS -->
<script>
(function() {
    'use strict';

    // Mark body as protected
    document.body.classList.add('protected-exam-page');

    // Auto-blur content when window loses focus (e.g. Snipping tool, tab switch, app switch)
    window.addEventListener('blur', function() {
        document.body.classList.add('screen-unfocused');
    });

    window.addEventListener('focus', function() {
        document.body.classList.remove('screen-unfocused');
    });

    // Disable right-click context menu on protected page
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });

    // Block keyboard shortcuts (PrintScreen, Ctrl+P, Ctrl+S, Ctrl+Shift+I, F12)
    document.addEventListener('keydown', function(e) {
        // PrintScreen
        if (e.key === 'PrintScreen' || e.keyCode === 44) {
            e.preventDefault();
            alert('Screen capture is restricted on examination drafting screens.');
            return false;
        }

        // Ctrl+P / Cmd+P
        if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            alert('Printing is disabled on live examination authoring/moderation interfaces.');
            return false;
        }

        // Ctrl+S / Cmd+S
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            return false;
        }

        // F12 or Ctrl+Shift+I (DevTools)
        if (e.key === 'F12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'i'))) {
            e.preventDefault();
            return false;
        }
    });

})();
</script>
