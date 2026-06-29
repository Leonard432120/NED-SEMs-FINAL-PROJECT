<?php
/**
 * update_profile.php
 * ──────────────────
 * Secure profile update with OTP verification + Audit Logging
 */

session_start();
date_default_timezone_set('Africa/Blantyre');

require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/* ── OTP Verification Gate (15 minutes) ── */
$verified_at = $_SESSION['profile_change_verified_at'] ?? 0;
$is_verified = ($_SESSION['profile_change_verified'] ?? false) && (time() - $verified_at < 900);

if (!$is_verified) {
    unset($_SESSION['profile_change_verified'], $_SESSION['profile_change_verified_at']);
    header("Location: verify_change.php");
    exit();
}

/* ── Database Connection ── */
$conn = get_db_connection();
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

/* ── Fetch Current User ── */
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ── Load Subjects & Teacher Categories ── */
$all_subjects = [];
$result = $conn->query("SELECT subject_name, category FROM subjects WHERE status = 'active' ORDER BY subject_name ASC");
while ($row = $result->fetch_assoc()) {
    $all_subjects[] = $row;
}
$result->free();

$teacher_categories = [];
$col_result = $conn->query("SHOW COLUMNS FROM users LIKE 'teacher_category'");
if ($col_result && ($col_row = $col_result->fetch_assoc())) {
    if (preg_match("/^enum\((.+)\)$/i", $col_row['Type'], $matches)) {
        $teacher_categories = str_getcsv($matches[1], ',', "'");
    }
}
$col_result->free();

