<?php
// api/catalog.php
include '../config/conn.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }

if ($action==='listCampuses') {
  $res=$conn->query("SELECT id,name,code FROM campus where is_available = 1 ORDER BY name");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}
if ($action==='listShifts') {
  $res=$conn->query("SELECT id,name FROM shift WHERE is_enabled=1 ORDER BY id");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

out(false,'Unknown action');
