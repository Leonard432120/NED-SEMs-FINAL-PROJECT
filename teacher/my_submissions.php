<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();

// ─── 1. Composed Exam Submissions ────────────────────────────────────────────
// All exams+subjects assigned to this teacher as item_writer (any status)
$sql_composed = "
    SELECT
        e.exam_id,
        es.id           AS exam_subject_id,
        es.subject_id,
        e.exam_name,
        e.status           AS exam_status,
        e.year,
        e.class,
        s.subject_name,
        COUNT(q.question_id)                                                        AS total_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'approved',  1, 0)) AS approved_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'revise',    1, 0)) AS revise_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'rejected',  1, 0)) AS rejected_q,
        SUM(IF(q.question_id IS NOT NULL AND
            (q.moderation_status = 'pending' OR q.moderation_status IS NULL), 1, 0))   AS pending_q
    FROM subject_assignments sa
    INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
    INNER JOIN exams e          ON es.exam_id    = e.exam_id
    INNER JOIN subjects s       ON es.subject_id = s.subject_id
    LEFT  JOIN questions q      ON q.exam_subject_id = es.id
    WHERE sa.teacher_id = ?
      AND sa.role       = 'item_writer'
      AND sa.status     = 'assigned'
    GROUP BY e.exam_id, es.id, es.subject_id, s.subject_name
    ORDER BY e.exam_id DESC, s.subject_name ASC
";
$stmt = $conn->prepare($sql_composed);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$composed_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── 2. Uploaded File Documents ──────────────────────────────────────────────
$sql_docs = "
    SELECT
        d.document_id,
        d.exam_id,
        d.file_path,
        d.version_number,
        d.uploaded_at,
        d.is_current,
        e.exam_name,
        e.status  AS exam_status,
        s.subject_name
    FROM exam_documents d
    JOIN exams e       ON d.exam_id    = e.exam_id
    JOIN exam_subjects es ON e.exam_id = es.exam_id
    JOIN subjects s    ON es.subject_id = s.subject_id
    WHERE d.uploaded_by = ?
    GROUP BY d.document_id
    ORDER BY d.uploaded_at DESC
";
$stmt = $conn->prepare($sql_docs);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$uploaded_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// ─── Helpers ─────────────────────────────────────────────────────────────────
function exam_file_url(string $path): string {
    if (empty($path)) return '#';
    $base = basename($path);
    return BASE_URL . '/static/uploads/exams/' . rawurlencode($base);
}

function exam_status_label(string $status): string {
    return match ($status) {
        'draft'            => 'Draft',
        'assigned'         => 'Assigned',
        'submitted'        => 'Submitted',
        'under_moderation' => 'Under Moderation',
        'needs_revision'   => 'Needs Revision',
        'approved'         => 'Approved',
        'rejected'         => 'Rejected',
        default            => ucfirst(str_replace('_', ' ', $status)),
    };
}

function exam_status_cls(string $status): string {
    return 'es-badge es-badge--' . str_replace('_', '-', $status);
}

/**
 * Derives a per-teacher "writer status" from the question moderation
 * counts already aggregated in $ex (total_q, approved_q, rejected_q,
 * revise_q, pending_q). Falls back to the exam's own status when there
 * are no questions yet, or when the row doesn't carry those keys.
 */
function derive_writer_submission_status(array $ex): string {
    $total    = (int)($ex['total_q']    ?? 0);
    $approved = (int)($ex['approved_q'] ?? 0);
    $rejected = (int)($ex['rejected_q'] ?? 0);
    $revise   = (int)($ex['revise_q']   ?? 0);
    $pending  = (int)($ex['pending_q']  ?? 0);

    if ($total === 0) {
        return $ex['exam_status'] ?? 'draft';
    }
    if ($rejected > 0)        return 'rejected';
    if ($revise > 0)          return 'needs_revision';
    if ($approved === $total) return 'approved';
    if ($pending > 0)         return 'under_moderation';

    return $ex['exam_status'] ?? 'draft';
}

