<!-- ================= EXAMINATION OFFICER SIDEBAR ================= -->
<div class="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" 
             alt="NED-SEMS Logo" 
             width="160">
    </div>

    <!-- Dashboard -->
    <a href="<?= BASE_URL ?>/examination_officer/dashboard.php" class="sidebar-link">
        Dashboard
    </a>

    <!-- Students -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="studentsMenu" role="button" aria-expanded="false" tabindex="0">
            Students
        </span>
        <div class="sidebar-sub" id="studentsMenu">
            <a href="<?= BASE_URL ?>/examination_officer/manage_students.php" class="sidebar-link">Manage Students</a>
            <a href="<?= BASE_URL ?>/examination_officer/manage_exam_candidates.php" class="sidebar-link">Exam Candidates</a>
        </div>
    </div>

    <!-- Exam Management -->

    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="examMenu" role="button" aria-expanded="false" tabindex="0">
            Exam Management
        </span>
        <div class="sidebar-sub" id="examMenu">
            <a href="<?= BASE_URL ?>/examination_officer/exams.php" class="sidebar-link">All Exams</a>
            <a href="<?= BASE_URL ?>/examination_officer/exams.php?status=under_moderation" class="sidebar-link">Pending Exams</a>
            <a href="<?= BASE_URL ?>/examination_officer/schedule.php" class="sidebar-link">Exam Deadlines</a>
            <a href="<?= BASE_URL ?>/examination_officer/control.php" class="sidebar-link">Exam Control Panel</a>
            <a href="<?= BASE_URL ?>/examination_officer/marks_management.php" class="sidebar-link">Marks Mgmt & Unlocking</a>
            <a href="<?= BASE_URL ?>/examination_officer/results.php" class="sidebar-link">Results Management</a>
        </div>
    </div>

    <!-- Results -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="resultsMenu" role="button" aria-expanded="false" tabindex="0">
            Results & Reports
        </span>
        <div class="sidebar-sub" id="resultsMenu">
            <a href="<?= BASE_URL ?>/examination_officer/reports.php" class="sidebar-link">Generate Reports</a>
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