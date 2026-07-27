<?php

/* ==========================================
   LANDING PAGE FOOTER
========================================== */

if (isset($landing_page) && $landing_page === true):
?>

<style>
.footer.landing-footer {
    background: var(--gov-navy, #0B2545);
    color: #cbd5e1;
    padding: 44px 0 20px;
}

.landing-footer .footer-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr;
    gap: 40px;
}

.landing-footer .footer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.landing-footer .footer-logo img { height: 40px; width: auto; }
.landing-footer .footer-logo h3 { color: #ffffff; margin: 0; font-family: Georgia, serif; }

.landing-footer .footer-desc {
    color: #94a3b8;
    font-size: 14px;
    line-height: 1.6;
}

.landing-footer .footer-col h4 {
    color: #ffffff;
    font-size: 14px;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.landing-footer .footer-col ul { list-style: none; padding: 0; margin: 0; }
.landing-footer .footer-col ul li { margin-bottom: 8px; }
.landing-footer .footer-col ul li a,
.landing-footer .footer-col ul li {
    color: #94a3b8;
    text-decoration: none;
    font-size: 14px;
}
.landing-footer .footer-col ul li a:hover { color: var(--gov-gold, #C9A227); }

.landing-footer .footer-bottom {
    border-top: 1px solid #1e3a5f;
    margin-top: 32px;
    padding-top: 18px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 13px;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .landing-footer .footer-grid { grid-template-columns: 1fr; }
}
</style>

<footer class="footer landing-footer">
    <div class="landing-container">
        <div class="footer-grid">
            <div>
                <div class="footer-logo">
                    <img src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo.png" alt="NED-SEMS Logo">
                    <h3>NED-SEMS</h3>
                </div>
                <p class="footer-desc">
                    Northern Education Division Smart Examination Management System.
                    Official examination coordination platform for the Division.
                </p>
            </div>

            <div class="footer-col">
                <h4>Access</h4>
                <ul>
                    <li><a href="/NED-SEMs FINAL YEAR PROJECT/login.php">Portal Login</a></li>
                    <li><a href="/NED-SEMs FINAL YEAR PROJECT/forget_password.php">Forgot Password</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><a href="mailto:support@ned-sems.gov.mw">support@ned-sems.gov.mw</a></li>
                    <li>Mzuzu, Malawi</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> NED-SEMS | Northern Education Division. All rights reserved.</div>
            <div style="font-weight:600;color:#ffffff;">Ministry of Education, Malawi</div>
        </div>
    </div>
</footer>

<?php
return;
endif;
?>

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
    <div class="gdm-box">

        <!-- Icon -->
        <div class="gdm-icon-wrap">
            <svg class="gdm-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
            </svg>
        </div>

        <!-- Heading -->
        <h3 class="gdm-title">Delete Confirmation</h3>
        <p class="gdm-msg">Are you sure you want to permanently delete this item?<br>This action <strong>cannot be undone</strong>.</p>

        <!-- Actions -->
        <div class="gdm-actions">
            <button type="button" class="gdm-btn gdm-btn-cancel" onclick="closeDeleteModal()">
                Cancel
            </button>
            <a id="globalDeleteConfirmBtn" href="#" class="gdm-btn gdm-btn-delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                </svg>
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

    document.querySelectorAll('.sidebar a').forEach(link => {

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