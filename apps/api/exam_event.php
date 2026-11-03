<?php
// apps/api/exam_event.php
include '../config/conn.php';
include '../config/helpers.php';

if (!function_exists('jsonOut')) {
    function jsonOut($status, $dataOrMsg) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => (bool)$status,
            'data'   => $dataOrMsg
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    jsonOut(false, "Action is required");
}

$action = $_POST['action'];

function nullable($v) {
    return ($v === '' || $v === null) ? null : $v;
}

if ($action === 'list') {
    $year_id = $_POST['academic_year_id'] ?? null;
    $sem_id  = $_POST['semester_id'] ?? null;
    $term_id = $_POST['term_id'] ?? null;
    $campus_id = $_POST['campus_id'] ?? null;
    $shift_id  = $_POST['shift_id'] ?? null;
    $class_id  = $_POST['class_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;

    $where = [];
    $types = '';
    $vals  = [];

    if ($year_id !== null && $year_id !== '') {
        $where[] = "(e.academic_year_id IS NULL OR e.academic_year_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$year_id;
    }
    if ($sem_id !== null && $sem_id !== '') {
        $where[] = "(e.semester_id IS NULL OR e.semester_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$sem_id;
    }
    if ($term_id !== null && $term_id !== '') {
        $where[] = "(e.term_id IS NULL OR e.term_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$term_id;
    }
    if ($campus_id !== null && $campus_id !== '') {
        $where[] = "(e.campus_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$campus_id;
    }
    if ($shift_id !== null && $shift_id !== '') {
        $where[] = "(e.shift_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$shift_id;
    }
    if ($class_id !== null && $class_id !== '') {
        $where[] = "(e.class_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$class_id;
    }
    if ($subject_id !== null && $subject_id !== '') {
        $where[] = "(e.subject_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$subject_id;
    }

    $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT 
                e.id, e.exam_date, e.duration_minutes, e.status, e.shift_id, e.campus_id,
                c.code AS class_code,
                s.subject_name AS subject_name,
                sh.name AS shift_name,
                cp.name AS campus_name
            FROM exam_event e
            LEFT JOIN `class` c ON c.id = e.class_id
            LEFT JOIN subject s ON s.id = e.subject_id
            LEFT JOIN shift sh ON sh.id = e.shift_id
            LEFT JOIN campus cp ON cp.id = e.campus_id
            $wsql
            ORDER BY e.exam_date ASC, c.code ASC";

    if ($wsql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    if (!$res) { jsonOut(false, $conn->error); }
    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    jsonOut(true, $out);
}

if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) jsonOut(false, "Missing id");

    $exam_date = nullable($_POST['exam_date'] ?? null);
    $shift_id  = nullable($_POST['shift_id'] ?? null);

    $sql = "UPDATE exam_event SET exam_date=?, shift_id=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters: exam_date (string/null), shift_id (int/null), id (int)
    $stmt->bind_param("sii", $exam_date, $shift_id, $id);

    if ($stmt->execute()) {
        jsonOut(true, "Updated");
    } else {
        jsonOut(false, $conn->error);
    }
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) jsonOut(false, "Missing id");
    
    $stmt = $conn->prepare("DELETE FROM exam_event WHERE id=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        jsonOut(true, "Deleted");
    } else {
        jsonOut(false, $conn->error);
    }
}

if ($action === 'bulk_update') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) jsonOut(false, "No events selected.");

    $exam_date = nullable($_POST['exam_date'] ?? null);
    $shift_id  = nullable($_POST['shift_id'] ?? null);

    $id_list = array_map('intval', $ids);
    $id_string = implode(',', $id_list);

    $sql = "UPDATE exam_event SET exam_date=?, shift_id=? WHERE id IN ($id_string)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $exam_date, $shift_id);
    
    if ($stmt->execute()) {
        jsonOut(true, "Updated");
    } else {
        jsonOut(false, $conn->error);
    }
}

if ($action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) jsonOut(false, "No events selected.");

    $id_list = array_map('intval', $ids);
    $id_string = implode(',', $id_list);

    $sql = "DELETE FROM exam_event WHERE id IN ($id_string)";
    if ($conn->query($sql)) {
        jsonOut(true, "Deleted");
    } else {
        jsonOut(false, $conn->error);
    }
}

jsonOut(false, "Unknown action");
