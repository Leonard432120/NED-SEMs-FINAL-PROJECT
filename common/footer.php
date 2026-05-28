<!-- ================= FOOTER ================= -->
<footer class="footer">

    <div class="footer-container">

        <!-- SYSTEM -->
        <div>
            <h4>NED-SEMS</h4>
            <p>
                National Education Data & School Examination
                Management System for monitoring school performance,
                examination compliance, and educational analytics.
            </p>
        </div>

        <!-- QUICK LINKS -->
        <div>
            <h4>Quick Access</h4>
            <ul>
                <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a></li>
                <li><a href="<?= BASE_URL ?>/admin/manage_schools.php">Schools</a></li>
                <li><a href="<?= BASE_URL ?>/admin/reports/performance.php">Performance Reports</a></li>
            </ul>
        </div>

        <!-- MODULES -->
        <div>
            <h4>System Modules</h4>
            <ul>
                <li>Exam Management</li>
                <li>School Monitoring</li>
                <li>Teacher Assignment</li>
                <li>Analytics & Reports</li>
                <li>Compliance Tracking</li>
            </ul>
        </div>

        <!-- ACCESS -->
        <div>
            <h4>Administration</h4>
            <p>
                EDM Control Center<br>
                Secure Ministry Platform<br>
                Administrative Access Portal
            </p>
        </div>

    </div>

    <div class="footer-bottom">
        © <?= date('Y') ?> NED-SEMS • Examination Management System • All Rights Reserved
    </div>

</footer>


<!-- ================= GLOBAL DELETE MODAL ================= -->
<div id="globalDeleteModal" class="modal">

    <div class="modal-content">

        <h3>Confirm Delete</h3>

        <p>
            Are you sure you want to delete this item?<br>
            This action cannot be undone.
        </p>

        <div class="modal-actions">

            <button type="button" onclick="closeDeleteModal()">Cancel</button>

            <a id="globalDeleteConfirmBtn"
               href="#"
               class="btn btn-delete">
               Yes, Delete
            </a>

        </div>

    </div>

</div>


<!-- ================= GLOBAL SCRIPTS ================= -->
<script>

document.addEventListener("DOMContentLoaded", function () {

    /* =========================================================
       SIDEBAR DROPDOWN (FIXED + REUSABLE)
    ========================================================= */
    document.querySelectorAll('.sidebar-group span').forEach(menu => {

        menu.addEventListener('click', function () {

            const targetId = this.getAttribute('data-menu');
            const submenu = document.getElementById(targetId);

            if (!submenu) return;

            submenu.classList.toggle('open');
            this.classList.toggle('open');

        });

    });


    /* =========================================================
       ACTIVE LINK AUTO HIGHLIGHT
    ========================================================= */
    const currentPage = window.location.pathname.split("/").pop();

    document.querySelectorAll('.sidebar a[data-link]').forEach(link => {

        const linkPage = link.getAttribute('href').split("/").pop();

        if (currentPage === linkPage) {

            link.classList.add('active');

            const submenu = link.closest('.sidebar-sub');

            if (submenu) {
                submenu.classList.add('open');

                const parent = submenu.previousElementSibling;
                if (parent) parent.classList.add('open');
            }
        }

    });

});


/* =========================================================
   GLOBAL DELETE MODAL (REUSABLE FIX)
========================================================= */

function openDeleteModal(url) {

    const modal = document.getElementById("globalDeleteModal");
    const btn = document.getElementById("globalDeleteConfirmBtn");

    if (!modal || !btn) return;

    btn.href = url;
    modal.classList.add("show");
}

function closeDeleteModal() {

    const modal = document.getElementById("globalDeleteModal");
    if (modal) modal.classList.remove("show");
}

/* close when clicking outside modal */
window.addEventListener("click", function (event) {

    const modal = document.getElementById("globalDeleteModal");

    if (event.target === modal) {
        modal.classList.remove("show");
    }

});

</script>

</body>
</html>