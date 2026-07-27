<?php

/* ==========================================
   LANDING PAGE HEADER
========================================== */

if (isset($landing_page) && $landing_page === true):
?>

<style>
/* ==========================================
   NED-SEMS LANDING HEADER
   Forest + Ochre Theme
========================================== */

.header.landing-header {
    background: white;
    border-bottom: 3px solid #3e2ce0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.header.landing-header .header-inner {
    width:100%;
    padding:18px 35px;
    display:flex;
    align-items:center;
    justify-space-between;
}

/* LOGO */
.logo-section {
    display:flex;
    align-items:center;
    gap:15px;
}

.logo-section img {
    height:55px;
    width:auto;
}

.logo-text h1 {
    margin:0;
    font-family:'Space Grotesk',sans-serif;
    font-size:1.45rem;
    font-weight:700;
    color:black;
}

.logo-text p {
    margin-top:5px;
    font-size:0.75rem;
    color:#E0932C;
    letter-spacing:1.5px;
    text-transform:uppercase;
    font-weight:600;
}

/* NAVIGATION */
.landing-nav {
    display:flex;
    align-items:center;
    gap:35px;
}

.landing-nav a {
    color:#FAF7F2;
    font-family:'Space Grotesk',sans-serif;
    font-size:15px;
    font-weight:500;
    text-decoration:none;
    transition:0.3s;
}

.landing-nav a:hover {
    color:#E0932C;
}

/* LOGIN BUTTON */
.landing-nav .quick-btn {
    background:#E0932C;
    color:#1B1B1B;
    padding:12px 26px;
    border-radius:6px;
    font-weight:700;
}

.landing-nav .quick-btn:hover {
    background:#F2B84B;
    color:#000;
    transform:translateY(-2px);
}

/* MOBILE */
@media(max-width:768px){
.header-inner{
    flex-direction:column;
}
.landing-nav{
    flex-wrap:wrap;
    justify-content:center;
}
}
</style>

<header class="header landing-header">
    <div class="header-inner">
        <div class="logo-section">
            <img src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo1.png" alt="NED-SEMS">
            <div class="logo-text">
                <h1>NED-SEMS</h1>
                <p>Northern Education Division</p>
            </div>
        </div>

        <nav class="landing-nav">
            <a href="#about">About</a>
            <a href="#mock">Mock Exams</a>
            <a href="#features">Who It's For</a>
            <a href="/NED-SEMs FINAL YEAR PROJECT/login.php" class="quick-btn">
                Login to Portal
            </a>
        </nav>
    </div>
</header>

<?php
return;
endif;

/* ================= DASHBOARD HEADER ================= */

if (!isset($portal_title)) {
    $portal_titles = [
        'admin'               => 'NED-SEMS | EDM Control Center',
        'teacher'             => 'NED-SEMS | Teacher Portal',
        'headteacher'         => 'NED-SEMS | Headteacher Portal',
        'examination_officer' => 'NED-SEMS | Examination Officer Portal',
    ];

    $portal_title = $portal_titles[$_SESSION['role'] ?? 'admin'] ?? 'NED-SEMS';
}

$user_display_name = $_SESSION['name'] ?? '';

