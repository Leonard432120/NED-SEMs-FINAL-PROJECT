<?php
session_start();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

include 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loginValue = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
    $stmt->bind_param("ss", $loginValue, $loginValue);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

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
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['school_id'] = $user['school_id'];
        $_SESSION['name'] = $user['name'];

        if ($user['role'] == 'admin') {
            header("Location: " . BASE_URL . "/admin/dashboard.php");
        } elseif ($user['role'] == 'headteacher') {
            header("Location: " . BASE_URL . "/headteacher/dashboard.php");
        } elseif ($user['role'] == 'examination_officer') {
            header("Location: " . BASE_URL . "/examination_officer/dashboard.php");
        } elseif ($user['role'] == 'teacher') {
            header("Location: " . BASE_URL . "/teacher/dashboard.php");
        }
        exit();
    } else {
        $login_error = true;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS Login</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
    <style>
        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-actions a {
            color: #2563eb;
            font-size: 14px;
            text-decoration: none;
        }

        .form-actions a:hover {
            text-decoration: underline;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #475569;
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    <div class="login-card">

        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to access your dashboard and manage your assigned tasks.</p>

        <?php if (!empty($login_error)): ?>
            <div class="alert alert-error">Invalid credentials. Please try again.</div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login.php">

            <div class="form-group">
                <label for="username">Email or Name</label>
                <input type="text" id="username" name="username" placeholder="Enter your email or name" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="form-actions">
                <a href="<?= BASE_URL ?>/forget_password.php">Forgot Password?</a>
            </div>

            <div class="show-password">
                <input type="checkbox" id="showPassword">
                <label for="showPassword">Show Password</label>
            </div>

            <button type="submit" class="btn btn-create">Login</button>
        </form>

        <div class="form-footer">
            <p>Need help? Contact your system administrator.</p>
        </div>

    </div>

</div>

<script>
    const showPassword = document.getElementById('showPassword');
    const passwordInput = document.getElementById('password');

    showPassword.addEventListener('change', function() {
        passwordInput.type = this.checked ? 'text' : 'password';
    });
</script>

</body>
</html>
