<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$message = '';
$message_type = '';

/* ================= CREATE SUBJECT ================= */
function createSubject(
    $conn,
    $subject_name,
    $subject_code,
    $category,
    $paper_type
) {
    $subject_name = trim($subject_name);
    $subject_code = trim($subject_code);

    if (!$subject_name || !$subject_code) {
        return [
            'status' => 'error',
            'message' => 'Subject name and code are required.'
        ];
    }

    /* Duplicate check */
    $check = $conn->prepare("
        SELECT subject_id
        FROM subjects
        WHERE LOWER(subject_name) = LOWER(?)
    ");

    $check->bind_param("s", $subject_name);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        return [
            'status' => 'error',
            'message' => 'Subject already exists.'
        ];
    }

    /* Optional: prevent duplicate codes */
    $checkCode = $conn->prepare("
        SELECT subject_id
        FROM subjects
        WHERE LOWER(subject_code) = LOWER(?)
    ");

    $checkCode->bind_param("s", $subject_code);
    $checkCode->execute();

    if ($checkCode->get_result()->num_rows > 0) {
        return [
            'status' => 'error',
            'message' => 'Subject code already exists.'
        ];
    }

    /* Insert */
    $stmt = $conn->prepare("
        INSERT INTO subjects (
            subject_name,
            status,
            subject_code,
            category,
            paper_type
        )
        VALUES (
            ?, 'active', ?, ?, ?
        )
    ");

    $stmt->bind_param(
        "ssss",
        $subject_name,
        $subject_code,
        $category,
        $paper_type
    );

    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Subject created successfully.'
        ];
    }

    return [
        'status' => 'error',
        'message' => 'Failed to create subject.'
    ];
}

/* ================= HANDLE POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {

    $response = createSubject(
        $conn,
        $_POST['subject_name'] ?? '',
        $_POST['subject_code'] ?? '',
        $_POST['category'] ?? '',
        $_POST['paper_type'] ?? 'Theory'
    );

    $message = $response['message'];
    $message_type = $response['status'];
}

/* ================= FETCH SUBJECTS ================= */
$subjects = $conn->query("
    SELECT *
    FROM subjects
    ORDER BY subject_name
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Subject</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h2 class="page-title">Add New Subject</h2>
            <p class="page-subtitle">Create and manage system subjects</p>
        </div>
        <a href="manage_subject.php" class="btn btn-dark btn-small">← Back</a>
    </div>

    <!-- ALERTS -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="card">

        <h3>Create Subject</h3>

        <form method="POST">

            <input type="hidden" name="create_subject" value="1">

            <!-- SUBJECT NAME -->
            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name" required placeholder="e.g. Mathematics">
            </div>

            <!-- CODE -->
            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="subject_code" required placeholder="e.g. MAT">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="science">Science</option>
                    <option value="language">Language</option>
                    <option value="humanities">Humanities</option>
                </select>
            </div>

            <!-- Paper Type -->
            <div class="form-group">
                <label>Paper Type</label>
                <select name="paper_type" required>
                    <option value="Theory">Theory</option>
                    <option value="Practical">Practical</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-create btn btn-dark">
                    Create Subject
                </button>
            </div>

        </form>

    </div>

    <!-- SUBJECT LIST -->
    <div class="card">

        <h3>Existing Subjects</h3>

        <table class="modern-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Category</th>
                    <th>Paper Type</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($subjects && $subjects->num_rows > 0): ?>

                    <?php $no = 1; // start numbering from 1 ?>

                    <?php while ($s = $subjects->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>  <!-- serial number instead of DB ID -->
                            <td><?= htmlspecialchars($s['subject_name']) ?></td>
                            <td><?= htmlspecialchars($s['subject_code']) ?></td>
                            <td><?= ucfirst($s['category'] ?? '') ?></td>
                            <td><?= htmlspecialchars($s['paper_type'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($s['status']) ?>">
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            No subjects found
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
        </table>

    </div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>