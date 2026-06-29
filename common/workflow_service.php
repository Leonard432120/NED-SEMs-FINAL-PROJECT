<?php

function insert_exam_workflow_log($conn, $exam_id, $action, $performed_by, $role, $from_status = null, $to_status = null, $notes = null) {
    $stmt = $conn->prepare(
        "INSERT INTO exam_workflow_logs (exam_id, action, performed_by, role, from_status, to_status, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('isissss', $exam_id, $action, $performed_by, $role, $from_status, $to_status, $notes);
    $stmt->execute();
    $stmt->close();
}

function insert_result_workflow_log($conn, $result_id, $action, $performed_by, $role, $from_status, $to_status, $notes = null) {
    $stmt = $conn->prepare(
        "INSERT INTO result_workflow_logs (result_id, action, performed_by, role, from_status, to_status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('isissss', $result_id, $action, $performed_by, $role, $from_status, $to_status, $notes);
    $stmt->execute();
    $stmt->close();
}

function get_recent_workflow_activity($conn, $school_id, $limit = 7) {
    $sql = "SELECT rwl.*, u.name AS user_name, u.role, e.exam_name
            FROM result_workflow_logs rwl
            LEFT JOIN results r ON rwl.result_id = r.result_id
            LEFT JOIN exams e ON r.exam_id = e.exam_id
            LEFT JOIN users u ON rwl.performed_by = u.user_id
            WHERE r.exam_id IN (SELECT exam_id FROM exams WHERE created_by IN (SELECT user_id FROM users WHERE school_id = ?))
            ORDER BY rwl.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $school_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt->close();
    return $entries;
}
