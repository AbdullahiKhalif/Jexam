<?php
// apps/api/report_subject_halls.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok,$data){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }

function cohort_from_code($code){
  $code = strtoupper(trim((string)$code));
  if (preg_match('/([A-Z]+)\s*?(\d{2})/', $code, $m)) {
    return $m[1].$m[2]."'s";
  }
  return $code;
}
function fetch_label(mysqli $conn,$sql,$types='',$params=[]){
  if(!$st=$conn->prepare($sql)) return null;
  if($types!=='') $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($v); $ok=$st->fetch(); $st->close();
  return $ok ? $v : null;
}

if ($_SERVER['REQUEST_METHOD']!=='POST' || !isset($_POST['action'])) out(false, 'Action required');
$action = $_POST['action'];

/**
 * ACTION: listSubjectsForFilter
 *  Inputs: academic_year_id, semester_id, term_id, campus_id (0=all), shift_id (0=all)
 *  Returns: [{id, name, exam_date, cohort}] — distinct per (cohort, subject, exam_date)
 *  Rule: Only include subjects that have exam events AND whose classes have hall allocations in the selected campus (+ shift if chosen) for that period.
 */
if ($action==='listSubjectsForFilter'){
  $year_id   = (int)($_POST['academic_year_id'] ?? 0);
  $sem_id    = (int)($_POST['semester_id'] ?? 0);
  $term_id   = (int)($_POST['term_id'] ?? 0);
  $campus_id = (int)($_POST['campus_id'] ?? 0);
  $shift_id  = (int)($_POST['shift_id'] ?? 0);

  if(!$year_id || !$sem_id || !$term_id) out(false,'Missing filters');

  // Build SQL
  $sql = "
    SELECT DISTINCT
      ev.subject_id                         AS id,
      s.subject_name                         AS name,
      ev.exam_date                           AS exam_date,
      c.code                                 AS class_code,
      h.campus_id                            AS campus_id
    FROM exam_event ev
    JOIN subject s            ON s.id = ev.subject_id
    JOIN class c              ON c.id = ev.class_id
    JOIN class_hall_allocation a
                              ON a.class_id = ev.class_id
                             AND a.academic_year_id = ev.academic_year_id
                             AND a.semester_id      = ev.semester_id
                             AND a.term_id          = ev.term_id
    JOIN hall h               ON h.id = a.hall_id
    WHERE ev.academic_year_id = ?
      AND ev.semester_id      = ?
      AND ev.term_id          = ?
  ";
  $types = "iii";
  $bind  = [$year_id,$sem_id,$term_id];

  if ($campus_id) { $sql .= " AND h.campus_id = ?"; $types.="i"; $bind[]=$campus_id; }
  if ($shift_id)  {
    // Must match the selected shift (both event & allocation)
    $sql .= " AND ev.shift_id = ? AND a.shift_id = ?";
    $types.="ii"; $bind[]=$shift_id; $bind[]=$shift_id;
  }

  // Execute
  if(!$st=$conn->prepare($sql)) out(false,'prepare(listSubjects) failed: '.$conn->error);
  $st->bind_param($types, ...$bind);
  $st->execute(); $res=$st->get_result();

  $rows = [];
  while($r=$res->fetch_assoc()){
    $rows[] = [
      'id'        => (int)$r['id'],
      'name'      => (string)$r['name'],
      'exam_date' => (string)$r['exam_date'],
      'cohort'    => cohort_from_code($r['class_code']),
      'campus_id' => (int)$r['campus_id'],
    ];
  }
  $st->close();

  // Deduplicate by (cohort, subject_id, date)
  $seen=[]; $out=[];
  foreach($rows as $x){
    $k = $x['cohort'].'|'.$x['id'].'|'.$x['exam_date'];
    if(isset($seen[$k])) continue;
    $seen[$k]=1; $out[]=$x;
  }

  out(true,$out);
}

