<?php
// apps/api/department.php
header('Content-Type: application/json');

// --- Safe includes (use absolute paths) ---
@include_once __DIR__ . '/../config/conn.php';       // must define $conn (mysqli)
@include_once __DIR__ . '/../config/helpers.php';    // may or may not define jsonOut()

// --- Fallback helpers if helpers.php isn't present ---
if (!function_exists('jsonOut')) {
  function jsonOut($status, $data = null) {
    echo json_encode(['status' => (bool)$status, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
if (!function_exists('ok')) {
  function ok($data = null) { jsonOut(true, $data); }
}
if (!function_exists('err')) {
  function err($msg) { jsonOut(false, $msg); }
}

// --- Guard: DB connection ---
if (!isset($conn) || !($conn instanceof mysqli)) {
  err('Database connection ($conn) not initialized.');
}

// --- Only POST with action ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
  err('Action is required');
}
$action = $_POST['action'];

// --- Small utils ---
function nv($v){ return ($v === '' ? null : $v); }

// ===================== DEPARTMENTS =====================

if ($action === 'list') {
  $res = $conn->query("SELECT id, name FROM department ORDER BY name");
  if (!$res) err($conn->error);
  $out = [];
  while ($r = $res->fetch_assoc()) $out[] = $r;
  ok($out);
}

if ($action === 'create') {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') err('Department name is required');

  $stmt = $conn->prepare("SELECT COUNT(*) c FROM department WHERE name=?");
  $stmt->bind_param("s", $name);
  $stmt->execute();
  $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  if ($c > 0) err('Department already exists');

  $stmt = $conn->prepare("INSERT INTO department(name) VALUES(?)");
  $stmt->bind_param("s", $name);
  if ($stmt->execute()) ok(['id' => $stmt->insert_id]);
  err($conn->error);
}

if ($action === 'update') {
  $id   = intval($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  if ($id <= 0 || $name === '') err('Missing id or name');

  $stmt = $conn->prepare("SELECT COUNT(*) c FROM department WHERE name=? AND id<>?");
  $stmt->bind_param("si", $name, $id);
  $stmt->execute();
  $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  if ($c > 0) err('Another department already has that name');

  $stmt = $conn->prepare("UPDATE department SET name=? WHERE id=?");
  $stmt->bind_param("si", $name, $id);
  if ($stmt->execute()) ok('Updated');
  err($conn->error);
}

if ($action === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  if ($id <= 0) err('Missing id');

  $stmt = $conn->prepare("DELETE FROM department WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) ok('Deleted');
  err($conn->error);
}

if ($action === 'listOptions') {
  $res = $conn->query("SELECT id, name FROM department ORDER BY name");
  if (!$res) err($conn->error);
  $out = [];
  while ($r = $res->fetch_assoc()) $out[] = $r;
  ok($out);
}

// ===================== DEPARTMENT RULES =====================

if ($action === 'listRules') {
  $dept_id = $_POST['department_id'] ?? null;
  if ($dept_id !== null && $dept_id !== '') {
    $stmt = $conn->prepare(
      "SELECT r.id, r.prefix, r.department_id, r.priority, d.name AS department
       FROM department_rule r
       JOIN department d ON d.id = r.department_id
       WHERE r.department_id = ?
       ORDER BY r.priority ASC, r.prefix ASC"
    );
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $conn->query(
      "SELECT r.id, r.prefix, r.department_id, r.priority, d.name AS department
       FROM department_rule r
       JOIN department d ON d.id = r.department_id
       ORDER BY r.priority ASC, r.prefix ASC"
    );
  }
  if (!$res) err($conn->error);
  $out = [];
  while ($r = $res->fetch_assoc()) $out[] = $r;
  ok($out);
}

if ($action === 'createRule') {
  $prefix        = strtoupper(trim($_POST['prefix'] ?? ''));
  $department_id = intval($_POST['department_id'] ?? 0);
  $priority      = nv($_POST['priority'] ?? null);

  if ($prefix === '') err('Prefix is required');
  if ($department_id <= 0) err('department_id is required');

  if ($priority === null) {
    $mx = $conn->query("SELECT COALESCE(MAX(priority), 90) AS m FROM department_rule");
    $priority = intval(($mx->fetch_assoc()['m'] ?? 90)) + 10;
  } else {
    $priority = intval($priority);
  }

  $stmt = $conn->prepare("SELECT COUNT(*) c FROM department_rule WHERE prefix=?");
  $stmt->bind_param("s", $prefix);
  $stmt->execute();
  $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  if ($c > 0) err('Prefix already exists');

  $stmt = $conn->prepare("INSERT INTO department_rule(prefix, department_id, priority) VALUES(?,?,?)");
  $stmt->bind_param("sii", $prefix, $department_id, $priority);
  if ($stmt->execute()) ok(['id' => $stmt->insert_id]);
  err($conn->error);
}

if ($action === 'updateRule') {
  $id            = intval($_POST['id'] ?? 0);
  $prefix        = strtoupper(trim($_POST['prefix'] ?? ''));
  $department_id = intval($_POST['department_id'] ?? 0);
  $priority      = intval($_POST['priority'] ?? 100);

  if ($id <= 0 || $prefix === '' || $department_id <= 0) err('Missing fields');

  $stmt = $conn->prepare("SELECT COUNT(*) c FROM department_rule WHERE prefix=? AND id<>?");
  $stmt->bind_param("si", $prefix, $id);
  $stmt->execute();
  $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  if ($c > 0) err('Another rule already has that prefix');

  $stmt = $conn->prepare("UPDATE department_rule SET prefix=?, department_id=?, priority=? WHERE id=?");
  $stmt->bind_param("siii", $prefix, $department_id, $priority, $id);
  if ($stmt->execute()) ok('Updated');
  err($conn->error);
}

if ($action === 'deleteRule') {
  $id = intval($_POST['id'] ?? 0);
  if ($id <= 0) err('Missing id');
  $stmt = $conn->prepare("DELETE FROM department_rule WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) ok('Deleted');
  err($conn->error);
}

if ($action === 'moveRule') {
  $id  = intval($_POST['id'] ?? 0);
  $dir = $_POST['direction'] ?? '';
  if ($id <= 0 || !in_array($dir, ['up','down'], true)) err('Invalid params');

  $cur = $conn->prepare("SELECT id, priority FROM department_rule WHERE id=?");
  $cur->bind_param("i", $id);
  $cur->execute();
  $row = $cur->get_result()->fetch_assoc();
  if (!$row) err('Rule not found');

  $p = intval($row['priority']);

  if ($dir === 'up') {
    $stmt = $conn->prepare("SELECT id, priority FROM department_rule WHERE priority < ? ORDER BY priority DESC LIMIT 1");
    $stmt->bind_param("i", $p);
  } else {
    $stmt = $conn->prepare("SELECT id, priority FROM department_rule WHERE priority > ? ORDER BY priority ASC LIMIT 1");
    $stmt->bind_param("i", $p);
  }
  $stmt->execute();
  $nb = $stmt->get_result()->fetch_assoc();
  if (!$nb) ok('No swap'); // already at edge

  $conn->begin_transaction();
  try {
    $a = $conn->prepare("UPDATE department_rule SET priority=? WHERE id=?");
    $a->bind_param("ii", $nb['priority'], $id);
    $a->execute();

    $b = $conn->prepare("UPDATE department_rule SET priority=? WHERE id=?");
    $b->bind_param("ii", $p, $nb['id']);
    $b->execute();

    $conn->commit();
    ok('Moved');
  } catch (Throwable $e) {
    $conn->rollback();
    err($e->getMessage());
  }
}

// --- Default ---
err('Unknown action');
