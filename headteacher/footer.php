<!-- ================= FOOTER ================= -->
<footer class="footer">

    <div class="footer-container">

        <!-- SYSTEM -->
        <div>
            <h4>NED-SEMS Headteacher Portal</h4>
            <p>
                National Education Data & School Examination
                Management System for school leadership,
                student oversight, and performance coordination.
            </p>
        </div>

        <!-- QUICK LINKS -->
        <div>
            <h4>Quick Access</h4>
            <ul>
                <li><a href="<?= BASE_URL ?>/headteacher/dashboard.php">Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/headteacher/manage_teachers.php">Teachers</a></li>
                <li><a href="<?= BASE_URL ?>/headteacher/manage_students.php">Students</a></li>
                <li><a href="<?= BASE_URL ?>/headteacher/reports.php">Reports</a></li>
            </ul>
        </div>

        <!-- MODULES -->
        <div>
            <h4>Headteacher Modules</h4>
            <ul>
                <li>Staff Management</li>
                <li>Exam Coordination</li>
                <li>Result Review</li>
                <li>Performance Analytics</li>
                <li>Communications Hub</li>
            </ul>
        </div>

        <!-- ACCESS -->
        <div>
            <h4>Administration</h4>
            <p>
                School Leadership Portal<br>
                Secure Institutional Platform<br>
                Headteacher Authority Access
            </p>
        </div>

    </div>

    <div class="footer-bottom">
        © <?= date('Y') ?> NED-SEMS • National Education Data System
    </div>

</footer>

<script>

    document.addEventListener("DOMContentLoaded", function () {

        document.querySelectorAll('.sidebar-group span').forEach(menu => {
            menu.addEventListener('click', function () {
                const targetId = this.getAttribute('data-menu');
                const submenu = document.getElementById(targetId);
                if (!submenu) return;
                submenu.classList.toggle('active');
                this.classList.toggle('active');
            });
        });

        const currentPage = window.location.pathname.split("/").pop();

        document.querySelectorAll('.sidebar a').forEach(link => {
            const linkPage = link.getAttribute('href').split("/").pop();
            if (currentPage === linkPage) {
                link.classList.add('active');
                const submenu = link.closest('.sidebar-sub');
                if (submenu) {
                    submenu.classList.add('active');
                    const parent = submenu.previousElementSibling;
                    if (parent) parent.classList.add('active');
                }
            }
        });

        const sidebarToggleBtn = document.getElementById('sidebarToggle');
        const dashboard = document.querySelector('.dashboard');
        const sidebar = document.querySelector('.sidebar');

        if (sidebarToggleBtn && dashboard && sidebar) {
            sidebarToggleBtn.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    dashboard.classList.toggle('mobile-sidebar-open');
                } else {
                    dashboard.classList.toggle('collapsed');
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 992) {
                    dashboard.classList.remove('mobile-sidebar-open');
                }
            });

            document.addEventListener('click', function (event) {
                if (!dashboard.classList.contains('mobile-sidebar-open')) return;
                if (window.innerWidth > 992) return;
                if (!sidebar.contains(event.target) && !sidebarToggleBtn.contains(event.target)) {
                    dashboard.classList.remove('mobile-sidebar-open');
                }
            });
        }

    });

</script>
