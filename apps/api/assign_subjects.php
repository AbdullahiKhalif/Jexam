<?php
// apps/api/assign_subjects.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

// Keep JSON clean in production
ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok, $data){
    echo json_encode(['status' => $ok, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if action is set
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    out(false, 'Action required');
}

$action = $_POST['action'];

if ($action === 'listClassesByDepartment') {
    $dept_id = (int)$_POST['department_id'];

    // Ensure the department ID is valid
    if ($dept_id <= 0) {
        out(false, 'Invalid department ID');
    }

    // Query to fetch active classes for the department
    $query = "SELECT id, code, name FROM classes WHERE department_id = ? AND status = 'active' ORDER BY code ASC";
    $stmt = $db->prepare($query);
    if (!$stmt) {
        out(false, 'Error preparing the query: ' . $db->error);  // Add detailed error
    }

    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();

        if (count($classes) > 0) {
            out(true, $classes);
        } else {
            out(false, 'No active classes found for this department');
        }
    } else {
        out(false, 'Query failed: ' . $db->error);  // Add detailed error
    }
}



/* ---------------- helpers (shared) ---------------- */

function fetch_label(mysqli $conn, string $sql, string $types, ...$params){
  if(!$st=$conn->prepare($sql)) return null;
  if($types!=='') $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($v); $ok=$st->fetch(); $st->close();
  return $ok? $v: null;
}

/* Shift policy helpers (to keep campus/shift consistent) */
function canon_shift($name){
  $t=strtolower(trim($name));
  if(preg_match('/shift\s*(1|i)\b/',$t) || in_array($t,['1','i','am','morning','ft'],true)) return 'Shift I';
  if(preg_match('/shift\s*(2|ii)\b/',$t) || in_array($t,['2','ii','pm','afternoon','pt'],true)) return 'Shift II';
  if(preg_match('/shift\s*(3|iii)\b/',$t) || in_array($t,['3','iii','evening','night'],true)) return 'Shift III';
  return $name;
}
function allowed_for_campus($canon_label, int $campus_id): bool {
  if($campus_id===1) return $canon_label==='Shift I';
  if($campus_id===2) return in_array($canon_label,['Shift I','Shift II'],true);
  return in_array($canon_label,['Shift I','Shift II','Shift III'],true);
}
function shift_in_campus_and_allowed(mysqli $conn, int $shift_id, int $campus_id): bool {
  $st=$conn->prepare("SELECT name, campus_id FROM shift WHERE id=? LIMIT 1");
  if(!$st) return false;
  $st->bind_param("i",$shift_id); $st->execute(); $st->bind_result($n,$c); $ok=$st->fetch(); $st->close();
  if(!$ok) return false;
  if((int)$c!==$campus_id) return false;
  return allowed_for_campus(canon_shift($n), $campus_id);
}


/* Subject create/update */
function find_or_create_subject(mysqli $conn,$subject_name,$department_id,&$created=false,$assigned_date=null,$shift_id=null,$level='Specific'){
  $created=false;
  $st=$conn->prepare("SELECT id FROM subject WHERE subject_name=? AND (department_id<=>?) LIMIT 1");
  if(!$st){ out(false,'prepare(select subject) failed: '.$conn->error); }
  $st->bind_param("si",$subject_name,$department_id);
  $st->execute(); $st->bind_result($id);
  if($st->fetch()){
    $st->close();
    $u=$conn->prepare("UPDATE subject SET assigned_date=?, shift_id=?, level=COALESCE(level,'Specific') WHERE id=?");
    if(!$u){ out(false,'prepare(update subject) failed: '.$conn->error); }
    $u->bind_param("sii",$assigned_date,$shift_id,$id);
    if(!$u->execute()){ out(false,'execute(update subject) failed: '.$conn->error); }
    $u->close();
    return (int)$id;
  }
  $st->close();

  $ins=$conn->prepare("INSERT INTO subject(subject_name,department_id,level,assigned_date,shift_id,is_active) VALUES(?,?,?,?,?,1)");
  if(!$ins){ out(false,'prepare(insert subject) failed: '.$conn->error); }
  $lvl = $level ?: 'Specific';
  $ins->bind_param("sissi",$subject_name,$department_id,$lvl,$assigned_date,$shift_id);
  if(!$ins->execute()){ out(false,'execute(insert subject) failed: '.$conn->error); }
  $sid=(int)$ins->insert_id; $ins->close();
  $created=true; return $sid;
}

/* Link class-subject (period-scoped) */
function upsert_class_subject(mysqli $conn,$class_id,$subject_id,$year_id,$sem_id,$term_id){
  $sel=$conn->prepare("SELECT id FROM class_subject WHERE class_id=? AND subject_id=? AND academic_year_id=? AND semester_id=? AND term_id=? LIMIT 1");
  if(!$sel){ out(false,'prepare(select class_subject) failed: '.$conn->error); }
  $sel->bind_param("iiiii",$class_id,$subject_id,$year_id,$sem_id,$term_id);
  $sel->execute(); $sel->bind_result($id);
  if($sel->fetch()){ $sel->close(); return (int)$id; }
  $sel->close();

  $ins=$conn->prepare("INSERT INTO class_subject (class_id,subject_id,academic_year_id,semester_id,term_id) VALUES (?,?,?,?,?)");
  if(!$ins){ out(false,'prepare(insert class_subject) failed: '.$conn->error); }
  $ins->bind_param("iiiii",$class_id,$subject_id,$year_id,$sem_id,$term_id);
  if(!$ins->execute()){ out(false,'execute(insert class_subject) failed: '.$conn->error); }
  $newId=(int)$ins->insert_id; $ins->close(); return $newId;
}

/* Create/Update exam_event (one slot = class + period + shift + date) */
function upsert_exam_event(mysqli $conn,$class_id,$subject_id,$year_id,$sem_id,$term_id,$exam_date,$shift_id,$duration=120){
  if(!$shift_id || !$exam_date) return 0;

  $sel=$conn->prepare("SELECT id FROM exam_event
                       WHERE class_id=? AND academic_year_id=? AND semester_id=? AND term_id=?
                         AND shift_id=? AND exam_date=?
                       LIMIT 1");
  if(!$sel){ out(false,'prepare(select exam_event) failed: '.$conn->error); }
  $sel->bind_param("iiiiis",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date);
  $sel->execute(); $sel->bind_result($eid);

  if($sel->fetch()){
    $sel->close();
    $u=$conn->prepare("UPDATE exam_event SET subject_id=?, duration_minutes=? WHERE id=?");
    if(!$u){ out(false,'prepare(update exam_event) failed: '.$conn->error); }
    $u->bind_param("iii",$subject_id,$duration,$eid);
    if(!$u->execute()){ out(false,'execute(update exam_event) failed: '.$conn->error); }
    $u->close();
    return 2;
  }
  $sel->close();

  $ins=$conn->prepare("INSERT INTO exam_event
    (class_id, academic_year_id, semester_id, term_id, shift_id, exam_date, subject_id, duration_minutes, status)
    VALUES (?,?,?,?,?,?,?,?,'draft')");
  if(!$ins){ out(false,'prepare(insert exam_event) failed: '.$conn->error); }
  $ins->bind_param("iiiiisii",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date,$subject_id,$duration);
  if(!$ins->execute()){ out(false,'execute(insert exam_event) failed: '.$conn->error); }
  $ins->close();
  return 1;
}

/* ------------- lightweight list endpoints ------------- */

/* Classes by department (active), ordered by code */
if ($action==='listClassesByDepartment'){
  $dept_id = (int)($REQ['department_id'] ?? 0);
  if(!$dept_id) out(false,'department_id required');
  $st=$conn->prepare("SELECT id, code, name FROM `class` WHERE department_id=? AND is_active=1 ORDER BY code, id");
  if(!$st) out(false,'prepare(listClassesByDepartment) failed: '.$conn->error);
  $st->bind_param("i",$dept_id); $st->execute(); $res=$st->get_result();
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; $st->close();
  out(true,$rows);
}

/* Shifts available for a campus (policy-aware) */
if($action==='listShiftsByCampus'){
  $campus_id=(int)($REQ['campus_id']??0);
  if(!$campus_id) out(false,'campus_id required');

  $sql="SELECT id, name,
           DATE_FORMAT(start_time, '%h:%i %p') AS start_time,
           DATE_FORMAT(end_time,   '%h:%i %p') AS end_time
        FROM shift
        WHERE campus_id=?
        ORDER BY FIELD(name,'Shift I','Shift II','Shift III'), start_time, id";
  $st=$conn->prepare($sql);
  if(!$st) out(false,'prepare(listShiftsByCampus) failed: '.$conn->error);
  $st->bind_param("i",$campus_id); $st->execute(); $res=$st->get_result();
  $rows=[]; while($r=$res->fetch_assoc()){
    $canon=canon_shift($r['name']);
    if(allowed_for_campus($canon,$campus_id)) $rows[]=$r;
  }
  $st->close(); out(true,$rows);
}

/* ----------------- main: assignSubjects ----------------- */
if ($action==='assignSubjects'){
  $year_id   = (int)($REQ['academic_year_id'] ?? 0);
  $sem_id    = (int)($REQ['semester_id'] ?? 0);
  $term_id   = (int)($REQ['term_id'] ?? 0);
  $dept_id   = (int)($REQ['department_id'] ?? 0);
  $campus_id = (int)($REQ['campus_id'] ?? 0);
  $shift_id  = (int)($REQ['shift_id'] ?? 0);

  $class_ids     = $REQ['class_ids'] ?? [];
  $subject_name  = trim((string)($REQ['subject_name'] ?? ''));
  $assigned_date = trim((string)($REQ['assigned_date'] ?? ''));   // YYYY-MM-DD
  $duration      = (int)($REQ['duration'] ?? 120);
  $level         = trim((string)($REQ['level'] ?? 'Specific'));    // optional visual flag

  if(!$year_id||!$sem_id||!$term_id||!$dept_id||!$campus_id||!$shift_id||$subject_name===''||!is_array($class_ids)||count($class_ids)===0){
    out(false,'All fields are required');
  }

  // campus/shift guard
  if(!shift_in_campus_and_allowed($conn,$shift_id,$campus_id)){
    out(false,'Selected shift is not allowed for this campus.');
  }

  // make sure date looks like YYYY-MM-DD (optional)
  if($assigned_date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$assigned_date)){
    out(false,'Invalid date format, expected YYYY-MM-DD');
  }

  $conn->begin_transaction();

  $created=false;
  $subject_id = find_or_create_subject($conn,$subject_name,$dept_id,$created, ($assigned_date?:null), $shift_id, $level);

  $stats = ['classes_assigned'=>0,'events_created'=>0,'events_updated'=>0];

  foreach($class_ids as $cid){
    $class_id = (int)$cid;
    if(!$class_id) continue;

    // link (idempotent)
    upsert_class_subject($conn,$class_id,$subject_id,$year_id,$sem_id,$term_id);
    $stats['classes_assigned']++;

    // create/update event if a date is provided
    if($assigned_date!==''){
      $res = upsert_exam_event($conn,$class_id,$subject_id,$year_id,$sem_id,$term_id,$assigned_date,$shift_id,$duration);
      if($res===1) $stats['events_created']++;
      elseif($res===2) $stats['events_updated']++;
    }
  }

  $conn->commit();
  out(true,$stats);
}

out(false,'Unknown action');
