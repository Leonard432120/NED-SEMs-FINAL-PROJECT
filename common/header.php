<?php

/* ==========================================
   LANDING PAGE HEADER
========================================== */

if (isset($landing_page) && $landing_page === true):
?>

<style>
/* ==========================================
   LANDING PAGE HEADER
========================================== */

.header.landing-header {
    background: linear-gradient(to right, #1f2937, #334155);
    padding: 14px 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.header.landing-header .header-left,
.header.landing-header .header-right {
    display: flex;
    align-items: center;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 14px;
}

.logo-section img {
    height: 60px;
    width: auto;
    object-fit: contain;
}

.logo-text h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1;
}

.logo-text p {
    margin: 4px 0 0;
    font-size: 0.78rem;
    color: #cbd5e1;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.landing-nav {
    display: flex;
    align-items: center;
    gap: 28px;
}

.landing-nav a {
    color: #e2e8f0;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.25s ease;
    position: relative;
}

.landing-nav a:not(.quick-btn)::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -6px;
    width: 0;
    height: 2px;
    background: #60a5fa;
    transition: width 0.25s ease;
}

.landing-nav a:not(.quick-btn):hover::after {
    width: 100%;
}

.landing-nav a:hover {
    color: #ffffff;
}

.landing-nav .quick-btn {
    background: #3b82f6;
    color: #fff;
    padding: 10px 22px;
    border-radius: 8px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(59,130,246,0.25);
}

.landing-nav .quick-btn:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

@media (max-width: 992px) {

    .header.landing-header {
        flex-direction: column;
        gap: 16px;
    }

    .landing-nav {
        flex-wrap: wrap;
        justify-content: center;
        gap: 16px;
    }
}

@media (max-width: 768px) {

    .logo-section img {
        height: 50px;
    }

    .logo-text h1 {
        font-size: 1.25rem;
    }

    .logo-text p {
        font-size: 0.68rem;
    }

    .landing-nav a {
        font-size: 0.85rem;
    }

    .landing-nav .quick-btn {
        padding: 8px 16px;
    }
}
</style>

<header class="header landing-header">

    <div class="header-left">

        <div class="logo-section">

            <img
                src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo.png"
                alt="NED-SEMS">

            <div class="logo-text">
                <h1>NED-SEMS</h1>
                <p>SMART EXAMINATION SYSTEM</p>
            </div>

        </div>

    </div>

    <div class="header-right">

        <nav class="landing-nav">

            <a href="#features">Features</a>

            <a href="/NED-SEMs FINAL YEAR PROJECT/login.php"
               class="quick-btn">
                Login
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
?>

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

            <img
                src="<?= $profile_picture ?>"
                alt="User profile"
            >

            <a
                href="<?= BASE_URL ?>/logout.php"
                class="logout-btn">

                Logout

            </a>

        </div>

    </div>

</header>
