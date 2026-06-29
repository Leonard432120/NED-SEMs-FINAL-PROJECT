<!-- ================= HEADTEACHER SIDEBAR ================= -->
<div class="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" 
             alt="NED-SEMS Logo" 
             width="160">
    </div>

    <!-- Dashboard -->
    <a href="<?= BASE_URL ?>/headteacher/dashboard.php" class="sidebar-link">
        Dashboard
    </a>

    <!-- Staff Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="staffMenu" role="button" aria-expanded="false" tabindex="0">
            Staff Management
        </span>
        <div class="sidebar-sub" id="staffMenu">
            <a href="<?= BASE_URL ?>/headteacher/manage_teachers.php" class="sidebar-link">Teachers</a>
            <a href="<?= BASE_URL ?>/headteacher/manage_examination_officers.php" class="sidebar-link">Examination Officers</a>
        </div>
    </div>

    <!-- Students & Classes -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="studentMenu" role="button" aria-expanded="false" tabindex="0">
            Students & Classes
        </span>
        <div class="sidebar-sub" id="studentMenu">
            <a href="<?= BASE_URL ?>/headteacher/manage_students.php" class="sidebar-link">Student Management</a>
            <a href="<?= BASE_URL ?>/headteacher/classes.php" class="sidebar-link">Class Overview</a>
            <a href="<?= BASE_URL ?>/headteacher/add_student.php" class="sidebar-link">Add Student</a>
        </div>
    </div>

    <!-- Communication -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="commMenu" role="button" aria-expanded="false" tabindex="0">
            Communication Hub
        </span>
        <div class="sidebar-sub" id="commMenu">
            <a href="<?= BASE_URL ?>/headteacher/forwarding_center.php" class="sidebar-link">Forwarding Center</a>
            <a href="<?= BASE_URL ?>/headteacher/exam_forwarding.php" class="sidebar-link">Forward Exams</a>
            <a href="<?= BASE_URL ?>/headteacher/results_forwarding.php" class="sidebar-link">Forward Results</a>
        </div>
    </div>

    <!-- Marking & Examinations -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="markingMenu" role="button" aria-expanded="false" tabindex="0">
            Marking & Examinations
        </span>
        <div class="sidebar-sub" id="markingMenu">
            <a href="<?= BASE_URL ?>/headteacher/assign_markers.php" class="sidebar-link">Assign Markers</a>
            <a href="<?= BASE_URL ?>/headteacher/marks_management.php" class="sidebar-link">Marks Management</a>
            <a href="<?= BASE_URL ?>/headteacher/marks_reception.php" class="sidebar-link">Marks Reception</a>
            <a href="<?= BASE_URL ?>/headteacher/released_results.php" class="sidebar-link">Released Results</a>
        </div>
    </div>

    <!-- Reports & Analytics -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="perfMenu" role="button" aria-expanded="false" tabindex="0">
            Analytics & Reports
        </span>
        <div class="sidebar-sub" id="perfMenu">
            <a href="<?= BASE_URL ?>/headteacher/performance.php" class="sidebar-link">Performance Analytics</a>
            <a href="<?= BASE_URL ?>/headteacher/reports.php" class="sidebar-link">School Reports</a>
            <a href="<?= BASE_URL ?>/headteacher/predictions.php" class="sidebar-link">AI Performance Predictor</a>
        </div>
    </div>

    <!-- General -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="generalMenu" role="button" aria-expanded="false" tabindex="0">
            General
        </span>
        <div class="sidebar-sub" id="generalMenu">
            <a href="<?= BASE_URL ?>/headteacher/announcements.php" class="sidebar-link">Announcements</a>
            <a href="<?= BASE_URL ?>/headteacher/documents.php" class="sidebar-link">Documents & Timetables</a>
        </div>
    </div>

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