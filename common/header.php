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

    justify-content:space-between;

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

            <img
                src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo1.png"
                alt="NED-SEMS">

            <div class="logo-text">
                <h1>NED-SEMS</h1>
                <p>Northern Education Division</p>
            </div>

        </div>

        <nav class="landing-nav">

            <a href="#about">About</a>
            <a href="#mock">Mock Exams</a>
            <a href="#features">Who It's For</a>

            <a href="/NED-SEMs FINAL YEAR PROJECT/login.php"
               class="quick-btn">
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