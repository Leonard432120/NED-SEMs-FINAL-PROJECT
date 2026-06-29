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

$edit_subject = null;
$subject_id = (int)($_GET['edit_id'] ?? 0);

if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = ? LIMIT 1");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $edit_subject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$edit_subject) {
    header('Location: manage_subject.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $paper_type = trim($_POST['paper_type'] ?? 'Theory');
    $status = trim($_POST['status'] ?? 'active');

    if (!$subject_name || !$subject_code) {
        $message = 'Subject name and code are required.';
        $message_type = 'error';
    } else {
        $check = $conn->prepare(
            "SELECT subject_id FROM subjects WHERE LOWER(subject_name) = LOWER(?) AND subject_id != ?"
        );
        $check->bind_param("si", $subject_name, $subject_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $message = 'Subject name already exists.';
            $message_type = 'error';
        } else {
            $check2 = $conn->prepare(
                "SELECT subject_id FROM subjects WHERE LOWER(subject_code) = LOWER(?) AND subject_id != ?"
            );
            $check2->bind_param("si", $subject_code, $subject_id);
            $check2->execute();

            if ($check2->get_result()->num_rows > 0) {
                $message = 'Subject code already exists.';
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE subjects SET subject_name = ?, subject_code = ?, category = ?, paper_type = ?, status = ? WHERE subject_id = ?"
                );
                $stmt->bind_param("sssssi", $subject_name, $subject_code, $category, $paper_type, $status, $subject_id);

                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Subject updated successfully.';
                    $_SESSION['message_type'] = 'success';
                    $stmt->close();
                    $conn->close();
                    header('Location: manage_subject.php');
                    exit();
                } else {
                    $message = 'Failed to update subject.';
                    $message_type = 'error';
                    $stmt->close();
                }
            }
            $check2->close();
        }
        $check->close();
    }

    $edit_subject['subject_name'] = $subject_name;
    $edit_subject['subject_code'] = $subject_code;
    $edit_subject['category'] = $category;
    $edit_subject['paper_type'] = $paper_type;
    $edit_subject['status'] = $status;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Subject</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

<style>
    .content {
        padding: 32px 36px 44px;
        min-height: calc(100vh - 160px);
    }

    .page-header {
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 28px;
    }

    .page-title {
        margin-bottom: 8px;
    }

    .page-subtitle {
        color: #64748b;
        margin-bottom: 0;
        line-height: 1.75;
        max-width: 720px;
    }

    .main-content.single-column {
        width: 100%;
        max-width: 860px;
        margin: 0 auto;
    }

    .card {
        padding: 40px 38px;
        border-radius: 28px;
        background: #ffffff;
        box-shadow: 0 32px 90px rgba(15, 23, 42, 0.10);
        border: 1px solid rgba(148, 163, 184, 0.18);
    }

    .card h3 {
        font-size: 24px;
        margin-bottom: 24px;
        color: #0f172a;
    }

    .form-container {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .form-group label {
        font-size: 15px;
        color: #334155;
        font-weight: 700;
        margin-bottom: 8px;
        display: block;
    }

    .form-group input[type="text"],
    .form-group select {
        width: 100%;
        height: 56px;
        border-radius: 16px;
        border: 1px solid #d1d5db;
        background: #f8fafc;
        padding: 0 18px;
        font-size: 15px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-start;
        margin-top: 12px;
    }

    .btn-create {
        min-width: 180px;
        padding: 13px 20px;
    }

    .alert {
        max-width: 860px;
        margin: 0 auto 22px;
    }

    @media (max-width: 960px) {
        .main-content.single-column {
            max-width: 100%;
        }
    }

    @media (max-width: 768px) {
        .content {
            padding: 20px 18px 26px;
        }

        .page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .page-header .btn-dark {
            width: fit-content;
            align-self: flex-start;
        }
    }
</style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Edit Subject</h2>
        <p class="page-subtitle">Update subject details below.</p>
    </div>
    <a href="manage_subject.php" class="btn btn-dark btn-small">← Back to Subjects</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="main-content single-column">
    <div class="card">
        <h3>Edit Subject</h3>
        <form method="POST" class="form-container">
            <input type="hidden" name="update_subject" value="1">
            
            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name" value="<?= htmlspecialchars($edit_subject['subject_name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="subject_code" value="<?= htmlspecialchars($edit_subject['subject_code'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="science" <?= ($edit_subject['category'] ?? '') === 'science' ? 'selected' : '' ?>>Science</option>
                    <option value="language" <?= ($edit_subject['category'] ?? '') === 'language' ? 'selected' : '' ?>>Language</option>
                    <option value="humanities" <?= ($edit_subject['category'] ?? '') === 'humanities' ? 'selected' : '' ?>>Humanities</option>
                </select>
            </div>

            <div class="form-group">
                <label>Paper Type</label>
                <select name="paper_type" required>
                    <option value="Theory" <?= ($edit_subject['paper_type'] ?? '') === 'Theory' ? 'selected' : '' ?>>Theory</option>
                    <option value="Practical" <?= ($edit_subject['paper_type'] ?? '') === 'Practical' ? 'selected' : '' ?>>Practical</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <option value="active" <?= ($edit_subject['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($edit_subject['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-create btn-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