/* ── Fetch recent notifications / announcements for header ── */
$recent_announcements = [];
try {
    require_once __DIR__ . '/../config/db.php';
    $hdr_conn = null;
    if (isset($conn) && ($conn instanceof mysqli)) {
        try {
            if ($conn->ping()) {
                $hdr_conn = $conn;
            }
        } catch (Throwable $pe) {
            $hdr_conn = null;
        }
    }
    if (!$hdr_conn) {
        $hdr_conn = get_db_connection();
    }

    if ($hdr_conn) {
        $hdr_user_id   = (int)($_SESSION['user_id'] ?? 0);
        $hdr_school_id = (int)($_SESSION['school_id'] ?? 0);

        // Count for badge (excludes self & restricts school notices to same-school staff)
        $count_res = $hdr_conn->query("
            SELECT COUNT(*) AS total 
            FROM announcements a
            WHERE a.published_by != {$hdr_user_id}
              AND (
                  a.school_id IS NULL 
                  OR (a.school_id = {$hdr_school_id} AND {$hdr_school_id} > 0)
              )
        ");
        if ($count_res) {
            $notif_count = (int)$count_res->fetch_assoc()['total'];
        }

        // Fetch top 5 recent notifications for dropdown
        $ann_res = $hdr_conn->query("
            SELECT a.id, a.title, a.content, a.created_at,
                   COALESCE(u.name, 'Administrator') AS author,
                   COALESCE(u.role, 'admin') AS author_role
            FROM announcements a
            LEFT JOIN users u ON u.user_id = a.published_by
            WHERE a.published_by != {$hdr_user_id}
              AND (
                  a.school_id IS NULL 
                  OR (a.school_id = {$hdr_school_id} AND {$hdr_school_id} > 0)
              )
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT 5
        ");

        if ($ann_res) {
            while ($row = $ann_res->fetch_assoc()) {
                $recent_announcements[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    // Fail gracefully if DB connection is unavailable
}
?>

<style>
/* Notification Icon & Dropdown Styles */
.header-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
.notif-container {
    position: relative;
    display: inline-block;
    overflow: visible !important;
}
.notif-bell-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.35);
    padding: 7px;
    border-radius: 8px;
    cursor: pointer;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: visible !important;
    transition: background 0.2s, color 0.2s, transform 0.15s;
}
.notif-bell-btn svg {
    stroke: #ffffff !important;
}
.notif-bell-btn:hover {
    background: rgba(255, 255, 255, 0.35);
    color: #ffffff;
    transform: translateY(-1px);
}
.notif-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 800;
    height: 18px;
    min-width: 18px;
    padding: 0 5px;
    border-radius: 99px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    line-height: 1;
    z-index: 10;
}
.notif-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 340px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
    z-index: 1000;
    display: none;
    overflow: hidden;
}
.notif-dropdown.show {
    display: block;
    animation: notifFadeIn 0.2s ease-out;
}
@keyframes notifFadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.notif-header {
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.notif-header h4 {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    color: #0f172a;
}
.notif-count-pill {
    font-size: 0.72rem;
    background: #e2e8f0;
    color: #475569;
    padding: 2px 8px;
    border-radius: 99px;
    font-weight: 600;
}
.notif-list {
    max-height: 320px;
    overflow-y: auto;
}
.notif-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    display: block;
    text-decoration: none;
    transition: background 0.15s;
}
.notif-item:last-child {
    border-bottom: none;
}
.notif-item:hover {
    background: #f8fafc;
}
.notif-title {
    font-size: 0.83rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
    line-height: 1.3;
}
.notif-meta {
    font-size: 0.72rem;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.notif-footer {
    padding: 10px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}
.notif-footer a {
    font-size: 0.8rem;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
}
.notif-footer a:hover {
    text-decoration: underline;
}
.notif-empty {
    padding: 24px 16px;
    text-align: center;
    color: #64748b;
    font-size: 0.82rem;
}
</style>

<header class="header">

    <div class="header-left">
        <button
            class="sidebar-toggle"
            id="sidebarToggle"
            type="button"
            aria-label="Toggle sidebar">
            ☰
        </button>

        <span class="dashboard-title">
            <?= htmlspecialchars($portal_title) ?>
        </span>
    </div>

    <div class="header-right">

        <!-- NOTIFICATION ICON & DROPDOWN -->
        <div class="notif-container" id="notifContainer">
            <button class="notif-bell-btn" id="notifBellBtn" type="button" aria-label="Notifications" title="Notifications">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($notif_count > 0): ?>
                    <span class="notif-badge"><?= $notif_count ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <h4>Notifications</h4>
                    <span class="notif-count-pill"><?= $notif_count ?> recent</span>
                </div>

                <div class="notif-list">
                    <?php if (empty($recent_announcements)): ?>
                        <div class="notif-empty">
                            No new notifications at this time.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_announcements as $ann): ?>
                            <a href="<?= BASE_URL ?>/common/announcements.php" class="notif-item">
                                <div class="notif-title"><?= htmlspecialchars($ann['title']) ?></div>
                                <div class="notif-meta">
                                    <span><?= htmlspecialchars($ann['author']) ?></span>
                                    <span><?= date('d M Y', strtotime($ann['created_at'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notif-footer">
                    <a href="<?= BASE_URL ?>/common/announcements.php">View All Announcements &rarr;</a>
                </div>
            </div>
        </div>

        <!-- PROFILE -->
        <div class="profile">
            <?php if ($user_display_name !== ''): ?>
                <span class="profile-name">
                    <?= htmlspecialchars($user_display_name) ?>
                </span>
            <?php endif; ?>

            <?php
            $profile_picture =
                !empty($_SESSION['profile_image'])
                ? BASE_URL . "/uploads/profiles/" . $_SESSION['profile_image']
                : BASE_URL . "/static/images/user.png";
            ?>

            <img src="<?= $profile_picture ?>" alt="User profile">

            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
                Logout
            </a>
        </div>

    </div>

</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var bellBtn = document.getElementById('notifBellBtn');
    var dropdown = document.getElementById('notifDropdown');
    var container = document.getElementById('notifContainer');

    if (bellBtn && dropdown) {
        bellBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (container && !container.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }
});
</script>