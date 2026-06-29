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
function createUser($conn, $name, $email, $role, $phone, $gender, $teacher_category, $qualification, $employment_number, $school_id = null, $major_subject = null, $minor_subject = null){
    $name  = trim($name);
    $email = trim($email);
    $role  = trim($role);
    $phone = trim($phone);

    if (!$name || !$email || !$role) {
        return ['status' => 'error', 'message' => 'Missing required fields'];
    }

    // only require category for teachers
    // only require category for teachers
    if ($role === 'teacher' && empty($teacher_category)) {
        return ['status' => 'error', 'message' => 'Teacher category is required'];
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
            INSERT INTO users (
                name,
                email,
                role,
                phone,
                gender,
                password,
                teacher_category,
                qualification,
                employment_number,
                school_id,
                major_subject,
                minor_subject,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

    $stmt->bind_param(
            "ssssssssssss",
            $name,
            $email,
            $role,
            $phone,
            $gender,
            $hashed_password,
            $teacher_category,
            $qualification,
            $employment_number,
            $school_id,
            $major_subject,
            $minor_subject
        );
        
        if ($stmt->execute()) {

            // Get ID of newly created user
            $new_user_id = $stmt->insert_id;

            // Admin performing the action
            $admin_id = $_SESSION['user_id'];

            // User IP address
            $ip_address = $_SERVER['REMOTE_ADDR'];

            // Determine action type
            $action = isset($_POST['import_users'])
                ? 'import_user'
                : 'create_user';

            // Description of activity
            $details = "Created {$role} account for {$name} ({$email})";

            // Save activity to audit logs
            $log = $conn->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    target_user_id,
                    action,
                    details,
                    ip_address
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "iisss",
                $admin_id,
                $new_user_id,
                $action,
                $details,
                $ip_address
            );

            $log->execute();
            $log->close();

            // Send account details to user
            send_email(
                $email,
                "Account Created - School Management System",
                "Hello $name,

        Your account has been created.

        Email: $email
        Password: $plain_password
        Role: $role
        Category: $teacher_category

        Please change your password after login."
            );

            return [
                'status' => 'success',
                'message' => 'User created successfully'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Failed to create user'
        ];

}

// ================= CREATE USER =================
if (isset($_POST['create_user'])) {

    $res = createUser(
    $conn,
    $_POST['name'] ?? '',
    $_POST['email'] ?? '',
    $_POST['role'] ?? '',
    $_POST['phone'] ?? '',
    $_POST['gender'] ?? '',
    $_POST['teacher_category'] ?? null,
    $_POST['qualification'] ?? '',
    $_POST['employment_number'] ?? '',
    $_POST['school_id'] ?? null,
    $_POST['major_subject'] ?? null,
    $_POST['minor_subject'] ?? null
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
            $duplicates = 0;

            foreach ($sheet as $i => $row) {

                // Skip header row
                if ($i === 0) {
                    continue;
                }

                // Skip completely empty rows
                if (empty($row[0]) && empty($row[1])) {
                    continue;
                }

                // Normalize Excel row data
                $name  = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $role  = trim($row[2] ?? '');
                $phone = trim($row[3] ?? '');
                $gender = trim($row[4] ?? '');

                $teacher_category = !empty($row[5]) ? trim($row[5]) : null;
                $qualification = !empty($row[6]) ? trim($row[6]) : null;
                $employment_number = !empty($row[7]) ? trim($row[7]) : null;
                $major_subject = !empty($row[8]) ? trim($row[8]) : null;
                $minor_subject = !empty($row[9]) ? trim($row[9]) : null;

                // Validate required fields
                if (empty($name) || empty($email) || empty($role) || empty($gender)) {
                    $failed++;
                    continue;
                }

                // Create user
                $res = createUser(
                    $conn,
                    $name,
                    $email,
                    $role,
                    $phone,
                    $gender,
                    $teacher_category,
                    $qualification,
                    $employment_number,
                    null, // school_id not applicable for bulk import
                    $major_subject,
                    $minor_subject
                );

                if ($res['status'] === 'success') {

                    $success++;

                } else {

                    if ($res['message'] === 'Email already exists') {
                        $duplicates++;
                    } else {
                        $failed++;
                    }

                    error_log("Import failed for {$email}: " . $res['message']);
                }
            }

            // Build message
            $message = "$success user(s) imported successfully.";

            if (!empty($duplicates)) {
                $message .= " $duplicates user(s) already exist.";
            }

            if (!empty($failed)) {
                $message .= " $failed user(s) failed to import.";
            }

            // Alert type
            $message_type = ($failed > 0)
                ? 'warning'
                : 'success';

        } catch (Exception $e) {

            $message = "Import Error: " . $e->getMessage();
            $message_type = 'error';
        }

    } else {

        $message = "Please select a valid Excel file.";
        $message_type = 'error';
    }
}
// Fetch schools for dropdown
$schools_res = $conn->query("SELECT school_id, school_name FROM schools ORDER BY school_name ASC");
$schools = $schools_res ? $schools_res->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

<style>
/* ================= LAYOUT ================= */
.main-content{
    width:100%;
    max-width:1100px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:30px;
    align-items:start;
}

/* ================= CARDS ================= */
.card,
.import-card{
    height:100%;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
    background:#fff;
    border-radius:12px;
    padding:20px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
    transition:0.25s ease;
}

.card:hover,
.import-card:hover{
    transform:translateY(-2px);
}

/* ================= HEADINGS ================= */
.card h3,
.import-card h3{
    font-size:18px;
    margin-bottom:10px;
    color:#0f172a;
}

/* ================= FORM ================= */
.form-container{
    display:flex;
    flex-direction:column;
    gap:12px;
}

.form-group label{
    font-weight:600;
    margin-bottom:4px;
    display:block;
}

/* ================= BUTTONS ================= */
.btn-create{
    margin-top:15px;
    color:#fff;
}

/* ================= IMPORT ================= */
.import-card{
    text-align:center;
}

.import-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;

    height:52px;
    padding:0 24px;

    border-radius:14px;
    border:1px dashed #cbd5e1;

    font-weight:700;
    color:#334155;

    cursor:pointer;
    transition:0.25s ease;

    margin-top:15px;
    background:#fff;
}

