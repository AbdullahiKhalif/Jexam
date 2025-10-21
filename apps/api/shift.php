<?php
// api/shift.php
include '../config/conn.php';
header('Content-Type: application/json');
function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }
$action=$_POST['action']??'';

if($action==='list'){
  $campus_id=(int)($_POST['campus_id']??0);
  $res=$conn->query("SELECT id,name,start_time,end_time,is_enabled FROM shift WHERE campus_id=$campus_id ORDER BY id");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}
if($action==='create'){
  $campus_id=(int)($_POST['campus_id']??0);
  $name=trim($_POST['name']??''); $start=$_POST['start_time']??null; $end=$_POST['end_time']??null;
  if(!$campus_id||$name==='') out(false,'campus_id and name required');
  $st=$conn->prepare("INSERT INTO shift(campus_id,name,start_time,end_time,is_enabled) VALUES(?,?,?,?,1)");
  $st->bind_param("isss",$campus_id,$name,$start,$end); $st->execute(); out(true,'Created');
}
if($action==='update'){
  $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $start=$_POST['start_time']??null; $end=$_POST['end_time']??null;
  if(!$id||$name==='') out(false,'id and name required');
  $st=$conn->prepare("UPDATE shift SET name=?, start_time=?, end_time=? WHERE id=?");
  $st->bind_param("sssi",$name,$start,$end,$id); $st->execute(); out(true,'Updated');
}
if($action==='toggle'){
  $id=(int)($_POST['id']??0); $enabled=(int)($_POST['is_enabled']??1);
  $st=$conn->prepare("UPDATE shift SET is_enabled=? WHERE id=?");
  $st->bind_param("ii",$enabled,$id); $st->execute(); out(true,'Toggled');
}
if($action==='delete'){
  $id=(int)($_POST['id']??0);
  $conn->query("DELETE FROM shift WHERE id=$id");
  out(true,'Deleted');
}

out(false,'Unknown action');
