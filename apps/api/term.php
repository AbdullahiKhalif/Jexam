<?php
// apps/api/term.php
include '../config/conn.php';
header('Content-Type: application/json');

function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }
function dOrNull($s){ $s = trim((string)$s); return $s === '' ? null : $s; }

$action = $_POST['action'] ?? '';

/* ========== Lists ========== */

// All terms (optionally by semester_id)
if ($action === 'list') {
  $semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : 0;

  $baseSql = "SELECT t.id, t.name, t.is_active, t.semester_id,
                     s.name AS semester_name, y.name AS year_name,
                     t.start_date, t.end_date,
                     y.is_active AS year_active, s.is_active AS sem_active
                FROM term t
                JOIN semester s ON s.id=t.semester_id
                JOIN academic_year y ON y.id=s.academic_year_id ";

  if ($semester_id) {
    $sql = $baseSql . "WHERE t.semester_id=? ORDER BY y.name DESC, s.id ASC, t.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $sql = $baseSql . "ORDER BY y.name DESC, s.id ASC, t.id ASC";
    $res = $conn->query($sql);
  }

  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

// List semesters (for dropdowns)
if ($action === 'listSemesters') {
  $res=$conn->query("SELECT s.id, s.name, s.is_active, y.name AS year_name
                     FROM semester s JOIN academic_year y ON y.id=s.academic_year_id where s.is_active = 1
                     ORDER BY y.name DESC, s.id ASC");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

/* ========== Create / Update ========== */

if ($action === 'create') {
  $semester_id=(int)($_POST['semester_id']??0);
  $name=trim($_POST['name']??'');
  $start_date=dOrNull($_POST['start_date']??'');
  $end_date=dOrNull($_POST['end_date']??'');

  if(!$semester_id || $name==='') out(false,'semester_id and name required');

  $st=$conn->prepare("INSERT INTO term(semester_id,name,start_date,end_date,is_active) VALUES(?,?,?,?,1)");
  // 4 placeholders -> "isss"
  $st->bind_param("isss",$semester_id,$name,$start_date,$end_date);
  if(!$st->execute()) out(false,$st->error);

  out(true,["id"=>$conn->insert_id,"name"=>$name]);
}

if ($action === 'update') {
  $id=(int)($_POST['id']??0);
  $name=trim($_POST['name']??'');
  $start_date=dOrNull($_POST['start_date']??'');
  $end_date=dOrNull($_POST['end_date']??'');
  // semester_id is optional on update
  $semester_id = isset($_POST['semester_id']) && $_POST['semester_id']!=='' ? (int)$_POST['semester_id'] : 0;

  if(!$id || $name==='') out(false,'id and name required');

  if ($semester_id) {
    $st=$conn->prepare("UPDATE term SET name=?, semester_id=?, start_date=?, end_date=? WHERE id=?");
    // s i s s i
    $st->bind_param("sissi",$name,$semester_id,$start_date,$end_date,$id);
  } else {
    $st=$conn->prepare("UPDATE term SET name=?, start_date=?, end_date=? WHERE id=?");
    // s s s i
    $st->bind_param("sssi",$name,$start_date,$end_date,$id);
  }

  if(!$st->execute()) out(false,$st->error);
  out(true,'Updated');
}

/* ========== Toggle Active (unchanged) ========== */

if ($action === 'toggle') {
  $id=(int)($_POST['id']??0);
  $active=(int)($_POST['is_active']??1);

  if ($active === 1) {
    $chk = $conn->query("SELECT y.is_active y, s.is_active s
                         FROM term t JOIN semester s ON s.id=t.semester_id
                                      JOIN academic_year y ON y.id=s.academic_year_id
                         WHERE t.id=$id")->fetch_assoc();
    if(!$chk) out(false,'Term not found');
    if((int)$chk['y']!==1) out(false,'Activate the Academic Year first');
    if((int)$chk['s']!==1) out(false,'Activate the Semester first');
  }

  $st=$conn->prepare("UPDATE term SET is_active=? WHERE id=?");
  $st->bind_param("ii",$active,$id);
  if(!$st->execute()) out(false,$st->error);
  out(true,'Toggled');
}

/* ========== Delete (unchanged) ========== */

if ($action === 'delete') {
  $id=(int)($_POST['id']??0);
  $conn->query("DELETE FROM term WHERE id=$id");
  out(true,'Deleted');
}

out(false,'Unknown action');
