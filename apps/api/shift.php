<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';
ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok,$data){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD']!=='POST' || !isset($_POST['action'])) out(false, 'Action required');
$action = $_POST['action'];

/* === LIST === */
if ($action==='list') {
  $sql = "SELECT id, name, start_time, end_time, is_enabled FROM shift ORDER BY start_time";
  $res = $conn->query($sql);
  if (!$res) out(false, 'Query failed: '.$conn->error);

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  out(true, $rows);
}

/* === CREATE === */
if ($action==='create') {
  $name  = trim($_POST['name'] ?? '');
  $start = $_POST['start_time'] ?? null;
  $end   = $_POST['end_time'] ?? null;
  if ($name==='') out(false,'Name required');

  if(!$st=$conn->prepare("INSERT INTO shift(name,start_time,end_time,is_enabled) VALUES(?,?,?,1)"))
    out(false,'prepare failed: '.$conn->error);
  $st->bind_param("sss",$name,$start,$end);
  if(!$st->execute()) out(false,'execute failed: '.$st->error);
  $st->close();
  out(true,'Created');
}

/* === UPDATE === */
if ($action==='update') {
  $id = (int)($_POST['id'] ?? 0);
  $name  = trim($_POST['name'] ?? '');
  $start = $_POST['start_time'] ?? null;
  $end   = $_POST['end_time'] ?? null;
  if(!$id || $name==='') out(false,'Missing data');

  if(!$st=$conn->prepare("UPDATE shift SET name=?, start_time=?, end_time=? WHERE id=?"))
    out(false,'prepare failed: '.$conn->error);
  $st->bind_param("sssi",$name,$start,$end,$id);
  if(!$st->execute()) out(false,'execute failed: '.$st->error);
  $st->close();
  out(true,'Updated');
}

/* === TOGGLE === */
if ($action==='toggle') {
  $id = (int)($_POST['id'] ?? 0);
  $val = (int)($_POST['is_enabled'] ?? 0);
  if(!$id) out(false,'Invalid ID');

  if(!$st=$conn->prepare("UPDATE shift SET is_enabled=? WHERE id=?"))
    out(false,'prepare failed: '.$conn->error);
  $st->bind_param("ii",$val,$id);
  if(!$st->execute()) out(false,'execute failed: '.$st->error);
  $st->close();
  out(true,'Updated');
}

/* === DELETE === */
if ($action==='delete') {
  $id = (int)($_POST['id'] ?? 0);
  if(!$id) out(false,'Invalid ID');

  if(!$st=$conn->prepare("DELETE FROM shift WHERE id=?"))
    out(false,'prepare failed: '.$conn->error);
  $st->bind_param("i",$id);
  if(!$st->execute()) out(false,'execute failed: '.$st->error);
  $st->close();
  out(true,'Deleted');
}

out(false,'Unknown action');
