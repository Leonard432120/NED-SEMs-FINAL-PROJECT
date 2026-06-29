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
                <?php
                $footer_role = $_SESSION['role'] ?? 'admin';
                if ($footer_role === 'headteacher'): ?>
                    <li><a href="<?= BASE_URL ?>/headteacher/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/headteacher/manage_teachers.php">Teachers</a></li>
                    <li><a href="<?= BASE_URL ?>/headteacher/manage_students.php">Students</a></li>
                    <li><a href="<?= BASE_URL ?>/headteacher/reports.php">Reports</a></li>
                <?php elseif ($footer_role === 'teacher'): ?>
                    <li><a href="<?= BASE_URL ?>/teacher/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/teacher/assigned_exams.php">Item Writer Tasks</a></li>
                    <li><a href="<?= BASE_URL ?>/teacher/marks_activities.php">Marks Activities</a></li>
                    <li><a href="<?= BASE_URL ?>/teacher/analytics.php">Analytics</a></li>
                <?php elseif ($footer_role === 'examination_officer'): ?>
                    <li><a href="<?= BASE_URL ?>/examination_officer/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/examination_officer/dashboard.php#exams">Exams</a></li>
                    <li><a href="<?= BASE_URL ?>/examination_officer/dashboard.php#results">Results</a></li>
                    <li><a href="<?= BASE_URL ?>/examination_officer/dashboard.php#reports">Reports</a></li>
                <?php else: ?>
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/manage_schools.php">Schools</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/reports/performance.php">Performance Reports</a></li>
                <?php endif; ?>
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


<!-- ================= GLOBAL CONFIRMATION MODAL ================= -->
<div id="globalConfirmModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 420px; border-top: 4px solid var(--primary-dark);">
        <h3 id="globalConfirmTitle" style="margin-top: 0; display: flex; align-items: center; gap: 8px; font-size: 1.15rem; color: #1e293b;">
            <span>⚠️</span> Action Confirmation
        </h3>
        <p id="globalConfirmMessage" style="color: #475569; font-size: 0.95rem; margin-top: 10px; line-height: 1.5;"></p>
        <div class="modal-actions" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="globalConfirmCancelBtn" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: transparent; cursor: pointer;">
                Cancel
            </button>
            <button type="button" id="globalConfirmYesBtn" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; background: var(--primary-dark); color: white; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600;">
                Confirm Action
            </button>
        </div>
    </div>
</div>

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

/* =========================================================
   GLOBAL CONFIRMATION MODAL CONTROLLER & INTERCEPTOR
========================================================= */
let confirmCallback = null;

function showNiceConfirm(message, onConfirm) {
    const modal = document.getElementById("globalConfirmModal");
    const msgEl = document.getElementById("globalConfirmMessage");
    if (!modal || !msgEl) {
        if (confirm(message)) {
            onConfirm();
        }
        return;
    }
    
    msgEl.textContent = message;
    confirmCallback = onConfirm;
    modal.style.display = "block";
    modal.classList.add("show");
}

function closeNiceConfirm() {
    const modal = document.getElementById("globalConfirmModal");
    if (modal) {
        modal.style.display = "none";
        modal.classList.remove("show");
    }
    confirmCallback = null;
}

function replaceNativeConfirms() {
    document.querySelectorAll('[onclick*="confirm("], [onsubmit*="confirm("]').forEach(el => {
        if (el.hasAttribute('onclick') && !el.hasAttribute('data-confirm-bound')) {
            let val = el.getAttribute('onclick');
            let match = val.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
            if (match) {
                let msg = match[2];
                el.setAttribute('data-confirm-message', msg);
                el.setAttribute('data-confirm-bound', 'true');
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    showNiceConfirm(msg, function() {
                        if (el.tagName === 'A') {
                            window.location.href = el.href;
                        } else if ((el.type === 'submit' || el.tagName === 'BUTTON') && el.form) {
                            let form = el.form;
                            if (el.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = el.name;
                                hidden.value = el.value;
                                form.appendChild(hidden);
                            }
                            form.submit();
                        } else {
                            let clone = el.cloneNode(true);
                            clone.removeAttribute('data-confirm-message');
                            clone.removeAttribute('data-confirm-bound');
                            el.parentNode.replaceChild(clone, el);
                            clone.click();
                            clone.parentNode.replaceChild(el, clone);
                        }
                    });
                });
            }
        }
        
        if (el.hasAttribute('onsubmit') && !el.hasAttribute('data-confirm-bound')) {
            let val = el.getAttribute('onsubmit');
            let match = val.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
            if (match) {
                let msg = match[2];
                el.setAttribute('data-confirm-message', msg);
                el.setAttribute('data-confirm-bound', 'true');
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showNiceConfirm(msg, function() {
                        el.submit();
                    });
                });
            }
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const cancelBtn = document.getElementById("globalConfirmCancelBtn");
    const yesBtn = document.getElementById("globalConfirmYesBtn");
    const modal = document.getElementById("globalConfirmModal");
    
    if (cancelBtn) cancelBtn.addEventListener("click", closeNiceConfirm);
    if (yesBtn) {
        yesBtn.addEventListener("click", function() {
            if (typeof confirmCallback === "function") {
                confirmCallback();
            }
            closeNiceConfirm();
        });
    }
    
    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            closeNiceConfirm();
        }
    });
    
    // Initial rewrite of confirm actions
    replaceNativeConfirms();
    
    // Listen for dynamically added elements
    const observer = new MutationObserver(function() {
        replaceNativeConfirms();
    });
    observer.observe(document.body, { childList: true, subtree: true });
});

</script>

</body>
</html>