/* ── Function to Log Activity ── */
function log_activity($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("
        INSERT INTO audit_logs 
        (user_id, target_user_id, action, details, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss", $user_id, $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

/* ── Handle Form Submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ================== UPDATE PROFILE ================== */
    if ($action === 'update_profile') {

        $name             = trim($_POST['name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $gender           = trim($_POST['gender'] ?? '');
        $teacher_category = trim($_POST['teacher_category'] ?? '');
        $major_subject    = trim($_POST['major_subject'] ?? '');
        $minor_subject    = trim($_POST['minor_subject'] ?? '');
        $profile_image    = $user['profile_image'];

        /* Profile Image Upload */
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $upload_dir = "../uploads/profiles/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                $new_name = "user_{$user_id}_" . time() . ".{$ext}";
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                    $profile_image = $new_name;
                }
            }
        }

        $stmt = $conn->prepare("
            UPDATE users SET 
                name=?, email=?, phone=?, gender=?, 
                teacher_category=?, major_subject=?, minor_subject=?, profile_image=?
            WHERE user_id=?
        ");
        $stmt->bind_param("ssssssssi", 
            $name, $email, $phone, $gender, 
            $teacher_category, $major_subject, $minor_subject, 
            $profile_image, $user_id
        );

        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $_SESSION['profile_image'] = $profile_image;

            $message = "Profile updated successfully.";
            $message_type = "success";

            /* Audit Log */
            $details = "Updated profile information for user ID {$user_id}";
            log_activity($conn, $user_id, "update_profile", $details);

            /* Refresh user data */
            $stmt2 = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $message = "Failed to update profile.";
            $message_type = "error";
        }
        $stmt->close();
    }

    /* ================== CHANGE PASSWORD ================== */
    elseif ($action === 'change_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (!password_verify($current_password, $user['password'])) {
            $message = "Current password is incorrect.";
            $message_type = "error";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters.";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = "error";
        } elseif (password_verify($new_password, $user['password'])) {
            $message = "New password must be different from current password.";
            $message_type = "error";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $user_id);

            if ($stmt->execute()) {
                $message = "Password changed successfully.";
                $message_type = "success";

                /* Audit Log */
                $details = "Changed password for user ID {$user_id}";
                log_activity($conn, $user_id, "change_password", $details);

                /* Revoke OTP session */
                unset($_SESSION['profile_change_verified'], $_SESSION['profile_change_verified_at']);
            } else {
                $message = "Failed to change password.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

$conn->close();

/* ── Helper Variables ── */
$profile_image_url = !empty($user['profile_image'])
    ? BASE_URL . '/uploads/profiles/' . $user['profile_image']
    : BASE_URL . '/static/images/user.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $portal_title ?></title>
    <?php include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .profile-container {
            max-width: 1060px;
            margin: auto;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
            align-items: start;
        }

        .profile-card { text-align: center; }

        .profile-image {
            width: 170px;
            height: 170px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #e2e8f0;
            margin-bottom: 16px;
        }

        .image-upload { margin-top: 14px; }

        /* Tabs */
        .tab-nav {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 9px 20px;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            border-radius: 6px 6px 0 0;
            transition: color .2s, border-color .2s;
        }

        .tab-btn.active {
            color: #0f172a;
            border-bottom-color: #1e40af;
        }

        .tab-btn:hover:not(.active) {
            color: #334155;
            background: #f1f5f9;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
        }

        .save-area {
            margin-top: 26px;
            display: flex;
            gap: 12px;
        }

        /* Password strength */
        .strength-bar {
            height: 5px;
            border-radius: 4px;
            background: #e2e8f0;
            margin-top: 6px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: width .3s, background .3s;
        }

        .strength-label {
            font-size: 12px;
            margin-top: 4px;
            color: #64748b;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">

    <?php include __DIR__ . '/../common/sidebar.php'; ?>

    <div class="content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Update Profile</h2>
                <p class="page-subtitle">Manage your personal information, subjects, and password.</p>
            </div>
        </div>

        <!-- Alert -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-grid">

                <!-- ── Left: avatar ── -->
                <div class="card profile-card">

                    <img src="<?= $profile_image_url ?>"
                         class="profile-image"
                         id="avatarPreview"
                         alt="Profile Image">

                    <h3><?= htmlspecialchars($user['name']) ?></h3>

                    <p style="color:#64748b;font-size:14px;">
                        <?= ucfirst(str_replace('_', ' ', $_SESSION['role'])) ?>
                    </p>

                    <div class="image-upload">
                        <label style="font-weight:600;font-size:13px;">Profile Picture</label>
                        <input type="file"
                               name="profile_image"
                               id="avatarInput"
                               form="profileForm"
                               accept=".jpg,.jpeg,.png,.webp">
                        <p style="font-size:12px;color:#94a3b8;margin-top:6px;">
                            JPG, PNG or WebP
                        </p>
                    </div>

                </div>

                <!-- ── Right: tabbed forms ── -->
                <div class="card">

                    <div class="tab-nav">
                        <button class="tab-btn active" data-tab="personal">Personal Info</button>
                        <button class="tab-btn" data-tab="password">Change Password</button>
                    </div>

                    <!-- ════ Tab 1: Personal Info ════ -->
                    <div class="tab-panel active" id="tab-personal">

                        <form id="profileForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-grid">

                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text"
                                           name="name"
                                           value="<?= htmlspecialchars($user['name']) ?>"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender">
                                        <option value="">Select Gender</option>
                                        <?php foreach (['Male', 'Female'] as $g): ?>
                                            <option value="<?= htmlspecialchars($g) ?>"
                                                <?= ($user['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($g) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email"
                                           name="email"
                                           value="<?= htmlspecialchars($user['email']) ?>"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text"
                                           name="phone"
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Teacher Category</label>
                                    <select name="teacher_category" id="teacherCategory">
                                        <option value="">Select Category</option>
                                        <?php foreach ($teacher_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"
                                                <?= ($user['teacher_category'] ?? '') === $cat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucfirst($cat)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Major Subject</label>
                                    <select name="major_subject" id="majorSubject" disabled>
                                        <option value="">Select Teacher Category first</option>
                                        <?php foreach ($all_subjects as $sub): ?>
                                            <option value="<?= htmlspecialchars($sub['subject_name']) ?>"
                                                data-category="<?= htmlspecialchars($sub['category']) ?>"
                                                <?= ($user['major_subject'] ?? '') === $sub['subject_name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub['subject_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Minor Subject</label>
                                    <select name="minor_subject" id="minorSubject" disabled>
                                        <option value="">Select Teacher Category first</option>
                                        <?php foreach ($all_subjects as $sub): ?>
                                            <option value="<?= htmlspecialchars($sub['subject_name']) ?>"
                                                data-category="<?= htmlspecialchars($sub['category']) ?>"
                                                <?= ($user['minor_subject'] ?? '') === $sub['subject_name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub['subject_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                            <div class="save-area">
                                <button type="submit" class="btn btn-dark">Save Changes</button>
                                <a href="dashboard.php" class="btn btn-edit">Cancel</a>
                            </div>

                        </form>

                    </div>

                    <!-- ════ Tab 2: Change Password ════ -->
                    <div class="tab-panel" id="tab-password">

                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-grid" style="grid-template-columns:1fr;">

                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password"
                                           id="currentPassword"
                                           name="current_password"
                                           placeholder="Enter your current password"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password"
                                           id="newPassword"
                                           name="new_password"
                                           placeholder="Min. 6 characters"
                                           required>
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <span class="strength-label" id="strengthLabel"></span>
                                </div>

                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password"
                                           id="confirmPassword"
                                           name="confirm_password"
                                           placeholder="Repeat new password"
                                           required>
                                    <span class="strength-label" id="matchLabel"></span>
                                </div>

                                <div class="show-password">
                                    <input type="checkbox" id="showPasswords">
                                    <label for="showPasswords">Show passwords</label>
                                </div>

                            </div>

                            <div class="save-area">
                                <button type="submit" class="btn btn-dark">Change Password</button>
                                <a href="dashboard.php" class="btn btn-edit">Cancel</a>
                            </div>

                        </form>

                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>

<script>
/* ── Tab switching ── */
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

/* ── Category-based subject filtering ── */
function filterSubjectsByCategory() {
    const category    = document.getElementById('teacherCategory').value;
    const majorSelect = document.getElementById('majorSubject');
    const minorSelect = document.getElementById('minorSubject');

    [majorSelect, minorSelect].forEach(select => {
        const previousValue = select.value;
        let hasMatchingSelection = false;

        select.querySelectorAll('option[data-category]').forEach(option => {
            const matches = option.dataset.category === category;
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches && option.value === previousValue) {
                hasMatchingSelection = true;
            }
        });

        if (category) {
            select.disabled = false;
            select.querySelector('option[value=""]').textContent =
                select.id === 'majorSubject' ? 'Select Major Subject' : 'Select Minor Subject';
            // Reset selection if the previously selected subject doesn't
            // belong to the newly chosen category.
            if (!hasMatchingSelection) {
                select.value = '';
            }
        } else {
            select.disabled = true;
            select.value = '';
            select.querySelector('option[value=""]').textContent = 'Select Teacher Category first';
        }
    });
}

document.getElementById('teacherCategory')?.addEventListener('change', filterSubjectsByCategory);

// Run once on page load so an existing saved category pre-filters
// and re-enables the Major/Minor dropdowns immediately.
filterSubjectsByCategory();

/* ── Auto-open password tab if that action had an error ── */
<?php if ($message_type === 'error' && ($_POST['action'] ?? '') === 'change_password'): ?>
document.querySelector('[data-tab="password"]').click();
<?php endif; ?>

/* ── Avatar live preview ── */
document.getElementById('avatarInput')?.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
        reader.readAsDataURL(file);
    }
});

/* ── Password strength meter ── */
document.getElementById('newPassword')?.addEventListener('input', function () {
    const val   = this.value;
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');

    let score = 0;
    if (val.length >= 6)           score++;
    if (val.length >= 10)          score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;

    const levels = [
        { pct: '0%',   color: '#e2e8f0', text: '' },
        { pct: '25%',  color: '#ef4444', text: 'Weak' },
        { pct: '50%',  color: '#f97316', text: 'Fair' },
        { pct: '75%',  color: '#eab308', text: 'Good' },
        { pct: '100%', color: '#22c55e', text: 'Strong' },
    ];

    const lvl = levels[Math.min(score, 4)];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.color;
});

/* ── Password match indicator ── */
document.getElementById('confirmPassword')?.addEventListener('input', function () {
    const newPw = document.getElementById('newPassword').value;
    const label = document.getElementById('matchLabel');
    if (this.value === '') {
        label.textContent = '';
    } else if (this.value === newPw) {
        label.textContent = '✓ Passwords match';
        label.style.color = '#22c55e';
    } else {
        label.textContent = '✗ Passwords do not match';
        label.style.color = '#ef4444';
    }
});

/* ── Show / hide all password fields ── */
document.getElementById('showPasswords')?.addEventListener('change', function () {
    ['currentPassword', 'newPassword', 'confirmPassword'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.type = this.checked ? 'text' : 'password';
    });
});
</script>

</body>
</html>