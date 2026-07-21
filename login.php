<?php
session_start([
    'read_and_close' => ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET',
]);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

include 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loginValue = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
    $stmt->bind_param("ss", $loginValue, $loginValue);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $authenticated = false;

    if ($user) {
        $storedPassword = $user['password'];

        if (password_verify($password, $storedPassword)) {
            $authenticated = true;

            if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $rehash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update->bind_param("si", $rehash, $user['user_id']);
                $update->execute();
                $update->close();
            }

        } elseif ($password === $storedPassword) {
            $authenticated = true;
            $rehash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $rehash, $user['user_id']);
            $update->execute();
            $update->close();
        }
    }

    if ($authenticated) {
        /* Update login count & last login */
        $track = $conn->prepare("
            UPDATE users
            SET login_count = login_count + 1,
                last_login  = NOW()
            WHERE user_id = ?
        ");
        $track->bind_param("i", $user['user_id']);
        $track->execute();
        $track->close();

        /* Set session variables */
        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['school_id']     = $user['school_id'];
        $_SESSION['name']          = $user['name'];
        $_SESSION['profile_image'] = $user['profile_image'];

        /* Redirect by role */
        $redirects = [
            'admin'               => BASE_URL . '/admin/dashboard.php',
            'headteacher'         => BASE_URL . '/headteacher/dashboard.php',
            'examination_officer' => BASE_URL . '/examination_officer/dashboard.php',
            'teacher'             => BASE_URL . '/teacher/dashboard.php',
        ];

        $destination = $redirects[$user['role']] ?? BASE_URL . '/login.php';
        log_audit_event('USER_LOGIN_SUCCESS', ['email' => $user['email'], 'role' => $user['role']], $user['user_id'], $conn);
        header("Location: " . $destination);
        exit();

    } else {
        log_audit_event('USER_LOGIN_FAILED', ['attempted_username' => $loginValue], null, $conn);
        $login_error = true;
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS Login</title>

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

        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to access your dashboard and manage your assigned tasks.</p>

        <?php if (!empty($login_error)): ?>
            <div class="alert alert-error">Invalid credentials. Please try again.</div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login.php">
            <div class="form-group">
               <input type="text" id="username" name="username" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                 <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <span id="togglePassword" class="toggle-eye">
                        <svg id="eyeOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg id="eyeClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.94"></path>
                            <path d="M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-2.16 3.19"></path>
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= BASE_URL ?>/forget_password.php">Forgot Password?</a>
            </div>          

            <button type="submit" class="btn btn-create">Login</button>
        </form>

        <div class="form-footer">
            <p>Need help? Contact your system administrator.</p>
        </div>

    </div>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePassword');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    toggleIcon.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        eyeOpen.style.display = isPassword ? 'none' : 'block';
        eyeClosed.style.display = isPassword ? 'block' : 'none';
    });
</script>

</body>
</html>