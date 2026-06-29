<?php
/**
 * verify_change.php
 * ─────────────────
 * Identity verification before profile changes.
 * Admins bypass this entirely and go straight to update_profile.php.
 */

session_start();
date_default_timezone_set('Africa/Blantyre');

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/* ── Admins skip verification entirely ── */
if ($_SESSION['role'] === 'admin') {
    $_SESSION['profile_change_verified']    = true;
    $_SESSION['profile_change_verified_at'] = time();
    header("Location: update_profile.php");
    exit();
}

/* ─────────────────────────────────────────
   Bootstrap
───────────────────────────────────────── */
$conn    = get_db_connection();
$user_id = $_SESSION['user_id'];
$step    = 'request';
$message = '';
$error   = '';

/* Ensure OTP table exists */
$conn->query("
    CREATE TABLE IF NOT EXISTS `profile_change_otps` (
        `otp_id`     INT NOT NULL AUTO_INCREMENT,
        `user_id`    INT NOT NULL,
        `otp_code`   VARCHAR(8) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used`       TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`otp_id`),
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Fetch logged-in user */
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ─────────────────────────────────────────
   Helper: send OTP
───────────────────────────────────────── */
function send_change_otp($conn, $user_id, $user, &$step, &$message, &$error) {
    $last_sent = $_SESSION['verify_change_sent_at'] ?? 0;

    if (time() - $last_sent < 60) {
        $error = 'Please wait a moment before requesting another code.';
        $step  = 'verify_otp';
        return;
    }

    $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $insert = $conn->prepare("
        INSERT INTO profile_change_otps (user_id, otp_code, expires_at)
        VALUES (?, ?, ?)
    ");
    $insert->bind_param("iss", $user_id, $otp, $expiresAt);
    $insert->execute();
    $otp_id = $insert->insert_id;
    $insert->close();

    $_SESSION['verify_change_otp_id']  = $otp_id;
    $_SESSION['verify_change_expires'] = $expiresAt;
    $_SESSION['verify_change_sent_at'] = time();

    $subject = 'NED-SEMS – Profile Change Verification';
    $body    = "Hello {$user['name']},\n\n"
             . "A request was made to update your profile on NED-SEMS.\n\n"
             . "Your verification code is: {$otp}\n\n"
             . "This code expires in 10 minutes.\n\n"
             . "If you did not request this, please contact your administrator immediately.";

    $sent = send_email($user['email'], $subject, $body);

    $message = $sent
        ? "A verification code has been sent to {$user['email']}. Please check your inbox."
        : "Code generated but email could not be sent. Please contact your system administrator.";

    $step = 'verify_otp';
}

/* ─────────────────────────────────────────
   POST handling
───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'send_otp') {
        send_change_otp($conn, $user_id, $user, $step, $message, $error);

    } elseif ($action === 'verify_otp') {
        $entered_otp = trim($_POST['otp'] ?? '');
        $otp_id      = $_SESSION['verify_change_otp_id'] ?? null;

        if (!$otp_id || $entered_otp === '') {
            $error = 'Please request a verification code first.';
            $step  = 'request';
        } else {
            $stmt = $conn->prepare("
                SELECT otp_id, expires_at
                FROM profile_change_otps
                WHERE user_id = ? AND otp_code = ? AND used = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param("is", $user_id, $entered_otp);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && strtotime($row['expires_at']) >= time()) {
                $mark = $conn->prepare("UPDATE profile_change_otps SET used = 1 WHERE otp_id = ?");
                $mark->bind_param("i", $row['otp_id']);
                $mark->execute();
                $mark->close();

                $_SESSION['profile_change_verified']    = true;
                $_SESSION['profile_change_verified_at'] = time();

                unset(
                    $_SESSION['verify_change_otp_id'],
                    $_SESSION['verify_change_expires'],
                    $_SESSION['verify_change_sent_at']
                );

                header("Location: update_profile.php");
                exit();
            } else {
                $error = 'Invalid or expired code. Please try again.';
                $step  = 'verify_otp';
            }
        }
    }

} else {
    /* GET: auto-send OTP immediately on first visit */
    send_change_otp($conn, $user_id, $user, $step, $message, $error);
}

$conn->close();

/* ─────────────────────────────────────────
   View helpers
───────────────────────────────────────── */
$portal_title = 'NED-SEMS | Verify Identity';

$module_css = match($_SESSION['role']) {
    'teacher'             => 'teacher',
    'headteacher'         => 'headteacher',
    'examination_officer' => 'exam_officer',
    default               => 'admin'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $portal_title ?></title>
    <?php include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .verify-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 160px);
            padding: 30px;
        }

        .verify-card {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 28px;
            padding: 38px 34px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        .verify-card h2 {
            font-size: 26px;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .verify-card .subtitle {
            font-size: 15px;
            color: #475569;
            margin-bottom: 26px;
        }

        .verify-card .form-footer {
            margin-top: 22px;
            font-size: 14px;
            color: #64748b;
            text-align: center;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .form-actions a {
            color: #2563eb;
            font-size: 14px;
            text-decoration: none;
        }

        .form-actions a:hover {
            text-decoration: underline;
        }

        .form-note {
            font-size: 14px;
            color: #475569;
            margin-bottom: 20px;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
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
                <h2 class="page-title">Verify Your Identity</h2>
                <p class="page-subtitle">
                    Complete this step to access your profile settings.
                </p>
            </div>
        </div>

        <div class="verify-wrapper">
            <div class="verify-card">

                <h2>One More Step</h2>
                <p class="subtitle">
                    For your security, confirm your identity before making any profile changes.
                </p>

                <!-- Alerts -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <?php if ($step === 'request'): ?>

                    <p class="form-note">
                        We will send a one-time verification code to
                        <strong><?= htmlspecialchars($user['email']) ?></strong>.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="send_otp">
                        <div class="form-actions">
                            <a href="dashboard.php">← Back to Dashboard</a>
                        </div>
                        <button type="submit" class="btn btn-create">
                            Send Verification Code
                        </button>
                    </form>

                <?php elseif ($step === 'verify_otp'): ?>

                    <p class="form-note">
                        A code was sent to
                        <strong><?= htmlspecialchars($user['email']) ?></strong>.
                        It expires at
                        <strong>
                            <?= date('h:i A', strtotime($_SESSION['verify_change_expires'])) ?>
                        </strong>.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="verify_otp">

                        <div class="form-group">
                            <label for="otp">Verification Code</label>
                            <input type="password"
                                   id="otp"
                                   name="otp"
                                   placeholder="Enter the 6-digit code"
                                   maxlength="6"
                                   autocomplete="one-time-code"
                                   required>
                        </div>

                        <div class="form-actions">
                            <a href="dashboard.php">Cancel</a>
                        </div>

                        <div class="show-password">
                            <input type="checkbox" id="showPassword">
                            <label for="showPassword">Show code</label>
                        </div>

                        <button type="submit" class="btn btn-create">
                            Verify &amp; Continue
                        </button>
                    </form>

                    <form method="POST" style="margin-top:14px;text-align:center;">
                        <input type="hidden" name="action" value="send_otp">
                        <button type="submit"
                                class="btn btn-edit"
                                style="font-size:13px;padding:7px 16px;">
                            Resend Code
                        </button>
                    </form>

                <?php endif; ?>

                <div class="form-footer">
                    <p>Need help? Contact your system administrator.</p>
                </div>

            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../common/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const showPassword = document.getElementById("showPassword");

    if (showPassword) {
        showPassword.addEventListener("change", function () {
            const otpInput = document.getElementById("otp");
            if (otpInput) {
                otpInput.type = this.checked ? "text" : "password";
            }
        });
    }
});
</script>

</body>
</html>