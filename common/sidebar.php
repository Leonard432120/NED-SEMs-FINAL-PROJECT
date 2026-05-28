<!-- ================= SIDEBAR ================= -->
<div class="sidebar">

    <!-- DASHBOARD -->
    <a href="<?= BASE_URL ?>/admin/dashboard.php" data-link>
        Dashboard
    </a>

    <!-- USERS -->
    <a href="<?= BASE_URL ?>/admin/manage_users.php" data-link>
        Users
    </a>

    <!-- SCHOOLS -->
    <div class="sidebar-group">

        <span data-menu="schoolMenu">
            Schools ▼
        </span>

        <div class="sidebar-sub" id="schoolMenu">

            <a href="<?= BASE_URL ?>/admin/add_school.php" data-link>
                Add School
            </a>

            <a href="<?= BASE_URL ?>/admin/manage_schools.php" data-link>
                Manage Schools
            </a>

        </div>
    </div>

    <!-- SUBJECTS -->
    <a href="<?= BASE_URL ?>/admin/manage_subject.php" data-link>
        Subjects
    </a>

    <!-- EXAMS -->
    <a href="<?= BASE_URL ?>/admin/exams.php" data-link>
        Exams
    </a>

    <!-- ASSIGNMENTS -->
    <div class="sidebar-group">

        <span data-menu="assignMenu">
            Assignments ▼
        </span>

        <div class="sidebar-sub" id="assignMenu">

            <a href="<?= BASE_URL ?>/admin/assign.php" data-link>
                Assign Teachers
            </a>

            <a href="<?= BASE_URL ?>/admin/manage_assignments.php" data-link>
                View Assignments
            </a>

        </div>
    </div>

    <!-- RESULTS -->
    <a href="<?= BASE_URL ?>/admin/results.php" data-link>
        Results
    </a>

    <!-- MODERATION -->
    <a href="<?= BASE_URL ?>/admin/moderation.php" data-link>
        Moderation
    </a>

    <!-- REPORTS -->
    <div class="sidebar-group">

        <span data-menu="reportMenu">
            Reports ▼
        </span>

        <div class="sidebar-sub" id="reportMenu">

            <a href="<?= BASE_URL ?>/admin/reports/index.php" data-link>
                Overview
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/anomalies.php" data-link>
                Anomalies
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/compliance_report.php" data-link>
                Compliance
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/school_report.php" data-link>
                School Report
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/district_report.php" data-link>
                District Report
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/ranking.php" data-link>
                Ranking
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/performance.php" data-link>
                Performance AI
            </a>

            <a href="<?= BASE_URL ?>/admin/reports/export_pdf.php" data-link>
                Export PDF
            </a>

        </div>
    </div>

    <!-- AUDIT LOGS -->
    <a href="<?= BASE_URL ?>/admin/audit_logs.php" data-link>
        Audit Logs
    </a>

</div>