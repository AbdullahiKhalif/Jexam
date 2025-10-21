<?php
// api/class.php
include '../config/conn.php';
include '../config/helpers.php';

/**
 * jsonOut(bool $status, mixed $dataOrMsg)
 * is provided by helpers.php in your project.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    jsonOut(false, "Action is required");
}

$action = $_POST['action'];

/* ============ HELPERS ============ */

function nullable($v) {
    // Normalize empty string to NULL for nullable FK columns
    return ($v === '' || $v === null) ? null : $v;
}

/* ============ ACTIONS ============ */

if ($action === 'readAllClass') {
    // Legacy endpoint used by js/upload.js
    $sql = "SELECT c.id, c.code, c.academic_year_group, d.name AS department, c.is_active
            FROM `class` c
            LEFT JOIN department d ON d.id = c.department_id
            ORDER BY c.code";
    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $data[] = $row;
        jsonOut(true, $data);
    } else {
        jsonOut(false, $conn->error);
    }
}

if ($action === 'readClassSizes') {
    // Legacy endpoint
    $sql = "SELECT cs.id, c.code AS class_code, ay.name AS academic_year,
                   s.name AS semester, t.name AS term, cs.student_count
            FROM class_size cs
            JOIN `class` c ON c.id = cs.class_id
            JOIN academic_year ay ON ay.id = cs.academic_year_id
            JOIN semester s ON s.id = cs.semester_id
            JOIN term t ON t.id = cs.term_id
            ORDER BY ay.name, s.name, t.name, c.code";
    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $data[] = $row;
        jsonOut(true, $data);
    } else {
        jsonOut(false, $conn->error);
    }
}

/**
 * NEW: Period-aware class listing for the new views/JS.
 * Accepts optional filters academic_year_id, semester_id, term_id.
 * Returns joined names for display.
 */