.import-btn:hover{
    border-color:#2563eb;
    color:#2563eb;
    background:#f8fafc;
    transform:translateY(-2px);
}

/* ================= TEACHER SECTION ================= */
#teacherFields{
    display:none;
    padding-top:10px;
    border-top:1px solid #e5e7eb;
    margin-top:10px;
}
.spinner{
    width:50px;
    height:50px;
    border:5px solid #ddd;
    border-top:5px solid #2563eb;
    border-radius:50%;
    animation: spin 1s linear infinite;
    margin:auto;
}

@keyframes spin{
    from{
        transform:rotate(0deg);
    }
    to{
        transform:rotate(360deg);
    }
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

            <a href="manage_users.php" class="btn-back">← Back</a>
        </div>

        <!-- ALERT -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="main-content">

            <!-- ================= IMPORT USERS ================= -->
            <div class="card import-card">

                <h3>Import Users</h3>

                <p class="page-subtitle">
                    Upload Excel file with users data (Name, Email, Role, Phone, etc.)
                </p>

                <form method="POST" enctype="multipart/form-data">

                    <input type="hidden" name="import_users" value="1">

                    <input type="file"
                    name="xlsx_file"
                    accept=".xlsx"
                    required
                    style="display:none;"
                    id="fileInput"
                    onchange="this.form.submit();">
                    <label for="fileInput" class="import-btn">
                        📁 Choose Excel File
                    </label>

                    <small style="display:block;margin-top:10px;color:#64748b;">
                        File will be uploaded and processed automatically.
                    </small>
                    <div id="loadingBox" style="display:none;text-align:center;margin-top:20px;">
                        <div class="spinner"></div>
                        <p>Importing users and sending emails. Please wait...</p>
                    </div>

                </form>

            </div>

            <!-- ================= CREATE USER ================= -->
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
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="headteacher">Headteacher</option>
                            <option value="examination_officer">Examination Officer</option>
                        </select>

                    <!-- School Selection -->
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_id" required>
                            <?php foreach ($schools as $sch): ?>
                                <option value="<?php echo htmlspecialchars($sch['school_id']); ?>"><?php echo htmlspecialchars($sch['school_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>

                    <!-- TEACHER DETAILS -->
                    <div id="teacherFields">

                        <div class="form-group">
                            <label>Teacher Category</label>
                            <select name="teacher_category">
                                <option value="">Select Category</option>
                                <option value="Science">Science</option>
                                <option value="Humanities">Humanities</option>
                                <option value="Languages">Languages</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Major Subject</label>
                            <input type="text" name="major_subject" placeholder="e.g. Mathematics">
                        </div>

                        <div class="form-group">
                            <label>Minor Subject</label>
                            <input type="text" name="minor_subject" placeholder="e.g. Computer Studies">
                        </div>

                        <div class="form-group">
                            <label>Qualification</label>
                            <select name="qualification">
                                <option value="">Select Qualification</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Bachelor Degree">Bachelor Degree</option>
                                <option value="Master Degree">Master Degree</option>
                                <option value="PhD">PhD</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Employment Number</label>
                            <input type="text" name="employment_number">
                        </div>

                    </div>

                    <!-- INFO -->
                    <div class="info-popup" style="margin-top:15px;">
                        <h4>Auto Password System</h4>
                        <p>
                            A secure password will be generated automatically and sent to the user's email address.
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

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ================= ROLE HANDLING =================
    const roleSelect = document.getElementById('role');
    const teacherFields = document.getElementById('teacherFields');

    function toggleTeacherFields() {

        const teachingRoles = [
            'teacher',
            'headteacher',
            'examination_officer'
        ];

        if (teachingRoles.includes(roleSelect.value)) {
            teacherFields.style.display = 'block';
        } else {
            teacherFields.style.display = 'none';
        }
    }

    // Run immediately when page loads
    if (roleSelect && teacherFields) {
        toggleTeacherFields();
        roleSelect.addEventListener('change', toggleTeacherFields);
    }


    // ================= IMPORT FILE HANDLING =================
    const fileInput = document.getElementById('fileInput');
    const loadingBox = document.getElementById('loadingBox');

    if (fileInput) {

        fileInput.addEventListener('change', function () {

            if (this.files.length > 0) {

                // Show loading message/spinner
                if (loadingBox) {
                    loadingBox.style.display = 'block';
                }

                // Automatically submit the form
                this.form.submit();
            }

        });

    }

});
</script>
<?php include '../common/footer.php'; ?>

</body>
</html>