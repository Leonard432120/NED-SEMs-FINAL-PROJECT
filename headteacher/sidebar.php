<!-- ================= SIDEBAR ================= -->
<div class="sidebar">

    <!-- DASHBOARD -->
    <a href="<?= BASE_URL ?>/headteacher/dashboard.php" data-link>
        Dashboard
    </a>

    <!-- STAFF MANAGEMENT -->
    <div class="sidebar-group">
        <span data-menu="staffMenu">
            Staff Management ▼
        </span>
        <div class="sidebar-sub" id="staffMenu">
            <a href="<?= BASE_URL ?>/headteacher/manage_teachers.php" data-link>
                Teachers
            </a>
            <a href="<?= BASE_URL ?>/headteacher/manage_examination_officers.php" data-link>
                Examination Officers
            </a>
        </div>
    </div>

    <!-- STUDENT MANAGEMENT -->
    <a href="<?= BASE_URL ?>/headteacher/manage_students.php" data-link>
        Student Management
    </a>

    <!-- COMMUNICATION HUB -->
    <div class="sidebar-group">
        <span data-menu="commMenu">
            Communication Hub ▼
        </span>
        <div class="sidebar-sub" id="commMenu">
            <a href="<?= BASE_URL ?>/headteacher/forwarding_center.php" data-link>
                Forwarding Center
            </a>
            <a href="<?= BASE_URL ?>/headteacher/exam_forwarding.php" data-link>
                Forward Exams
            </a>
            <a href="<?= BASE_URL ?>/headteacher/results_forwarding.php" data-link>
                Forward Results
            </a>
        </div>
    </div>

    <!-- MARKING & EXAMINATIONS -->
    <div class="sidebar-group">
        <span data-menu="markingMenu">
            Marking & Examinations ▼
        </span>
        <div class="sidebar-sub" id="markingMenu">
            <a href="<?= BASE_URL ?>/headteacher/assign_markers.php" data-link>
                Assign Markers
            </a>
            <a href="<?= BASE_URL ?>/headteacher/marks_management.php" data-link>
                Marks Management
            </a>
            <a href="<?= BASE_URL ?>/headteacher/marks_reception.php" data-link>
                Marks Reception
            </a>
            <a href="<?= BASE_URL ?>/headteacher/released_results.php" data-link>
                Released Results
            </a>
        </div>
    </div>

    <!-- PERFORMANCE & RESULTS -->
    <div class="sidebar-group">
        <span data-menu="perfMenu">
            Analytics & Reports ▼
        </span>
        <div class="sidebar-sub" id="perfMenu">
            <a href="<?= BASE_URL ?>/headteacher/performance.php" data-link>
                Performance Analytics
            </a>
            <a href="<?= BASE_URL ?>/headteacher/reports.php" data-link>
                School Reports
            </a>
        </div>
    </div>

    <!-- DOCUMENTS -->
    <a href="<?= BASE_URL ?>/headteacher/documents.php" data-link>
        Documents & Timetables
    </a>

</div>
