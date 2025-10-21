<?php
// apps/api/academic.php
include '../config/conn.php';
header('Content-Type: application/json');

function out($ok,$data){ echo json_encode(["status"=>$ok,"data"=>$data]); exit; }

$action = $_POST['action'] ?? '';

/* ---------------- LISTS ---------------- */

if ($action === 'listYears') {
  $res = $conn->query("SELECT id,name,is_active FROM academic_year ORDER BY name DESC");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

if ($action === 'listSemesters') {
  $year_id = (int)($_POST['year_id'] ?? 0);
  $res = $conn->query("SELECT id,name,is_active FROM semester WHERE academic_year_id=$year_id ORDER BY id");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

if ($action === 'listTerms') {
  $semester_id = (int)($_POST['semester_id'] ?? 0);
  $res = $conn->query("SELECT id,name,is_active FROM term WHERE semester_id=$semester_id ORDER BY id");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; out(true,$rows);
}

/* -------- NEW: YEAR+SEMESTER LIST --------
   Returns [{year_id, semester_id, label: "Semester I 2025-2026", is_active}]
------------------------------------------------ */
/* Year+Semester pairs (optionally only active) */
if ($action === 'listYearSemesterPairs' || $action === 'listYearSemesterPairsActive') {
  $only_active = ($action === 'listYearSemesterPairsActive') ||
                 (isset($_POST['only_active']) && (int)$_POST['only_active'] === 1);

  $where = $only_active ? "WHERE s.is_active=1 AND y.is_active=1" : "";
  $sql = "SELECT s.id AS semester_id, s.name AS semester_name,
                 y.id AS year_id, y.name AS year_name,
                 (CASE WHEN s.is_active=1 AND y.is_active=1 THEN 1 ELSE 0 END) AS is_active
          FROM semester s
          JOIN academic_year y ON y.id = s.academic_year_id
          $where
          ORDER BY y.name DESC, s.id ASC";
  $res = $conn->query($sql);
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      "year_id"     => (int)$r["year_id"],
      "semester_id" => (int)$r["semester_id"],
      "label"       => $r["semester_name"] . " " . $r["year_name"],
      "is_active"   => (int)$r["is_active"]
    ];
  }
  echo json_encode(["status"=>true,"data"=>$rows]); exit;
}

/* Terms for a semester (optionally only active) */
if ($action === 'listTerms' || $action === 'listTermsActive') {
  $semester_id = (int)($_POST['semester_id'] ?? 0);
  $only_active = ($action === 'listTermsActive') ||
                 (isset($_POST['only_active']) && (int)$_POST['only_active'] === 1);

  $where = $only_active ? "AND t.is_active=1" : "";
  $sql = "SELECT t.id, t.name, t.is_active
          FROM term t
          WHERE t.semester_id=$semester_id $where
          ORDER BY t.id";
  $res = $conn->query($sql);
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
  echo json_encode(["status"=>true,"data"=>$rows]); exit;
}


/* ---------------- CREATE ---------------- */

if ($action === 'createYear') {
  $name = trim($_POST['name'] ?? '');
  if ($name==='') out(false,'Year name required');
  $st=$conn->prepare("INSERT INTO academic_year(name,is_active) VALUES(?,1)");
  $st->bind_param("s",$name); $st->execute();
  out(true,["id"=>$conn->insert_id,"name"=>$name]);
}

if ($action === 'createSemester') {
  $year_id=(int)($_POST['year_id']??0); $name=trim($_POST['name']??'');
  if(!$year_id||$name==='') out(false,'year_id and name required');
  $st=$conn->prepare("INSERT INTO semester(academic_year_id,name,is_active) VALUES(?,?,1)");
  $st->bind_param("is",$year_id,$name); $st->execute();
  out(true,["id"=>$conn->insert_id,"name"=>$name]);
}

if ($action === 'createTerm') {
  $semester_id=(int)($_POST['semester_id']??0); $name=trim($_POST['name']??'');
  if(!$semester_id||$name==='') out(false,'semester_id and name required');
  $st=$conn->prepare("INSERT INTO term(semester_id,name,is_active) VALUES(?, ?, 1)");
  $st->bind_param("is",$semester_id,$name); $st->execute();
  out(true,["id"=>$conn->insert_id,"name"=>$name]);
}

/* --------------- UPDATE NAMES --------------- */

