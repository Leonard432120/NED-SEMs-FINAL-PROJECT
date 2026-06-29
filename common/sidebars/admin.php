<!-- ================= ADMIN SIDEBAR ================= -->
<div class="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" 
             alt="NED-SEMS Logo" 
             width="160">
    </div>

    <!-- Dashboard -->
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link" aria-current="page">
        Dashboard
    </a>

    <!-- User Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="userMenu" role="button" aria-expanded="false" tabindex="0">
            User Management
        </span>
        <div class="sidebar-sub" id="userMenu">
            <a href="<?= BASE_URL ?>/admin/add_user.php" class="sidebar-link">Add User</a>
            <a href="<?= BASE_URL ?>/admin/manage_users.php" class="sidebar-link">Manage Users</a>
        </div>
    </div>

    <!-- School Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="schoolMenu" role="button" aria-expanded="false" tabindex="0">
            School Management
        </span>
        <div class="sidebar-sub" id="schoolMenu">
            <a href="<?= BASE_URL ?>/admin/add_school.php" class="sidebar-link">Add School</a>
            <a href="<?= BASE_URL ?>/admin/manage_schools.php" class="sidebar-link">Manage Schools</a>
        </div>
    </div>

    <!-- Subject Management -->
    <a href="<?= BASE_URL ?>/admin/manage_subject.php" class="sidebar-link">
        Subject Management
    </a>

    <!-- Examination Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="examMenu" role="button" aria-expanded="false" tabindex="0">
            Examination Management
        </span>
        <div class="sidebar-sub" id="examMenu">
            <a href="<?= BASE_URL ?>/admin/exams.php" class="sidebar-link">Manage Exams</a>
            <a href="<?= BASE_URL ?>/admin/assign.php" class="sidebar-link">Assign Teachers</a>
            <a href="<?= BASE_URL ?>/admin/manage_assignments.php" class="sidebar-link">Teacher Assignments</a>
        </div>
    </div>

    <!-- Marks Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="marksMenu" role="button" aria-expanded="false" tabindex="0">
            Marks Management
        </span>
        <div class="sidebar-sub" id="marksMenu">
            <a href="<?= BASE_URL ?>/admin/marks_overview.php" class="sidebar-link">Subject Marks</a>
            <a href="<?= BASE_URL ?>/admin/missing_marks.php" class="sidebar-link">Missing Submissions</a>
        </div>
    </div>

    <!-- Results Management -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="resultsMenu" role="button" aria-expanded="false" tabindex="0">
            Results Management
        </span>
        <div class="sidebar-sub" id="resultsMenu">
            <a href="<?= BASE_URL ?>/admin/manage_results.php" class="sidebar-link">Results Dashboard</a>
            <a href="<?= BASE_URL ?>/admin/compile_results.php" class="sidebar-link">Compile Results</a>
            <a href="<?= BASE_URL ?>/admin/publish_results.php" class="sidebar-link">Publish Results</a>
            <a href="<?= BASE_URL ?>/admin/results_archive.php" class="sidebar-link">Results Archive</a>
        </div>
    </div>

    <!-- Analytics & Reports -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="reportMenu" role="button" aria-expanded="false" tabindex="0">
            Analytics & Reports
        </span>
        <div class="sidebar-sub" id="reportMenu">
            <a href="<?= BASE_URL ?>/admin/reports/performance.php" class="sidebar-link">Performance Analysis</a>
            <a href="<?= BASE_URL ?>/admin/reports/ranking.php" class="sidebar-link">Student Rankings</a>
            <a href="<?= BASE_URL ?>/admin/reports/school_report.php" class="sidebar-link">School Performance</a>
            <a href="<?= BASE_URL ?>/admin/reports/district_report.php" class="sidebar-link">Division Performance</a>
            <a href="<?= BASE_URL ?>/admin/reports/anomalies.php" class="sidebar-link">AI Anomalies</a>
            <a href="<?= BASE_URL ?>/admin/predictions.php" class="sidebar-link">AI Performance Predictor</a>
            <a href="<?= BASE_URL ?>/admin/reports/export_pdf.php" class="sidebar-link">Export Reports</a>
        </div>
    </div>

    <!-- System Monitoring -->
    <div class="sidebar-group">
        <span class="sidebar-menu-header" data-menu="systemMenu" role="button" aria-expanded="false" tabindex="0">
            System Monitoring
        </span>
        <div class="sidebar-sub" id="systemMenu">
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="sidebar-link">Audit Logs</a>
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