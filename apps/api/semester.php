<?php
// apps/api/semester.php
include '../config/conn.php';
header('Content-Type: application/json');

function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }

$action = $_POST['action'] ?? '';

/* ========== Lists ========== */

// All semesters (optionally by year_id)
if ($action === 'list') {
  $year_id = isset($_POST['year_id']) ? (int)$_POST['year_id'] : 0;
  $where = $year_id ? "WHERE s.academic_year_id=$year_id" : "";
  $sql = "SELECT s.id, s.name, s.is_active, s.academic_year_id, y.name AS year_name
          FROM semester s
          JOIN academic_year y ON y.id=s.academic_year_id
          $where
          ORDER BY y.name DESC, s.id ASC";
  $res = $conn->query($sql);
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
  out(true,$rows);
}

// List years (for dropdowns)
if ($action === 'listYears') {
  $res = $conn->query("SELECT id,name,is_active FROM academic_year ORDER BY name DESC");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

/* ========== Create / Update ========== */

if ($action === 'create') {
  $year_id = (int)($_POST['academic_year_id'] ?? 0);
  $name    = trim($_POST['name'] ?? '');
  if(!$year_id || $name==='') out(false,'academic_year_id and name required');

  $st = $conn->prepare("INSERT INTO semester(academic_year_id,name,is_active) VALUES(?,?,1)");
  $st->bind_param("is",$year_id,$name);
  $st->execute();
  out(true,["id"=>$conn->insert_id,"name"=>$name]);
}

if ($action === 'update') {
  $id   = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $year_id = (int)($_POST['academic_year_id'] ?? 0);
  if(!$id || $name==='') out(false,'id and name required');

  if ($year_id) {
    $st = $conn->prepare("UPDATE semester SET name=?, academic_year_id=? WHERE id=?");
    $st->bind_param("sii",$name,$year_id,$id);
  } else {
    $st = $conn->prepare("UPDATE semester SET name=? WHERE id=?");
    $st->bind_param("si",$name,$id);
  }
  $st->execute();
  out(true,'Updated');
}

/* ========== Toggle Active (with rules) ========== */

if ($action === 'toggle') {
  $id=(int)($_POST['id']??0);
  $active=(int)($_POST['is_active']??1);

  if ($active === 1) {
    $yr = $conn->query("SELECT y.is_active
                        FROM semester s JOIN academic_year y ON y.id=s.academic_year_id
                        WHERE s.id=$id")->fetch_assoc();
    if(!$yr || (int)$yr['is_active']!==1) out(false,'Activate the Academic Year first');
  }

  $st=$conn->prepare("UPDATE semester SET is_active=? WHERE id=?");
  $st->bind_param("ii",$active,$id);
  $st->execute();

  if ($active === 0) {
    // Disable all terms under this semester
    $conn->query("UPDATE term SET is_active=0 WHERE semester_id=$id");
  }
  out(true,'Toggled');
}

/* ========== Delete ========== */

if ($action === 'delete') {
  $id=(int)($_POST['id']??0);
  // Will cascade delete terms if FK set; else terms must be deleted first
  $conn->query("DELETE FROM semester WHERE id=$id");
  out(true,'Deleted');
}

out(false,'Unknown action');