// ─── Summary counts ──────────────────────────────────────────────────────────
$total_composed   = count($composed_exams);
$total_submitted  = count(array_filter($composed_exams, fn($r) => !in_array($r['exam_status'], ['draft','assigned'])));
$total_approved   = count(array_filter($composed_exams, fn($r) => $r['exam_status'] === 'approved'));
$total_docs       = count($uploaded_docs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Submissions | NED-SEMS Teacher Portal</title>
<meta name="description" content="View all your composed exam submissions and uploaded exam documents.">
<?php
$portal_title = 'NED-SEMS | My Submissions';
$module_css   = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<style>
/* ── Page-level tokens (reference globals) ─────── */
:root {
    --surface  : var(--card-color,      #ffffff);
    --bg       : var(--background-color,#f4f6f8);
    --border   : var(--border-color,    #e2e8f0);
    --text     : var(--text-color,      #1e293b);
    --muted    : var(--text-muted,      #64748b);
    --accent   : var(--info-color,      #3b82f6);
}

/* ── Summary stat cards ────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card-s {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: box-shadow .15s;
}
.stat-card-s:hover { box-shadow: 0 6px 20px rgba(0,0,0,.07); }
.stat-card-s .sc-num {
    font-size: 28px;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
}
.stat-card-s .sc-lbl {
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.stat-card-s.accent .sc-num { color: var(--accent); }
.stat-card-s.success .sc-num { color: #16a34a; }
.stat-card-s.warn    .sc-num { color: #d97706; }

/* ── Tabs ──────────────────────────────────────── */
.tab-bar {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--border);
    margin-bottom: 20px;
}
.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    font-size: 14px;
    font-weight: 600;
    color: var(--muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    border-radius: 6px 6px 0 0;
    transition: color .15s, border-color .15s, background .15s;
    font-family: inherit;
}
.tab-btn:hover { color: var(--accent); background: rgba(59,130,246,.05); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); background: rgba(59,130,246,.06); }
.tab-count {
    display: inline-block;
    background: var(--border);
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    border-radius: 999px;
    padding: 1px 7px;
    margin-left: 6px;
}
.tab-btn.active .tab-count { background: rgba(59,130,246,.15); color: var(--accent); }

.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ── Exam status badges (override globals for exam flow) ── */
.es-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.es-badge--draft            { background: #fef3c7; color: #92400e; }
.es-badge--assigned         { background: #dbeafe; color: #1d4ed8; }
.es-badge--composed           { background: #e0f2fe; color: #0369a1; }
.es-badge--submitted        { background: #e0f2fe; color: #0369a1; }
.es-badge--under-moderation { background: #ede9fe; color: #6d28d9; }
.es-badge--needs-revision   { background: #fef9c3; color: #854d0e; }
.es-badge--approved         { background: #dcfce7; color: #166534; }
.es-badge--rejected         { background: #fee2e2; color: #991b1b; }

/* ── Progress bar (question moderation) ─────────── */
.q-progress { display: flex; align-items: center; gap: 8px; }
.q-bar {
    flex: 1;
    height: 6px;
    background: var(--border);
    border-radius: 999px;
    overflow: hidden;
    min-width: 60px;
}
.q-bar-fill { height: 100%; border-radius: 999px; transition: width .3s; }
.q-bar-fill.full   { background: #22c55e; }
.q-bar-fill.partial{ background: #f59e0b; }
.q-bar-fill.none   { background: var(--border); }
.q-label { font-size: 11px; color: var(--muted); white-space: nowrap; }

/* ── File icon ──────────────────────────────────── */
.file-cell { display: flex; align-items: center; gap: 8px; }
.file-icon {
    width: 32px; height: 32px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: #1d4ed8;
    flex-shrink: 0;
}
.file-name { font-size: 13px; color: var(--text); font-weight: 500; word-break: break-all; }
.file-meta { font-size: 11px; color: var(--muted); margin-top: 1px; }

/* ── "Not submitted" notice ──────────────────────── */
.ns-notice {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--muted);
    font-style: italic;
}

/* ── Empty state ─────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state .es-icon {
    width: 56px; height: 56px;
    background: var(--bg);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px;
    font-size: 20px; font-weight: 800; color: var(--muted);
}
.empty-state p { font-size: 14px; margin-bottom: 16px; }

/* ── Version chip ────────────────────────────────── */
.version-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid var(--border);
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
}
.version-chip.current { background: #dcfce7; border-color: #bbf7d0; color: #166534; }

/* ── Responsive ───────────────────────────────────── */
@media (max-width: 700px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    table th:nth-child(3),
    table td:nth-child(3),
    table th:nth-child(5),
    table td:nth-child(5) { display: none; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
<?php include __DIR__ . '/../common/sidebar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">My Submissions</h1>
            <p class="stats-info">All your composed exam submissions and uploaded exam documents.</p>
        </div>
        <a href="assigned_exams.php" class="btn btn-dark">Back to My Exams</a>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card-s">
            <span class="sc-num"><?= $total_composed ?></span>
            <span class="sc-lbl">Assigned Exams</span>
        </div>
        <div class="stat-card-s accent">
            <span class="sc-num"><?= $total_submitted ?></span>
            <span class="sc-lbl">Submitted for Review</span>
        </div>
        <div class="stat-card-s success">
            <span class="sc-num"><?= $total_approved ?></span>
            <span class="sc-lbl">Fully Approved</span>
        </div>
        <div class="stat-card-s warn">
            <span class="sc-num"><?= $total_docs ?></span>
            <span class="sc-lbl">Uploaded Documents</span>
        </div>
    </div>

    <div class="card" style="padding:24px;">

        <!-- Tab Bar -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab(event,'tab-composed')" id="btn-composed">
                Composed Exams
                <span class="tab-count"><?= $total_composed ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event,'tab-docs')" id="btn-docs">
                Uploaded Documents
                <span class="tab-count"><?= $total_docs ?></span>
            </button>
        </div>

        <!-- ═══ TAB 1: Composed Exams ═══════════════════════════════════════ -->
        <div class="tab-pane active" id="tab-composed">
            <?php if (empty($composed_exams)): ?>
                <div class="empty-state">
                    <div class="es-icon">EX</div>
                    <p>You have no assigned exams yet.</p>
                    <a href="assigned_exams.php" class="btn btn-dark btn-small">View Assigned Exams</a>
                </div>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Year / Class</th>
                            <th>Questions</th>
                            <th>Moderation Progress</th>
                            <th>Status</th>
                            <th style="width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($composed_exams as $ex):
                            $total_q   = (int)$ex['total_q'];
                            $approved  = (int)$ex['approved_q'];
                            $pending   = (int)$ex['pending_q'];
                            $pct       = $total_q > 0 ? round($approved / $total_q * 100) : 0;
                            $bar_cls   = $pct === 100 ? 'full' : ($pct > 0 ? 'partial' : 'none');
                            $writer_status = $ex['writer_status'] ?? derive_writer_submission_status($ex);
                            $is_locked = in_array($writer_status, ['submitted','under_moderation','approved','needs_revision','rejected']);
                        ?>                        <tr>
                            <td>
                                <strong style="color:var(--text);font-size:13px;"><?= htmlspecialchars($ex['exam_name']) ?></strong>
                            </td>
                            <td style="font-size:13px; color:var(--muted);"><?= htmlspecialchars($ex['subject_name']) ?></td>
                            <td style="font-size:13px;">
                                <?= htmlspecialchars($ex['year'] ?? '—') ?>
                                <?php if ($ex['class']): ?> / Form <?= htmlspecialchars($ex['class']) ?><?php endif; ?>
                            </td>
                            <td style="font-size:13px; font-weight:600; color:var(--text);"><?= $total_q ?></td>
                            <td>
                                <?php if ($total_q > 0): ?>
                                <div class="q-progress">
                                    <div class="q-bar">
                                        <div class="q-bar-fill <?= $bar_cls ?>" style="width:<?= $pct ?>%;"></div>
                                    </div>
                                    <span class="q-label">
                                        <?= $approved ?>/<?= $total_q ?> approved
                                        <?php if ($pending > 0): ?> · <?= $pending ?> pending<?php endif; ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                    <span class="q-label">No questions yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= exam_status_cls($writer_status) ?>">
                                    <?= exam_status_label($writer_status) ?>
                                </span>
                            </td>                            <td class="actions">
                                <?php if ($is_locked): ?>
                                    <a href="compose_exam.php?exam_id=<?= $ex['exam_id'] ?>&subject_id=<?= $ex['subject_id'] ?>&exam_subject_id=<?= $ex['exam_subject_id'] ?>" class="btn btn-dark btn-small">View Questions</a>
                                <?php else: ?>
                                    <a href="compose_exam.php?exam_id=<?= $ex['exam_id'] ?>&subject_id=<?= $ex['subject_id'] ?>&exam_subject_id=<?= $ex['exam_subject_id'] ?>" class="btn btn-teal btn-small">Compose</a>
                                <?php endif; ?>
                                <?php if ($total_q > 0): ?>
                                    <a href="exam.php?action=download&id=<?= $ex['exam_id'] ?>&subject_id=<?= $ex['subject_id'] ?>" class="btn btn-success btn-small">PDF</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══ TAB 2: Uploaded Documents ═══════════════════════════════════ -->
        <div class="tab-pane" id="tab-docs">
            <?php if (empty($uploaded_docs)): ?>
                <div class="empty-state">
                    <div class="es-icon">UP</div>
                    <p>You have not uploaded any exam documents yet.</p>
                    <a href="assigned_exams.php" class="btn btn-dark btn-small">Go to My Exams</a>
                </div>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>File</th>
                            <th>Subject</th>
                            <th>Version</th>
                            <th>Uploaded At</th>
                            <th>Exam Status</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploaded_docs as $doc):
                            $ext      = strtoupper(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            $fname    = basename($doc['file_path']);
                            $url      = exam_file_url($doc['file_path']);
                            $isCurrent = (bool)$doc['is_current'];
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--text);font-size:13px;"><?= htmlspecialchars($doc['exam_name']) ?></strong>
                            </td>
                            <td>
                                <div class="file-cell">
                                    <div class="file-icon"><?= $ext ?: 'F' ?></div>
                                    <div>
                                        <div class="file-name"><?= htmlspecialchars($fname) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:13px; color:var(--muted);"><?= htmlspecialchars($doc['subject_name']) ?></td>
                            <td>
                                <span class="version-chip <?= $isCurrent ? 'current' : '' ?>">
                                    v<?= (int)$doc['version_number'] ?><?= $isCurrent ? ' (current)' : '' ?>
                                </span>
                            </td>
                            <td style="font-size:12px; color:var(--muted);">
                                <?= date('d M Y, H:i', strtotime($doc['uploaded_at'])) ?>
                            </td>
                            <td>
                                <span class="<?= exam_status_cls($doc['exam_status']) ?>">
                                    <?= exam_status_label($doc['exam_status']) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="btn btn-dark btn-small">Open</a>
                                <a href="<?= htmlspecialchars($url) ?>" download="<?= htmlspecialchars($fname) ?>" class="btn btn-teal btn-small">Download</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /card -->

</div><!-- /main-content -->
</div><!-- /dashboard -->

<?php include __DIR__ . '/../common/footer.php'; ?>

<script>
function switchTab(e, paneId) {
    // Deactivate all
    document.querySelectorAll('.tab-btn').forEach(b  => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    // Activate clicked
    e.currentTarget.classList.add('active');
    document.getElementById(paneId).classList.add('active');
}
</script>
</body>
</html>