<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;

$message = '';
$message_type = '';

// Can publish? (EDM/admin, headteacher, examination officer)
$can_publish = in_array($role, ['admin', 'headteacher', 'examination_officer']);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'publish' && $can_publish) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (empty($title) || empty($content)) {
            $message = "All fields are required.";
            $message_type = "error";
        } else {
            // Admin posts globally (NULL school_id), others post to their school
            $target_school_id = ($role === 'admin') ? null : $school_id;
            
            $stmt = $conn->prepare("
                INSERT INTO announcements (title, content, published_by, school_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssii", $title, $content, $user_id, $target_school_id);
            if ($stmt->execute()) {
                $message = "Announcement published successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to publish announcement: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $announcement_id = (int)$_POST['announcement_id'];
        
        if ($role === 'admin') {
            // Admin can delete any announcement
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->bind_param("i", $announcement_id);
        } else {
            // Others can only delete their own
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND published_by = ?");
            $stmt->bind_param("ii", $announcement_id, $user_id);
        }
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Announcement deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to delete announcement or unauthorized.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Fetch announcements
$announcements = [];
if ($role === 'admin') {
    $res = $conn->query("
        SELECT a.*, u.name AS author_name, u.role AS author_role, s.school_name
        FROM announcements a
        LEFT JOIN users u ON a.published_by = u.user_id
        LEFT JOIN schools s ON a.school_id = s.school_id
        ORDER BY a.created_at DESC
    ");
} else {
    // Fetch global (school_id is NULL) and school-specific announcements
    $stmt = $conn->prepare("
        SELECT a.*, u.name AS author_name, u.role AS author_role, s.school_name
        FROM announcements a
        LEFT JOIN users u ON a.published_by = u.user_id
        LEFT JOIN schools s ON a.school_id = s.school_id
        WHERE a.school_id IS NULL OR a.school_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
}

while ($row = $res->fetch_assoc()) {
    $announcements[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements & Bulletins</title>
    <?php include __DIR__ . '/head_assets.php'; ?>
    <style>
        .announcement-card {
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
        }
        .announcement-card .meta {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .announcement-card .meta .tag {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .announcement-card .meta .tag.global {
            background: #dbeafe;
            color: #1e40af;
        }
        .announcement-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            color: #0f172a;
        }
        .announcement-card p {
            line-height: 1.6;
            color: #334155;
            margin: 0;
            white-space: pre-wrap;
        }
        .announcement-card .delete-btn-container {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
            color: #334155;
        }
        .form-row input, .form-row textarea {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 10px;
            font-size: 15px;
        }
        .form-row textarea {
            resize: vertical;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Announcements & Notices</h2>
                <p class="page-subtitle">Stay updated with general and school-specific broadcasts.</p>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- PUBLISH FORM -->
        <?php if ($can_publish): ?>
            <div class="card" style="margin-bottom: 30px;">
                <h3>Publish New Announcement</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="publish">
                    
                    <div class="form-row">
                        <label>Title <span style="color:red;">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. End of Term Notice or Timetable Update">
                    </div>
                    
                    <div class="form-row">
                        <label>Message Content <span style="color:red;">*</span></label>
                        <textarea name="content" rows="4" required placeholder="Write the announcement message details here..."></textarea>
                    </div>

                    <div style="text-align:right;">
                        <button type="submit" class="btn btn-dark">Publish Announcement</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- ANNOUNCEMENTS LIST -->
        <div class="card">
            <h3>Recent Bulletins</h3>
            
            <?php if (empty($announcements)): ?>
                <p class="empty-state">No announcements published yet.</p>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="announcement-card">
                        
                        <div class="meta">
                            <span><?= date('F j, Y, g:i a', strtotime($ann['created_at'])) ?></span>
                            
                            <!-- Global vs School Tag -->
                            <?php if ($ann['school_id'] === null): ?>
                                <span class="tag global">Global Bulletin</span>
                            <?php else: ?>
                                <span class="tag">School Notice (<?= htmlspecialchars($ann['school_name'] ?? 'Your School') ?>)</span>
                            <?php endif; ?>
                            
                            <span>By: <strong><?= htmlspecialchars($ann['author_name'] ?? 'System') ?></strong> (<?= ucfirst(htmlspecialchars($ann['author_role'] ?? '')) ?>)</span>
                        </div>

                        <h3><?= htmlspecialchars($ann['title']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($ann['content'])) ?></p>

                        <!-- Delete Button for authorized users -->
                        <?php if ($role === 'admin' || (int)$ann['published_by'] === $user_id): ?>
                            <div class="delete-btn-container">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                    <button type="submit" class="btn btn-delete btn-small" style="padding: 4px 8px; font-size: 0.8rem;">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>

</body>
</html>
