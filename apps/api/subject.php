<?php
// api/subject.php (mysqli version to match your project)
header('Content-Type: application/json');
include '../config/conn.php';
include '../config/helpers.php'; // for jsonOut()

function nvl($v){ return ($v === '' ? null : $v); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
  jsonOut(false, "Action is required");
}
$action = $_POST['action'];

/* ---------- LIST (period-aware, includes default_exam_date) ---------- */
if ($action === 'list') {
  $year_id = $_POST['academic_year_id'] ?? null;
  $sem_id  = $_POST['semester_id'] ?? null;
  $term_id = $_POST['term_id'] ?? null;

  $where = [];
  $types = '';
  $vals  = [];

  if ($year_id !== null && $year_id !== '') { $where[]="(s.academic_year_id IS NULL OR s.academic_year_id=?)"; $types.='i'; $vals[]=(int)$year_id; }
  if ($sem_id  !== null && $sem_id  !== '') { $where[]="(s.semester_id      IS NULL OR s.semester_id=?)";      $types.='i'; $vals[]=(int)$sem_id;  }
  if ($term_id !== null && $term_id !== '') { $where[]="(s.term_id          IS NULL OR s.term_id=?)";           $types.='i'; $vals[]=(int)$term_id; }

  $wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
  $sql = "SELECT s.id, s.name, s.level, s.department_id, d.name AS department,
                 s.academic_year_id, s.semester_id, s.term_id,
                 s.default_exam_date,
                 ay.name AS year_name, se.name AS semester_name, te.name AS term_name
          FROM subject s
          LEFT JOIN department d ON d.id=s.department_id
          LEFT JOIN academic_year ay ON ay.id=s.academic_year_id
          LEFT JOIN semester se ON se.id=s.semester_id
          LEFT JOIN term te ON te.id=s.term_id
          $wsql
          ORDER BY s.name";
  if ($wsql) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $conn->query($sql);
  }
  if(!$res) jsonOut(false, $conn->error);
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  jsonOut(true,$out);
}

/* ---------- CREATE ---------- */
if ($action === 'create') {
  $name = trim($_POST['name'] ?? '');
  if ($name==='') jsonOut(false,"Subject name required");

  $level = $_POST['level'] ?? 'General';
  $department_id = nvl($_POST['department_id'] ?? null);
  $year_id = nvl($_POST['academic_year_id'] ?? null);
  $sem_id  = nvl($_POST['semester_id'] ?? null);
  $term_id = nvl($_POST['term_id'] ?? null);
  $default_exam_date = nvl($_POST['default_exam_date'] ?? null);

  // uniqueness per year (if you applied unique(name, academic_year_id))
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM subject WHERE name=? AND (academic_year_id <=> ?)");
  $stmt->bind_param("si", $name, $year_id);
  $stmt->execute(); $r=$stmt->get_result()->fetch_assoc();
  if(($r['c']??0)>0) jsonOut(false,"Subject name already exists for this Academic Year");

  $sql="INSERT INTO subject(name,department_id,level,academic_year_id,semester_id,term_id,default_exam_date,is_active)
        VALUES(?,?,?,?,?,?,?,1)";
  $stmt=$conn->prepare($sql);
  $stmt->bind_param("sisiiis", $name, $department_id, $level, $year_id, $sem_id, $term_id, $default_exam_date);
  if($stmt->execute()) jsonOut(true,["id"=>$stmt->insert_id]);
  jsonOut(false,$conn->error);
}

/* ---------- UPDATE ---------- */
if ($action === 'update') {
  $id = intval($_POST['id'] ?? 0);
  if ($id<=0) jsonOut(false,"Missing id");

  $name = trim($_POST['name'] ?? '');
  if ($name==='') jsonOut(false,"Subject name required");

  $level = $_POST['level'] ?? 'General';
  $department_id = nvl($_POST['department_id'] ?? null);
  $year_id = nvl($_POST['academic_year_id'] ?? null);
  $sem_id  = nvl($_POST['semester_id'] ?? null);
  $term_id = nvl($_POST['term_id'] ?? null);
  $default_exam_date = nvl($_POST['default_exam_date'] ?? null);

  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM subject WHERE name=? AND (academic_year_id <=> ?) AND id<>?");
  $stmt->bind_param("sii", $name, $year_id, $id);
  $stmt->execute(); $r=$stmt->get_result()->fetch_assoc();
  if(($r['c']??0)>0) jsonOut(false,"Subject name already exists for this Academic Year");

  $sql="UPDATE subject SET name=?, level=?, department_id=?, academic_year_id=?, semester_id=?, term_id=?, default_exam_date=? WHERE id=?";
  $stmt=$conn->prepare($sql);
  $stmt->bind_param("ssiiiisi", $name, $level, $department_id, $year_id, $sem_id, $term_id, $default_exam_date, $id);
  if($stmt->execute()) jsonOut(true,"Updated");
  jsonOut(false,$conn->error);
}

/* ---------- DELETE ---------- */
if ($action === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  if($id<=0) jsonOut(false,"Missing id");
  $stmt=$conn->prepare("DELETE FROM subject WHERE id=?");
  $stmt->bind_param("i",$id);
  if($stmt->execute()) jsonOut(true,"Deleted");
  jsonOut(false,$conn->error);
}

/* ---------- Departments list (for dropdown) ---------- */
if ($action === 'listDepartments') {
  $res=$conn->query("SELECT id, name FROM department ORDER BY name");
  if(!$res) jsonOut(false,$conn->error);
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  jsonOut(true,$out);
}

/* ---------- Assign subject to many classes (selected cohorts/classes) ---------- */
/* Params: subject_id, academic_year_id, semester_id, term_id, class_ids (csv) */
if ($action === 'assignSubjectToClasses') {
  $subject_id = intval($_POST['subject_id'] ?? 0);
  $year_id = intval($_POST['academic_year_id'] ?? 0);
  $sem_id  = intval($_POST['semester_id'] ?? 0);
  $term_id = intval($_POST['term_id'] ?? 0);
  $csv = trim($_POST['class_ids'] ?? '');
  if(!$subject_id || !$year_id || !$sem_id || !$term_id) jsonOut(false,"Missing required");
  $ids = array_filter(array_map('intval', explode(',', $csv)));
  if(empty($ids)) jsonOut(false,"No classes selected");

  $conn->begin_transaction();
  try{
    $stmt = $conn->prepare("INSERT IGNORE INTO class_subject (class_id, subject_id, academic_year_id, semester_id, term_id) VALUES (?,?,?,?,?)");
    $added=0;
    foreach($ids as $cid){
      $stmt->bind_param("iiiii", $cid, $subject_id, $year_id, $sem_id, $term_id);
      $stmt->execute();
      $added += ($stmt->affected_rows>0?1:0);
    }
    $conn->commit();
    jsonOut(true,["assigned"=>$added]);
  } catch(Exception $e){
    $conn->rollback();
    jsonOut(false,$e->getMessage());
  }
}

jsonOut(false,"Unknown action");