/**
 * ACTION: subjectHalls
 *  Inputs: academic_year_id, semester_id, term_id, campus_id (0=all), shift_id (0=all),
 *          subject_id, (optional) exam_date
 *  Output: { meta:{labels}, rows:[{department, cohort, class_code, hall_name, capacity, allocated, campus, shift}] }
 */
if ($action==='subjectHalls'){
  $year_id   = (int)($_POST['academic_year_id'] ?? 0);
  $sem_id    = (int)($_POST['semester_id'] ?? 0);
  $term_id   = (int)($_POST['term_id'] ?? 0);
  $campus_id = (int)($_POST['campus_id'] ?? 0);
  $shift_id  = (int)($_POST['shift_id'] ?? 0);
  $subject_id= (int)($_POST['subject_id'] ?? 0);
  $exam_date = trim($_POST['exam_date'] ?? ''); // optional

  if(!$year_id||!$sem_id||!$term_id||!$subject_id) out(false,'Missing filters');

  // labels
  $meta = [
    'year'      => fetch_label($conn,"SELECT name FROM academic_year WHERE id=?","i",[$year_id]),
    'semester'  => fetch_label($conn,"SELECT name FROM semester WHERE id=?","i",[$sem_id]),
    'term'      => fetch_label($conn,"SELECT name FROM term WHERE id=?","i",[$term_id]),
    'campus'    => $campus_id ? fetch_label($conn,"SELECT name FROM campus WHERE id=?","i",[$campus_id]) : 'All Campuses',
    'shift'     => $shift_id  ? fetch_label($conn,"SELECT name FROM shift  WHERE id=?","i",[$shift_id])  : 'All Shifts',
  ];

  // Pull class + halls for the subject within filters
  $sql = "
    SELECT
      d.name         AS department,
      c.code         AS class_code,
      h.name         AS hall_name,
      h.capacity     AS capacity,
      a.allocated_students AS allocated,
      cp.name        AS campus,
      sh.name        AS shift_name
    FROM exam_event ev
    JOIN class c              ON c.id = ev.class_id
    JOIN department d         ON d.id = c.department_id
    JOIN class_hall_allocation a
                              ON a.class_id = ev.class_id
                             AND a.academic_year_id = ev.academic_year_id
                             AND a.semester_id      = ev.semester_id
                             AND a.term_id          = ev.term_id
    JOIN hall h               ON h.id = a.hall_id
    JOIN campus cp            ON cp.id = h.campus_id
    JOIN shift  sh            ON sh.id = a.shift_id
    WHERE ev.academic_year_id = ?
      AND ev.semester_id      = ?
      AND ev.term_id          = ?
      AND ev.subject_id       = ?
  ";
  $types="iiii"; $bind=[$year_id,$sem_id,$term_id,$subject_id];

  if ($exam_date !== ''){
    $sql .= " AND ev.exam_date = ?"; $types.="s"; $bind[]=$exam_date;
  }
  if ($campus_id){
    $sql .= " AND h.campus_id = ?"; $types.="i"; $bind[]=$campus_id;
  }
  if ($shift_id){
    // keep consistent shift across both sides
    $sql .= " AND ev.shift_id = ? AND a.shift_id = ?"; $types.="ii"; $bind[]=$shift_id; $bind[]=$shift_id;
  }

  $sql .= " ORDER BY d.name, c.code, h.name";

  if(!$st=$conn->prepare($sql)) out(false,'prepare(subjectHalls) failed: '.$conn->error);
  $st->bind_param($types, ...$bind);
  $st->execute(); $res=$st->get_result();

  $rows=[];
  while($r=$res->fetch_assoc()){
    $rows[] = [
      'department'  => (string)$r['department'],
      'cohort'      => cohort_from_code($r['class_code']),
      'class_code'  => (string)$r['class_code'],
      'hall_name'   => (string)$r['hall_name'],
      'capacity'    => (int)$r['capacity'],
      'allocated'   => (int)$r['allocated'],
      'campus'      => (string)$r['campus'],
      'shift'       => (string)$r['shift_name'],
    ];
  }
  $st->close();

  out(true, ['meta'=>$meta, 'rows'=>$rows]);
}

out(false,'Unknown action');
