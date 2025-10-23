<?php
// api/campus.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

include '../config/conn.php'; // must define $conn as mysqli

function out(bool $ok, $data) {
  echo json_encode(["status" => $ok, "data" => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_POST['action'] ?? '';

// -------------- list: all campuses --------------
if ($action === 'list') {
  $sql = "SELECT id, name, code, leaders FROM campus ORDER BY name";
  if (!$res = $conn->query($sql)) {
    out(false, 'Failed to fetch campuses');
  }
  $rows = [];
  while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  out(true, $rows);
}

// -------------- create: add new campus --------------
if ($action === 'create') {
  $name    = trim($_POST['name']    ?? '');
  $code    = trim($_POST['code']    ?? '');
  $leaders = trim($_POST['leaders'] ?? '');

  if ($name === '' || $code === '' || $leaders === '') {
    out(false, 'name, code, and leaders coordinator are required');
  }

  $st = $conn->prepare("INSERT INTO campus (name, code, leaders) VALUES (?, ?, ?)");
  if (!$st) out(false, 'Prepare failed');
  // 3 placeholders -> "sss"
  $st->bind_param("sss", $name, $code, $leaders);
  if (!$st->execute()) out(false, 'Insert failed');
  out(true, 'Created');
}

// -------------- update: modify campus --------------
if ($action === 'update') {
  $id      = (int)($_POST['id'] ?? 0);
  $name    = trim($_POST['name']    ?? '');
  $code    = trim($_POST['code']    ?? '');
  $leaders = trim($_POST['leaders'] ?? '');

  if (!$id || $name === '' || $code === '' || $leaders === '') {
    out(false, 'id, name, code, and leaders coordinator are required');
  }

  $st = $conn->prepare("UPDATE campus SET name = ?, code = ?, leaders = ? WHERE id = ?");
  if (!$st) out(false, 'Prepare failed');
  // 4 placeholders -> "sssi"  (three strings + one int)
  $st->bind_param("sssi", $name, $code, $leaders, $id);
  if (!$st->execute()) out(false, 'Update failed');
  if ($st->affected_rows === 0) out(true, 'No changes');
  out(true, 'Updated');
}

// -------------- delete: remove campus --------------
if ($action === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) out(false, 'id is required');

  // Use prepared statement to avoid injection and handle FK errors gracefully
  $st = $conn->prepare("DELETE FROM campus WHERE id = ?");
  if (!$st) out(false, 'Prepare failed');
  $st->bind_param("i", $id);
  if (!$st->execute()) {
    // Likely FK constraint violation if referenced elsewhere
    out(false, 'Delete failed (possibly referenced by other records)');
  }
  out(true, 'Deleted');
}

out(false, 'Unknown action');
