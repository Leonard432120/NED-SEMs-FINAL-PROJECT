<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

// ================= CREATE SCHOOL =================
function createSchool($conn, $school_name, $district, $address) {

    $school_name = trim($school_name);
    $district    = trim($district);
    $address     = trim($address);

    if (!$school_name || !$district) {
        return ['status' => 'error', 'message' => 'Missing required fields'];
    }

    $check = $conn->prepare("SELECT school_id FROM schools WHERE school_name = ? AND district = ?");
    $check->bind_param("ss", $school_name, $district);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        return ['status' => 'error', 'message' => 'School already exists in this district'];
    }

    $division = 'Northern';
    
    $stmt = $conn->prepare("
        INSERT INTO schools (school_name, district, address, division)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $school_name, $district, $address, $division);

    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'School created successfully'];
    }

    return ['status' => 'error', 'message' => 'Failed to create school'];
}

// ================= CREATE SCHOOL =================
if (isset($_POST['create_school'])) {

    $res = createSchool(
        $conn,
        $_POST['school_name'] ?? '',
        $_POST['district'] ?? '',
        $_POST['address'] ?? ''
    );

    $message = $res['message'];
    $message_type = $res['status'];
}

// ================= IMPORT SCHOOLS =================
if (isset($_POST['import_schools'])) {

    if (!empty($_FILES['xlsx_file']['tmp_name'])) {

        try {
            $spreadsheet = IOFactory::load($_FILES['xlsx_file']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet()->toArray();

            $success = 0;
            $failed = 0;

            foreach ($sheet as $i => $row) {

                if ($i === 0) continue;

                $school_name = trim($row[0] ?? '');
                $district    = trim($row[1] ?? '');
                $address     = trim($row[2] ?? '');

                if (!$school_name || !$district) {
                    $failed++;
                    continue;
                }

                $res = createSchool($conn, $school_name, $district, $address);
                ($res['status'] === 'success') ? $success++ : $failed++;
            }

            $message = "$success school(s) imported successfully. $failed failed.";
            $message_type = 'success';

        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$conn->close();
?>
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add School</title>

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
            <h2 class="page-title">Add New School</h2>
            <p class="page-subtitle">Create single or bulk school accounts</p>
        </div>

        <a href="manage_schools.php" class="btn-back">
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

            <h3>Import Schools</h3>
            <p class="page-subtitle">
                Upload Excel file (School Name, District, Address)
            </p>

            <form method="POST" enctype="multipart/form-data" class="import-form">
                <input type="hidden" name="import_schools" value="1">

                <label class="import-label">
                    <input type="file" name="xlsx_file" accept=".xlsx"
                           onchange="this.form.submit()">

                    <span class="import-btn">
                        📁 Choose Excel File
                    </span>
                </label>
            </form>

        </div>

        <!-- RIGHT: CREATE SCHOOL -->
        <div class="card">

            <h3>Create School</h3>

            <form method="POST" class="form-container">
                <input type="hidden" name="create_school" value="1">

                <div class="form-group">
                    <label>School Name</label>
                    <input type="text" name="school_name" required>
                </div>

                <div class="form-group">
                    <label>District</label>
                    <input type="text" name="district" required>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address">
                </div>

                <div class="info-popup">
                    <h4>Information</h4>
                    <p>
                        School information will be stored and can be managed later.
                    </p>
                </div>

                <button type="submit" class="btn btn-create">
                    Create School
                </button>

            </form>

        </div>

    </div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
</body>
</html>