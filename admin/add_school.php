<?php
/**
 * add_school.php - Professional version with Headteacher Dropdown
 */

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

// ================= AUDIT LOG =================
function log_activity($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address)
        VALUES (?, NULL, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// ================= FETCH AVAILABLE HEADTEACHERS =================
$available_headteachers = [];
$stmt = $conn->prepare("
    SELECT user_id, name 
    FROM users 
    WHERE role = 'headteacher' 
      AND (school_id IS NULL OR school_id = 0)
    ORDER BY name ASC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $available_headteachers[] = $row;
}
$stmt->close();

// ================= CREATE SCHOOL FUNCTION =================
function createSchool($conn, $data, $admin_id) {
    $school_name      = trim($data['school_name'] ?? '');
    $district         = trim($data['district'] ?? '');
    $cluster_name     = trim($data['cluster_name'] ?? '');
    $school_number    = trim($data['school_number'] ?? '');
    $school_type      = trim($data['school_type'] ?? '');
    $phone            = trim($data['phone'] ?? '');
    $email            = trim($data['email'] ?? '');
    $address          = trim($data['address'] ?? '');

    $headteacher_id   = !empty($data['headteacher_id']) ? (int)$data['headteacher_id'] : null;
    $headteacher_name = '';

    if (!$school_name || !$district) {
        return ['status' => 'error', 'message' => 'School name and district are required'];
    }

    // Duplicate check
    $check = $conn->prepare("SELECT school_id FROM schools WHERE school_name = ? AND district = ?");
    $check->bind_param("ss", $school_name, $district);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return ['status' => 'error', 'message' => 'School already exists in this district'];
    }

    // Get headteacher name
    if ($headteacher_id) {
        $hstmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
        $hstmt->bind_param("i", $headteacher_id);
        $hstmt->execute();
        $hstmt->bind_result($headteacher_name);
        $hstmt->fetch();
        $hstmt->close();
    }

    $stmt = $conn->prepare("
        INSERT INTO schools (
            school_name, district, cluster_name, school_number, school_type,
            phone, email, headteacher_name, address, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $stmt->bind_param("sssssssss",
        $school_name, $district, $cluster_name, $school_number,
        $school_type, $phone, $email, $headteacher_name, $address
    );

    if ($stmt->execute()) {
        $new_school_id = $stmt->insert_id;

        // Assign school to headteacher
        if ($headteacher_id) {
            $update = $conn->prepare("UPDATE users SET school_id = ? WHERE user_id = ?");
            $update->bind_param("ii", $new_school_id, $headteacher_id);
            $update->execute();
            $update->close();
        }

        $details = "Created school: $school_name ($district)" . 
                   ($headteacher_id ? " | Assigned Headteacher" : "");
        log_activity($conn, $admin_id, "create_school", $details);

        return ['status' => 'success', 'message' => 'School created successfully'];
    }

    return ['status' => 'error', 'message' => 'Failed to create school'];
}

// ================= HANDLE FORMS =================
if (isset($_POST['create_school'])) {
    $res = createSchool($conn, $_POST, $_SESSION['user_id']);
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
            $duplicates = 0;

            foreach ($sheet as $i => $row) {
                // Skip header row
                if ($i === 0) continue;

                // Skip empty rows
                if (empty($row[0]) && empty($row[1])) continue;

                $schoolData = [
                    'school_name'   => trim($row[0] ?? ''),
                    'district'      => trim($row[1] ?? ''),
                    'cluster_name'  => trim($row[2] ?? ''),
                    'school_number' => trim($row[3] ?? ''),
                    'school_type'   => trim($row[4] ?? ''),
                    'phone'         => trim($row[5] ?? ''),
                    'email'         => trim($row[6] ?? ''),
                    'address'       => trim($row[7] ?? '')
                ];

                // Validate mandatory fields
                if (empty($schoolData['school_name']) || empty($schoolData['district'])) {
                    $failed++;
                    continue;
                }

                $res = createSchool($conn, $schoolData, $_SESSION['user_id']);

                if ($res['status'] === 'success') {
                    $success++;
                } else {
                    if (strpos($res['message'], 'already exists') !== false) {
                        $duplicates++;
                    } else {
                        $failed++;
                    }
                }
            }

            $message = "$success school(s) imported successfully.";
            if ($duplicates > 0) {
                $message .= " $duplicates school(s) already exist.";
            }
            if ($failed > 0) {
                $message .= " $failed school record(s) failed.";
            }
            $message_type = ($failed > 0) ? 'warning' : 'success';

        } catch (Exception $e) {
            $message = "Import Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Please select a valid Excel or CSV file.";
        $message_type = 'error';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add School</title>

    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

    <style>
        .main-content{
            width:100%;
            max-width:1100px;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:30px;
            align-items:start;
        }

        .card, .import-card{
            height:100%;
            display:flex;
            flex-direction:column;
            background:#fff;
            border-radius:12px;
            padding:25px;
            box-shadow:0 4px 10px rgba(0,0,0,0.05);
        }

        .card h3, .import-card h3{
            font-size:18px;
            margin-bottom:12px;
            color:#0f172a;
        }

        .form-container{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .form-group label{
            font-weight:600;
            margin-bottom:5px;
            display:block;
        }

        .btn-create{
            margin-top:15px;
        }

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
            background:#fff;
        }

        .import-btn:hover{
            border-color:#2563eb;
            color:#2563eb;
            background:#f8fafc;
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
                <p class="page-subtitle">Create single or bulk school records</p>
            </div>
            <a href="manage_schools.php" class="btn-back">← Back</a>
        </div>

        <!-- ALERT -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="main-content">

            <!-- IMPORT CARD -->
            <div class="card import-card">
                <h3>Import Schools</h3>
                <p class="page-subtitle">
                    Upload Excel or CSV file with school data
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="import_schools" value="1">

                    <input type="file" name="xlsx_file" accept=".xlsx,.csv" 
                           id="fileInput" style="display:none;" 
                           onchange="this.form.submit();">

                    <label for="fileInput" class="import-btn">
                        📁 Choose Excel / CSV File
                    </label>
                </form>
            </div>

            <!-- CREATE SCHOOL CARD -->
            <div class="card">
                <h3>Create School</h3>

                <form method="POST" class="form-container">
                    <input type="hidden" name="create_school" value="1">

                    <div class="form-group">
                        <label>School Name <span style="color:red;">*</span></label>
                        <input type="text" name="school_name" required>
                    </div>

                    <div class="form-group">
                        <label>District <span style="color:red;">*</span></label>
                        <select name="district" required>
                            <option value="">Select District</option>
                            <option value="Chitipa">Chitipa</option>
                            <option value="Karonga">Karonga</option>
                            <option value="Rumphi">Rumphi</option>
                            <option value="Mzimba">Mzimba</option>
                        </select>
                    </div>

                    <!-- HEADTEACHER DROPDOWN -->
                    <div class="form-group">
                        <label>Headteacher</label>
                        <select name="headteacher_id">
                            <option value="">-- Select Headteacher (Optional) --</option>
                            <?php foreach ($available_headteachers as $ht): ?>
                                <option value="<?= $ht['user_id'] ?>">
                                    <?= htmlspecialchars($ht['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (empty($available_headteachers)): ?>
                                <option value="" disabled>No unassigned headteachers available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cluster Name</label>
                        <input type="text" name="cluster_name">
                    </div>

                    <div class="form-group">
                        <label>School Number</label>
                        <input type="text" name="school_number">
                    </div>

                    <div class="form-group">
                        <label>School Type</label>
                        <select name="school_type">
                            <option value="">Select Type</option>
                            <option value="CDSS">CDSS</option>
                            <option value="DAY SECONDARY">Day Secondary</option>
                            <option value="BOARDING">Boarding</option>
                            <option value="PRIVATE">Private</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-create">Create School</button>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>