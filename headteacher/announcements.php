<?php
session_start();
header("Location: ../common/announcements.php");
exit();
?>

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $term = trim($_POST['term'] ?? 'General');
        $year = (int)($_POST['year'] ?? date('Y'));
        $comments = trim($_POST['comments'] ?? '');
        if ($comments === '') {
            $error = 'Announcement message is required.';
        } else {
    $stmt = $conn->prepare("
        INSERT INTO comments
        (
            user_id,
            school_id,
            comment_type,
            term,
            year,
            comment
        )
        VALUES
        (
            ?, ?, 'school_note', ?, ?, ?
        )
    ");

    $stmt->bind_param(
        "iisis",
        $user_id,
        $school_id,
        $term,
        $year,
        $comments
    );
            if ($stmt->execute()) {
                $message = 'Announcement published successfully.';
            } else {
                $error = 'Could not save announcement.';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete' && !empty($_POST['note_id'])) {
        $note_id = (int)$_POST['note_id'];        $conn->query("DELETE FROM comments WHERE comment_id = $note_id AND user_id = $user_id");   
        $message = 'Announcement removed.';
    }
}

$announcements = $conn->query("
    SELECT
        c.comment_id,
        c.comment,
        c.comment_type,
        c.term,
        c.year,
        c.created_at,
        u.name AS author
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE c.school_id = $school_id
      AND c.comment_type = 'school_note'
    ORDER BY c.created_at DESC
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">School Announcements</h1>
        <p class="stats-info">Publish notices to teachers, examination officers, and staff.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel-grid">
    <div class="section">
        <h3>Create Announcement</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="term">Term / Category</label>
                <input type="text" id="term" name="term" placeholder="e.g. Term 1, Urgent, Staff Meeting" required>
            </div>
            <div class="form-group">
                <label for="year">Year</label>
                <input type="number" id="year" name="year" value="<?= date('Y') ?>" min="2020" max="2099" required>
            </div>
            <div class="form-group">
                <label for="comments">Message</label>
                <textarea id="comments" name="comments" rows="5" placeholder="Write your announcement..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Publish Announcement</button>
        </form>
    </div>

    <div class="section">
        <h3>Published Notices</h3>
        <div class="announcement-list">
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while ($a = $announcements->fetch_assoc()): ?>
                    <div class="announcement-item">
                        <time>
                            <?= date('d M Y', strtotime($a['created_at'])) ?>
                            · <?= htmlspecialchars($a['term'] ?? '') ?>
                              <?= (int)($a['year'] ?? 0) ?>
                        </time>

                        <p>
                            <?= nl2br(htmlspecialchars($a['comment'])) ?>
                        </p>
                        <small class="muted-text">By <?= htmlspecialchars($a['author'] ?? 'Headteacher') ?></small>
                        <form id="htAnnDeleteForm<?= (int)$a['comment_id'] ?>" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="note_id" value="<?= (int)$a['comment_id'] ?>">
                        </form>
                        <button type="button" class="btn btn-small btn-danger" style="margin-top:10px;"
                                onclick="openHtAnnDelete('htAnnDeleteForm<?= (int)$a['comment_id'] ?>')">
                            Delete
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-state">No announcements published yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>

<script>
function openHtAnnDelete(formId) {
    var modal = document.getElementById('globalDeleteModal');
    var confirmBtn = document.getElementById('globalDeleteConfirmBtn');
    if (!modal || !confirmBtn) return;

    confirmBtn.removeAttribute('href');
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        var f = document.getElementById(formId);
        if (f) f.submit();
    };
    modal.classList.add('show');
}
</script>
