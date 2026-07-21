<!-- ================= TEACHER SIDEBAR ================= -->
<div class="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="<?= BASE_URL ?>/assets/images/logo1.png" 
             alt="NED-SEMS Logo" 
             width="160">
    </div>

    <!-- Dashboard -->
    <a href="<?= BASE_URL ?>/teacher/dashboard.php" class="sidebar-link">
        Dashboard
    </a>

    <!-- Examination Tasks -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="taskMenu" role="button" aria-expanded="false" tabindex="0">
            Examination Tasks
        </span>
        <div class="sidebar-sub" id="taskMenu">
            <a href="<?= BASE_URL ?>/teacher/assigned_exams.php" class="sidebar-link">Item Writer Tasks</a>
            <a href="<?= BASE_URL ?>/teacher/moderation_exams.php" class="sidebar-link">Moderator Tasks</a>
            <a href="<?= BASE_URL ?>/teacher/my_submissions.php" class="sidebar-link">My Submissions</a>
        </div>
    </div>

    <!-- Marks & Results -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="marksMenu" role="button" aria-expanded="false" tabindex="0">
            Marks & Results
        </span>
        <div class="sidebar-sub" id="marksMenu">
            <a href="<?= BASE_URL ?>/teacher/marks_activities.php" class="sidebar-link">Marks Activities</a>
            <a href="<?= BASE_URL ?>/teacher/view_results.php" class="sidebar-link">View Results</a>
            <a href="<?= BASE_URL ?>/teacher/analytics.php" class="sidebar-link">Analytics</a>
        </div>
    </div>

    <!-- Announcements -->
    <a href="<?= BASE_URL ?>/common/announcements.php" class="sidebar-link">
        Announcements
    </a>

    <hr>

    <!-- Account -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="profileMenu" role="button" aria-expanded="false" tabindex="0">
            Account
        </span>
        <div class="sidebar-sub" id="profileMenu">
            <a href="<?= BASE_URL ?>/common/update_profile.php" class="sidebar-link">Update Profile</a>
            <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link">Logout</a>
        </div>
    </div>

</div>