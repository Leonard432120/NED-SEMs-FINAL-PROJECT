<?php
session_start();
date_default_timezone_set('Africa/Blantyre');

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

require_once 'config/db.php';
require_once 'common/email_service.php';

$step = 'request';
$message = '';
$error = '';
$reset_email = '';
$reset_name = '';

$conn = get_db_connection();
$createResetTable = "CREATE TABLE IF NOT EXISTS `password_resets` (
    `reset_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `otp_code` VARCHAR(8) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reset_id`),
    INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($createResetTable);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'send_otp') {
        $loginValue = trim($_POST['login_value'] ?? '');

        if ($loginValue === '') {
            $error = 'Please enter your registered name or email.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
            $stmt->bind_param('ss', $loginValue, $loginValue);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', time() + 600);

                $insert = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
                $insert->bind_param('iss', $user['user_id'], $otp, $expiresAt);
                $insert->execute();
                $resetId = $insert->insert_id;
                $insert->close();

                $_SESSION['password_reset_user_id'] = $user['user_id'];
                $_SESSION['password_reset_id'] = $resetId;
                $_SESSION['password_reset_expires'] = $expiresAt;
                $_SESSION['password_reset_sent_at'] = time();

                $subject = 'NED-SEMS Password Reset OTP';
                $messageBody = "Hello {$user['name']},\n\nYour password reset OTP is: {$otp}\nThis code expires in 10 minutes.\n\nIf you did not request this, please ignore this message.";
                $sent = send_email($user['email'], $subject, $messageBody);

                if ($sent) {
                    $message = 'An OTP has been sent to your registered email address. Please check your inbox.';
                } else {
                    $message = 'We generated an OTP, but the email could not be sent. Please contact your system administrator.';
                }

                $step = 'verify_otp';
                $reset_email = $user['email'];
                $reset_name = $user['name'];
            } else {
                $error = 'No account was found with that name or email.';
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        $userId = $_SESSION['password_reset_user_id'] ?? null;

        if (!$userId) {
            $error = 'Please request a reset code first.';
        } elseif ($otp === '') {
            $error = 'Enter the OTP sent to your email.';
            $step = 'verify_otp';
        } else {
            $stmt = $conn->prepare("SELECT reset_id, expires_at FROM password_resets WHERE user_id = ? AND otp_code = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param('is', $userId, $otp);
            $stmt->execute();
            $result = $stmt->get_result();
            $resetRow = $result->fetch_assoc();
            $stmt->close();

            if ($resetRow && strtotime($resetRow['expires_at']) >= time()) {
                $_SESSION['password_reset_verified'] = true;
                $_SESSION['password_reset_id'] = $resetRow['reset_id'];
                $message = 'OTP verified. You may now enter a new password.';
                $step = 'new_password';
            } else {
                $error = 'Invalid or expired OTP. Please try again.';
                $step = 'verify_otp';
            }
        }
    } elseif ($action === 'new_password') {
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        $userId = $_SESSION['password_reset_user_id'] ?? null;
        $resetId = $_SESSION['password_reset_id'] ?? null;
        $verified = $_SESSION['password_reset_verified'] ?? false;

        if (!$userId || !$verified || !$resetId) {
            $error = 'Please complete the OTP verification first.';
            $step = 'request';
        } elseif ($password === '' || $confirmPassword === '') {
            $error = 'Both password fields are required.';
            $step = 'new_password';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 'new_password';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $step = 'new_password';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param('si', $hash, $userId);
            $update->execute();
            $update->close();

            $markUsed = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?");
            $markUsed->bind_param('i', $resetId);
            $markUsed->execute();
            $markUsed->close();

            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_id'], $_SESSION['password_reset_expires'], $_SESSION['password_reset_sent_at'], $_SESSION['password_reset_verified']);

            $message = 'Your password has been reset successfully. You may now log in with your new password.';
            $step = 'complete';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS Password Reset</title>
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">

    <style>
        .login-logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .login-logo img {
            max-width: 180px;
            height: auto;
        }

        .login-card {
            text-align: center;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
        }

        .login-card {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 28px;
            padding: 38px 34px;
            box-shadow: 0 24px 60px rgba(15,23,42,0.12);
        }

        .login-card h2 {
            font-size: 30px;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .login-card .subtitle {
            font-size: 15px;
            color: #475569;
            margin-bottom: 26px;
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 40px;
        }

        .toggle-eye {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
        }

        .toggle-eye:hover {
            color: #2563eb;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo Added Here -->
        <div class="login-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo1.png" 
                 alt="NED-SEMS - Northern Education Division Smart Examination Management System" 
                 width="180">
        </div>

        <h2>Password Reset</h2>
        <p class="subtitle">Use the OTP sent to your registered email to reset your password.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($step === 'request'): ?>
            <form method="POST" action="<?= BASE_URL ?>/forget_password.php">
                <input type="hidden" name="action" value="send_otp">
                <div class="form-group">                    
                    <input type="text" id="login_value" name="login_value" placeholder="Enter your email" required>
                </div>
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/login.php">Back to login</a>
                </div>
                <button type="submit" class="btn btn-create">Send OTP</button>
            </form>

        <?php elseif ($step === 'verify_otp'): ?>
            <p class="form-note">
                An OTP was sent to <strong><?= htmlspecialchars($reset_email ?: 'your email') ?></strong>.<br>
                The code expires at <strong><?= date('h:i A', strtotime($_SESSION['password_reset_expires'])) ?></strong>.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/forget_password.php">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">                  
                    <div class="password-wrapper">
                        <input type="password" id="otp" name="otp" placeholder="Enter the 6-digit OTP" maxlength="6" required>
                        <span class="toggle-eye" data-target="otp">
                            <svg class="eyeOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eyeClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.94"></path>
                                <path d="M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-2.16 3.19"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/login.php">Cancel</a>
                </div>                
                <button type="submit" class="btn btn-create">Verify OTP</button>
            </form>

        <?php elseif ($step === 'new_password'): ?>
            <p class="form-note">Enter a new password for your account.</p>
            <form method="POST" action="<?= BASE_URL ?>/forget_password.php">
                <input type="hidden" name="action" value="new_password">
                <div class="form-group">                    
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="New password" required>
                        <span class="toggle-eye" data-target="password">
                            <svg class="eyeOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eyeClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.94"></path>
                                <path d="M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-2.16 3.19"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="form-group">                    
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
                        <span class="toggle-eye" data-target="confirm_password">
                            <svg class="eyeOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eyeClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.94"></path>
                                <path d="M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-2.16 3.19"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </span>
                    </div>
                </div>                
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/login.php">Cancel</a>
                </div>
                <button type="submit" class="btn btn-create">Reset Password</button>
            </form>

        <?php else: ?>
            <div class="form-group">
                <p class="form-note">Your password has been reset successfully.<br>You may now log in with your new password.</p>
            </div>
            <div class="form-actions">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-create" style="display:inline-block; text-decoration:none; text-align:center;">Back to Login</a>
            </div>
        <?php endif; ?>

        <div class="form-footer">
            <p>Need help? Contact your system administrator.</p>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const toggles = document.querySelectorAll(".toggle-eye");

    toggles.forEach(function (toggle) {
        toggle.addEventListener("click", function () {
            const targetId = this.getAttribute("data-target");
            const input = document.getElementById(targetId);
            const eyeOpen = this.querySelector(".eyeOpen");
            const eyeClosed = this.querySelector(".eyeClosed");

            if (!input) return;

            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            eyeOpen.style.display = isPassword ? "none" : "block";
            eyeClosed.style.display = isPassword ? "block" : "none";
        });
    });
});
</script>

</body>
</html>