if ($action === 'list') {
    $year_id = $_POST['academic_year_id'] ?? null;
    $sem_id  = $_POST['semester_id'] ?? null;
    $term_id = $_POST['term_id'] ?? null;

    $where = [];
    $types = '';
    $vals  = [];

    if ($year_id !== null && $year_id !== '') {
        $where[] = "(c.academic_year_id IS NULL OR c.academic_year_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$year_id;
    }
    if ($sem_id !== null && $sem_id !== '') {
        $where[] = "(c.semester_id IS NULL OR c.semester_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$sem_id;
    }
    if ($term_id !== null && $term_id !== '') {
        $where[] = "(c.term_id IS NULL OR c.term_id = ?)";
        $types  .= 'i';
        $vals[]  = (int)$term_id;
    }

    $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
                c.id, c.code, c.level, c.department_id, d.name AS department,
                c.academic_year_id, c.semester_id, c.term_id,
                ay.name AS year_name, se.name AS semester_name, te.name AS term_name
            FROM `class` c
            LEFT JOIN department d  ON d.id  = c.department_id
            LEFT JOIN academic_year ay ON ay.id = c.academic_year_id
            LEFT JOIN semester se   ON se.id = c.semester_id
            LEFT JOIN term te       ON te.id = c.term_id
            $wsql
            ORDER BY c.code";

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

if ($action === 'listCohortsByDepartment') {
    $dept_id = intval($_POST['department_id'] ?? 0);
    $year_id = $_POST['academic_year_id'] ?? null;
    $sem_id  = $_POST['semester_id'] ?? null;
    $term_id = $_POST['term_id'] ?? null;
    if($dept_id<=0) jsonOut(false,"department_id required");

    $where = "WHERE c.is_active=1 AND c.department_id=?";
    $types = "i";
    $vals  = [$dept_id];

    if ($year_id !== null && $year_id !== '') { $where.=" AND (c.academic_year_id IS NULL OR c.academic_year_id=?)"; $types.="i"; $vals[]=(int)$year_id; }
    if ($sem_id  !== null && $sem_id  !== '') { $where.=" AND (c.semester_id      IS NULL OR c.semester_id=?)";      $types.="i"; $vals[]=(int)$sem_id;  }
    if ($term_id !== null && $term_id !== '') { $where.=" AND (c.term_id          IS NULL OR c.term_id=?)";           $types.="i"; $vals[]=(int)$term_id; }

    // Pull raw classes, then group by cohort in PHP:
    $sql = "SELECT c.id, c.code, c.academic_year_group,
                   UPPER(LEFT(c.code,2)) AS prefix
            FROM `class` c
            $where
            ORDER BY c.academic_year_group DESC, c.code ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $res = $stmt->get_result();

    $cohorts = []; // label => data
    while($r = $res->fetch_assoc()){
        $pref = $r['prefix'] ?: '';
        $grp  = $r['academic_year_group']; // e.g., 24
        $label = ($pref!=='' && $grp!==null) ? ($pref . str_pad($grp,2,'0',STR_PAD_LEFT)) : ($r['code']);
        if(!isset($cohorts[$label])){
            $cohorts[$label] = ["label"=>$label, "count"=>0, "class_ids"=>[], "sample_codes"=>[]];
        }
        $cohorts[$label]["count"] += 1;
        $cohorts[$label]["class_ids"][] = intval($r['id']);
        if(count($cohorts[$label]["sample_codes"])<5) $cohorts[$label]["sample_codes"][] = $r['code'];
    }
    // return as indexed array
    jsonOut(true, array_values($cohorts));
}

/**
 * NEW: Create class (period-aware)
 * Params: code, department_id?, level?, academic_year_id?, semester_id?, term_id?
 */
if ($action === 'create') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '') jsonOut(false, "Class code required");

    $department_id = nullable($_POST['department_id'] ?? null);
    $level         = nullable($_POST['level'] ?? null);
    $year_id       = nullable($_POST['academic_year_id'] ?? null);
    $sem_id        = nullable($_POST['semester_id'] ?? null);
    $term_id       = nullable($_POST['term_id'] ?? null);

    $sql = "INSERT INTO `class`
            (code, department_id, level, academic_year_id, semester_id, term_id, is_active)
            VALUES (?,?,?,?,?,?,1)";
    $stmt = $conn->prepare($sql);

    // bind types: s (code), i? (dept), s? (level), i? (ids) — use NULL safely
    // We'll bind as: s i s i i i (NULL values are allowed with bind_param by passing null)
    $stmt->bind_param(
        "sisiii",
        $code,
        $department_id,  // NULL ok
        $level,          // NULL ok
        $year_id,        // NULL ok
        $sem_id,         // NULL ok
        $term_id         // NULL ok
    );

    if ($stmt->execute()) {
        jsonOut(true, ["id" => $stmt->insert_id]);
    } else {
        jsonOut(false, $conn->error);
    }
}

/**
 * NEW: Update class (period-aware)
 */
if ($action === 'update') {
    $id   = intval($_POST['id'] ?? 0);
    if ($id <= 0) jsonOut(false, "Missing id");

    $code = trim($_POST['code'] ?? '');
    if ($code === '') jsonOut(false, "Class code required");

    $department_id = nullable($_POST['department_id'] ?? null);
    $level         = nullable($_POST['level'] ?? null);
    $year_id       = nullable($_POST['academic_year_id'] ?? null);
    $sem_id        = nullable($_POST['semester_id'] ?? null);
    $term_id       = nullable($_POST['term_id'] ?? null);

    $sql = "UPDATE `class`
               SET code=?,
                   department_id=?,
                   level=?,
                   academic_year_id=?,
                   semester_id=?,
                   term_id=?
             WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sisiiii",
        $code,
        $department_id,
        $level,
        $year_id,
        $sem_id,
        $term_id,
        $id
    );

    if ($stmt->execute()) {
        jsonOut(true, "Updated");
    } else {
        jsonOut(false, $conn->error);
    }
}

/**
 * NEW: Delete class
 */
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) jsonOut(false, "Missing id");
    $stmt = $conn->prepare("DELETE FROM `class` WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        jsonOut(true, "Deleted");
    } else {
        jsonOut(false, $conn->error);
    }
}

// If we got here, the action wasn't matched:
jsonOut(false, "Unknown action");
