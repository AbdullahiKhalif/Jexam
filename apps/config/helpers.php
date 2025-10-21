<?php
// config/helpers.php

// ---- small utils ----
function extract_prefix($code) {
    if (!$code) return "";
    if (preg_match('/^([A-Za-z]+)/', $code, $m)) return strtoupper($m[1]);
    return "";
}

function extract_year_group($code) {
    if (!$code) return null;
    if (preg_match('/(\d{2})/', $code, $m)) return intval($m[1]);
    return null;
}

// ---- UPSERT HELPERS (all prepared, no recursion) ----

function get_or_create_department_by_prefix(mysqli $conn, string $prefix) {
    if ($prefix === "") return null;

    // 1) Try rule → department
    $sql = "SELECT d.id
            FROM department_rule r
            JOIN department d ON d.id = r.department_id
            WHERE UPPER(r.prefix)=UPPER(?) LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $stmt->bind_result($did);
    if ($stmt->fetch()) { $stmt->close(); return $did; }
    $stmt->close();

    // 2) Create or fetch department named by prefix
    $sql = "INSERT INTO department (name)
            VALUES (?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $depId = $conn->insert_id;
    $stmt->close();

    // 3) Register rule so next time it maps directly
    $prio = 100;
    $sql  = "INSERT INTO department_rule (prefix, department_id, priority)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE department_id=VALUES(department_id), priority=VALUES(priority)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $prefix, $depId, $prio);
    $stmt->execute();
    $stmt->close();

    return $depId;
}

function get_or_create_campus(mysqli $conn, string $name, string $code) {
    $sql = "INSERT INTO campus (name, code)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), code=VALUES(code)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $code);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

function get_or_create_shift(mysqli $conn, int $campus_id, string $name, $start_time=null, $end_time=null) {
    $sql = "INSERT INTO shift (campus_id, name, start_time, end_time, is_enabled)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),
              start_time=VALUES(start_time), end_time=VALUES(end_time), is_enabled=1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $campus_id, $name, $start_time, $end_time);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

function get_or_create_hall(mysqli $conn, int $campus_id, string $name, int $capacity=0, int $active=1) {
    $capacity = max(0, $capacity);
    $sql = "INSERT INTO hall (campus_id, name, capacity, is_active)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),
              capacity=VALUES(capacity), is_active=VALUES(is_active)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $campus_id, $name, $capacity, $active);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

function set_hall_shift_available(mysqli $conn, int $hall_id, int $shift_id, int $is_available=1) {
    $sql = "INSERT INTO hall_shift_availability (hall_id, shift_id, is_available)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE is_available=VALUES(is_available)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $hall_id, $shift_id, $is_available);
    $stmt->execute();
    $stmt->close();
}

function get_or_create_class(mysqli $conn, string $code, $dept_id=null, $level=null, $active=1) {
    $yg  = extract_year_group($code);
    $sql = "INSERT INTO `class` (code, academic_year_group, department_id, level, is_active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),
              academic_year_group=VALUES(academic_year_group),
              department_id=VALUES(department_id),
              level=VALUES(level),
              is_active=VALUES(is_active)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisi", $code, $yg, $dept_id, $level, $active);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

function get_or_create_academic_period(mysqli $conn, string $yearName, string $semesterName, string $termName) {
    // year
    $sql = "INSERT INTO academic_year (name, is_active)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $yearName);
    $stmt->execute();
    $yearId = $conn->insert_id;
    $stmt->close();

    // semester
    $sql = "INSERT INTO semester (academic_year_id, name, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $yearId, $semesterName);
    $stmt->execute();
    $semId = $conn->insert_id;
    $stmt->close();

    // term
    $sql = "INSERT INTO term (semester_id, name, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $semId, $termName);
    $stmt->execute();
    $termId = $conn->insert_id;
    $stmt->close();

    return [$yearId, $semId, $termId];
}

function upsert_class_size(mysqli $conn, int $class_id, int $year_id, int $sem_id, int $term_id, int $count) {
    $count = max(0, $count);
    $sql   = "INSERT INTO class_size (class_id, academic_year_id, semester_id, term_id, student_count)
              VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE student_count=VALUES(student_count)";
    $stmt  = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $class_id, $year_id, $sem_id, $term_id, $count);
    $stmt->execute();
    $stmt->close();
}
