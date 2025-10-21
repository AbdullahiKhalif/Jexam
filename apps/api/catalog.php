<?php
// api/catalog.php
include '../config/conn.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }

if ($action==='listCampuses') {
  $res=$conn->query("SELECT id,name,code FROM campus ORDER BY name");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}
if ($action==='listShifts') {
  $campus_id = (int)($_POST['campus_id'] ?? 0);
  $res=$conn->query("SELECT id,name FROM shift WHERE campus_id=$campus_id AND is_enabled=1 ORDER BY id");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

out(false,'Unknown action');
