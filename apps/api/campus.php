<?php
// api/campus.php
include '../config/conn.php';
header('Content-Type: application/json');
function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }
$action=$_POST['action']??'';

if($action==='list'){ // all campuses
  $res=$conn->query("SELECT id,name,code FROM campus ORDER BY name");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}
if($action==='create'){
  $name=trim($_POST['name']??''); $code=trim($_POST['code']??'');
  if($name===''||$code==='') out(false,'name and code required');
  $st=$conn->prepare("INSERT INTO campus(name,code) VALUES(?,?)");
  $st->bind_param("ss",$name,$code); $st->execute(); out(true,'Created');
}
if($action==='update'){
  $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $code=trim($_POST['code']??'');
  if(!$id||$name===''||$code==='') out(false,'id, name, code required');
  $st=$conn->prepare("UPDATE campus SET name=?, code=? WHERE id=?");
  $st->bind_param("ssi",$name,$code,$id); $st->execute(); out(true,'Updated');
}
if($action==='delete'){
  $id=(int)($_POST['id']??0);
  // Will fail if referenced; handle in JS
  $conn->query("DELETE FROM campus WHERE id=$id");
  out(true,'Deleted');
}

out(false,'Unknown action');
