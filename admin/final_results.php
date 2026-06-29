<?php
require_once __DIR__ . '/../teacher/teacher_init.php';

$conn = get_db_connection();

/* ================= FILTERS ================= */

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class = isset($_GET['class']) ? $_GET['class'] : '';

/* ================= LOAD EXAMS ================= */

$exams = $conn->query("
    SELECT exam_id, exam_name, class
    FROM exams
    ORDER BY exam_date DESC
");

/* ================= LOAD RESULTS ================= */

$results = [];

if ($exam_id > 0) {

    $sql = "
        SELECT 
            r.*,
            s.name,
            s.exam_number,
            s.class,
            sc.school_name
        FROM results r

        JOIN students s
            ON r.student_id = s.student_id

        LEFT JOIN schools sc
            ON s.school_id = sc.school_id

        WHERE r.exam_id = ?
    ";

    if (!empty($class)) {
        $sql .= " AND s.class = ? ";
    }

    $sql .= " ORDER BY r.position_in_class ASC";

    $stmt = $conn->prepare($sql);

    if (!empty($class)) {
        $stmt->bind_param("is", $exam_id, $class);
    } else {
        $stmt->bind_param("i", $exam_id);
    }

    $stmt->execute();
    $results = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Final Results</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>

<body>

<div class="header">
    <div class="dashboard-title">FINAL RESULTS DASHBOARD</div>
</div>

<div class="dashboard">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content">

        <div class="page-header">
            <h2 class="page-title">Published Results</h2>
        </div>

        <!-- FILTERS -->
        <div class="card">

            <form method="GET" class="search-form">

                <select name="exam_id" required>
                    <option value="">Select Exam</option>
                    <?php while($e = $exams->fetch_assoc()): ?>
                        <option value="<?= $e['exam_id'] ?>"
                            <?= $exam_id == $e['exam_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['exam_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="text" name="class" placeholder="Class (optional)" value="<?= htmlspecialchars($class) ?>">

                <button type="submit">Filter</button>

                <a href="final_results.php?exam_id=<?= $exam_id ?>" class="btn-small btn-dark">
                    Reset
                </a>

            </form>

        </div>

        <!-- TABLE -->
        <?php if ($exam_id > 0): ?>

        <div class="card">

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Pos</th>
                            <th>Name</th>
                            <th>Exam No</th>
                            <th>School</th>
                            <th>Class</th>
                            <th>Total</th>
                            <th>%</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($row = $results->fetch_assoc()): ?>

                        <tr>

                            <td><?= $row['position_in_class'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['exam_number']) ?></td>
                            <td><?= htmlspecialchars($row['school_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['class']) ?></td>
                            <td><?= $row['total_score'] ?></td>
                            <td><?= $row['percentage'] ?>%</td>
                            <td>
                                <span class="badge badge-active">
                                    <?= $row['grade'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $row['status'] == 'published' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <?php endif; ?>

    </div>
</div>

</body>
</html>