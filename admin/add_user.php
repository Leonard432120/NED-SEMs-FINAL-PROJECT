<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

// ================= PASSWORD GENERATOR =================
function generateStrongPassword($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$!';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

// ================= CREATE USER =================
function createUser($conn, $name, $email, $role, $phone) {

    $name  = trim($name);
    $email = trim($email);
    $role  = trim($role);
    $phone = trim($phone);

    if (!$name || !$email || !$role) {
        return ['status' => 'error', 'message' => 'Missing required fields'];
    }

    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Email already exists'];
    }

    $plain_password = generateStrongPassword(12);
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (name, email, role, phone, password, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param("sssss", $name, $email, $role, $phone, $hashed_password);

    if ($stmt->execute()) {

        send_email(
            $email,
            "Account Created - School Management System",
            "Hello $name,

Your account has been created.

Email: $email
Password: $plain_password
Role: $role

Please change your password after login."
        );

        return ['status' => 'success', 'message' => 'User created successfully'];
    }

    return ['status' => 'error', 'message' => 'Failed to create user'];
}

// ================= CREATE USER =================
if (isset($_POST['create_user'])) {

    $res = createUser(
        $conn,
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['role'] ?? '',
        $_POST['phone'] ?? ''
    );

    $message = $res['message'];
    $message_type = $res['status'];
}

// ================= IMPORT USERS =================
if (isset($_POST['import_users'])) {

    if (!empty($_FILES['xlsx_file']['tmp_name'])) {

        try {
            $spreadsheet = IOFactory::load($_FILES['xlsx_file']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet()->toArray();

            $success = 0;
            $failed = 0;

            foreach ($sheet as $i => $row) {

                if ($i === 0) continue;

                $name  = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $role  = trim($row[2] ?? '');
                $phone = trim($row[3] ?? '');

                if (!$name || !$email || !$role) {
                    $failed++;
                    continue;
                }

                $res = createUser($conn, $name, $email, $role, $phone);
                ($res['status'] === 'success') ? $success++ : $failed++;
            }

            $message = "$success user(s) imported successfully. $failed failed.";
            $message_type = 'success';

        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
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
<title>Add User</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

<style>
/* ====== TWO COLUMN LAYOUT FIX ====== */
.main-content{
    width:100%;
    max-width:1100px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:30px;
    align-items:start;
}

/* card spacing balance */
.card,
.import-card{
    height:100%;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
}

/* import card center */
.import-card{
    text-align:center;
}

/* nicer headings */
.card h3,
.import-card h3{
    font-size:18px;
    margin-bottom:10px;
    color:#0f172a;
}

/* smooth hover cards */
.card:hover,
.import-card:hover{
    transform:translateY(-2px);
    transition:0.3s ease;
}

/* button spacing inside form */
.form-container{
    display:flex;
    flex-direction:column;
    gap:10px;
}

/* submit button spacing */
.btn-create{
    margin-top:10px;
     color: #334155;
}

/* import button improvement */
.import-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;

    height:52px;
    padding:0 24px;

    border-radius:14px;

    background:#fff;
    border:1px dashed #cbd5e1;

    font-weight:700;
    color:#334155;

    cursor:pointer;
    transition:0.25s ease;

    margin-top:15px;
}

.import-btn:hover{
    border-color:#2563eb;
    color:#2563eb;
    background:#f8fafc;
    transform:translateY(-2px);
}
</style>

</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h2 class="page-title">Add New User</h2>
            <p class="page-subtitle">Create single or bulk user accounts</p>
        </div>

        <a href="manage_users.php" class="btn-back">
            ← Back
        </a>
    </div>

    <!-- ALERT -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- TWO COLUMN GRID -->
    <div class="main-content">

        <!-- LEFT: IMPORT -->
        <div class="card import-card">

            <h3>Import Users</h3>
            <p class="page-subtitle">
                Upload Excel file (Name, Email, Role, Phone)
            </p>

            <form method="POST" enctype="multipart/form-data" class="import-form">
                <input type="hidden" name="import_users" value="1">

                <label class="import-label">
                    <input type="file" name="xlsx_file" accept=".xlsx"
                           onchange="this.form.submit()">

                    <span class="import-btn">
                        📁 Choose Excel File
                    </span>
                </label>
            </form>

        </div>

        <!-- RIGHT: CREATE USER -->
        <div class="card">

            <h3>Create User</h3>

            <form method="POST" class="form-container">
                <input type="hidden" name="create_user" value="1">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="headteacher">Headteacher</option>
                        <option value="examination_officer">Examination Officer</option>
                    </select>
                </div>

                <div class="info-popup">
                    <h4>Auto Password System</h4>
                    <p>
                        A secure password will be generated automatically and sent to the user's email.
                    </p>
                </div>

                <button type="submit" class="btn btn-create">
                    Create User
                </button>

            </form>

        </div>

    </div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>