if ($action === 'updateYear') {
  $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');
  if(!$id||$name==='') out(false,'id and name required');
  $st=$conn->prepare("UPDATE academic_year SET name=? WHERE id=?");
  $st->bind_param("si",$name,$id); $st->execute(); out(true,'Updated');
}

if ($action === 'updateSemester') {
  $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');
  if(!$id||$name==='') out(false,'id and name required');
  $st=$conn->prepare("UPDATE semester SET name=? WHERE id=?");
  $st->bind_param("si",$name,$id); $st->execute(); out(true,'Updated');
}

if ($action === 'updateTerm') {
  $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');
  if(!$id||$name==='') out(false,'id and name required');
  $st=$conn->prepare("UPDATE term SET name=? WHERE id=?");
  $st->bind_param("si",$name,$id); $st->execute(); out(true,'Updated');
}

/* -------------- ENABLE / DISABLE -------------- */

if ($action === 'toggleYear') {
  $id=(int)($_POST['id']??0);
  $active=(int)($_POST['is_active']??1);   // <-- force to int
  $st=$conn->prepare("UPDATE academic_year SET is_active=? WHERE id=?");
  $st->bind_param("ii",$active,$id);
  $st->execute();

  if ($active === 0) {
    $conn->query("UPDATE semester SET is_active=0 WHERE academic_year_id=$id");
    $conn->query("UPDATE term SET is_active=0 WHERE semester_id IN (SELECT id FROM semester WHERE academic_year_id=$id)");
  }
  echo json_encode(["status"=>true,"data"=>"Toggled"]); exit;
}

/* SEMESTER toggle */
if ($action === 'toggleSemester') {
  $id=(int)($_POST['id']??0);
  $active=(int)($_POST['is_active']??1);

  if ($active === 1) { // validate parent year is active
    $yr = $conn->query("SELECT y.is_active
      FROM semester s JOIN academic_year y ON y.id=s.academic_year_id
      WHERE s.id=$id")->fetch_assoc();
    if (!$yr || (int)$yr['is_active'] !== 1) {
      echo json_encode(["status"=>false,"data"=>"Activate the Academic Year first"]); exit;
    }
  }

  $st=$conn->prepare("UPDATE semester SET is_active=? WHERE id=?");
  $st->bind_param("ii",$active,$id);
  $st->execute();

  if ($active === 0) {
    $conn->query("UPDATE term SET is_active=0 WHERE semester_id=$id");
  }
  echo json_encode(["status"=>true,"data"=>"Toggled"]); exit;
}

/* TERM toggle */
if ($action === 'toggleTerm') {
  $id=(int)($_POST['id']??0);
  $active=(int)($_POST['is_active']??1);

  if ($active === 1) { // validate parents are active
    $chk = $conn->query("SELECT y.is_active y, s.is_active s
      FROM term t JOIN semester s ON s.id=t.semester_id
                   JOIN academic_year y ON y.id=s.academic_year_id
      WHERE t.id=$id")->fetch_assoc();
    if (!$chk) { echo json_encode(["status"=>false,"data"=>"Term not found"]); exit; }
    if ((int)$chk['y'] !== 1) { echo json_encode(["status"=>false,"data"=>"Activate the Academic Year first"]); exit; }
    if ((int)$chk['s'] !== 1) { echo json_encode(["status"=>false,"data"=>"Activate the Semester first"]); exit; }
  }

  $st=$conn->prepare("UPDATE term SET is_active=? WHERE id=?");
  $st->bind_param("ii",$active,$id);
  $st->execute();
  echo json_encode(["status"=>true,"data"=>"Toggled"]); exit;
}

/* ----------------- DELETE ----------------- */

if ($action === 'deleteYear') {
  $id=(int)($_POST['id']??0);
  $conn->query("DELETE FROM academic_year WHERE id=$id");
  out(true,'Deleted year (semesters & terms removed)');
}

if ($action === 'deleteSemester') {
  $id=(int)($_POST['id']??0);
  $conn->query("DELETE FROM semester WHERE id=$id");
  out(true,'Deleted semester (terms removed)');
}

if ($action === 'deleteTerm') {
  $id=(int)($_POST['id']??0);
  $conn->query("DELETE FROM term WHERE id=$id");
  out(true,'Deleted term');
}

out(false,'Unknown action');
