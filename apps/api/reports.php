<?php
// apps/api/reports.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

function out($ok,$data=null){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }

/* ---- tolerant request parsing + action normalization ---- */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$REQ = [];
if (is_array($_GET))  $REQ += $_GET;
if (is_array($_POST)) $REQ += $_POST;
if (is_array($body))  $REQ += $body;

if (!isset($REQ['action'])) out(false,'Action required');
$action_raw = (string)$REQ['action'];
$norm = strtolower(trim(preg_replace('/\s+/', '', $action_raw)));
$map = [
  'listshiftsbycampus' => 'listShiftsByCampus',
  'allocationbycampus' => 'allocationByCampus',
  'allocationbydate'   => 'allocationByDate',
];
$action = $map[$norm] ?? $action_raw;

/* ---------- helpers ---------- */
function table_exists(mysqli $conn, string $name): bool {
  $sql="SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
  if(!$st=$conn->prepare($sql)) return false;
  $st->bind_param("s",$name); $st->execute(); $st->store_result();
  $ok=$st->num_rows>0; $st->close(); return $ok;
}
function fetch_label(mysqli $conn, string $sql, string $types, ...$params){
  if(!$st=$conn->prepare($sql)) return null;
  if($types!=='') $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($v); $ok=$st->fetch(); $st->close();
  return $ok? $v: null;
}

/* Canonicalize "Shift I/1/AM" etc. */
function canon_shift($name){
  $t=strtolower(trim($name));
  if(preg_match('/shift\s*(1|i)\b/',$t) || in_array($t,['1','i','am','morning','ft'],true)) return 'Shift I';
  if(preg_match('/shift\s*(2|ii)\b/',$t) || in_array($t,['2','ii','pm','afternoon','pt'],true)) return 'Shift II';
  if(preg_match('/shift\s*(3|iii)\b/',$t) || in_array($t,['3','iii','evening','night'],true)) return 'Shift III';
  return $name;
}

/* Campus shift policy */
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

/* Term window + weekday label */
function get_term_window(mysqli $conn, int $term_id): array {
  $st=$conn->prepare("SELECT start_date, end_date FROM term WHERE id=?");
  if(!$st) return [null,null];
  $st->bind_param("i",$term_id); $st->execute(); $st->bind_result($sd,$ed); $ok=$st->fetch(); $st->close();
  return $ok? [$sd?:null,$ed?:null] : [null,null];
}
function weekday_occurrence_label(string $date, ?string $start, ?string $end): string {
  $ts=strtotime($date); if($ts===false) return '';
  $name=date('l',$ts);
  if(!$start||!$end) return $name;
  $s=strtotime($start); $e=strtotime($end); if($s===false||$e===false) return $name;
  $dow=(int)date('w',$ts); $tot=0; $idx=0;
  for($d=$s;$d<=$e;$d=strtotime('+1 day',$d)){
    if((int)date('w',$d)===$dow){ $tot++; if(date('Y-m-d',$d)===$date) $idx=$tot; }
  }
  return ($tot>1 && $idx>0) ? "$name $idx" : $name;
}

/* ---------- support: shifts for a campus (filtered by policy) ---------- */
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
  $st->close();
  out(true,$rows);
}

/* ---------- reports ---------- */

/* 1) Allocation by campus (no subjects) */
if($action==='allocationByCampus'){
  $year_id =(int)($REQ['academic_year_id']??0);
  $sem_id  =(int)($REQ['semester_id']??0);
  $term_id =(int)($REQ['term_id']??0);
  $campus_id=(int)($REQ['campus_id']??0);
  $shift_id=(int)($REQ['shift_id']??0);
  if(!$year_id||!$sem_id||!$term_id||!$campus_id||!$shift_id) out(false,'Missing selectors');

  if(!shift_in_campus_and_allowed($conn,$shift_id,$campus_id)){
    out(false,'Selected shift is not allowed for this campus.');
  }

  $meta=[
    'campus'=>fetch_label($conn,"SELECT name FROM campus WHERE id=?","i",$campus_id),
    'year'=>fetch_label($conn,"SELECT name FROM academic_year WHERE id=?","i",$year_id),
    'semester'=>fetch_label($conn,"SELECT name FROM semester WHERE id=?","i",$sem_id),
    'term'=>fetch_label($conn,"SELECT name FROM term WHERE id=?","i",$term_id),
    'shift'=>null
  ];
  $st=$conn->prepare("SELECT name,
                        DATE_FORMAT(start_time, '%h:%i %p'),
                        DATE_FORMAT(end_time,   '%h:%i %p')
                      FROM shift WHERE id=?");
  $st->bind_param("i",$shift_id); $st->execute(); $st->bind_result($sn,$s1,$s2);
  if($st->fetch()) $meta['shift']=['name'=>$sn,'start_time'=>$s1,'end_time'=>$s2];
  $st->close();

  if(!table_exists($conn,'class_hall_allocation')) out(false,'No allocation table found (class_hall_allocation)');

  // Exclude zero-student rows at SQL level
  $sql="
    SELECT h.id AS hall_id,h.name AS hall_name,h.capacity,
           c.code AS class_code,a.allocated_students AS students
    FROM hall h
    JOIN class_hall_allocation a
      ON a.hall_id=h.id
     AND a.academic_year_id=?
     AND a.semester_id=?
     AND a.term_id=?
     AND a.shift_id=?
     AND a.allocated_students > 0         -- ✨ exclude zero/NULL rows
    JOIN `class` c ON c.id=a.class_id
    WHERE h.campus_id=?
    ORDER BY h.name,c.code";
  $st=$conn->prepare($sql);
  if(!$st) out(false,'prepare error: '.$conn->error);
  $st->bind_param("iiiii",$year_id,$sem_id,$term_id,$shift_id,$campus_id);
  $st->execute(); $res=$st->get_result();

  $halls=[];
  while($r=$res->fetch_assoc()){
    $hid=(int)$r['hall_id'];
    if(!isset($halls[$hid])) $halls[$hid]=[
      'hall_id'=>$hid,'hall_name'=>$r['hall_name'],
      'capacity'=>(int)$r['capacity'],'total'=>0,'rows'=>[]
    ];
    $stud=(int)$r['students']; // >0 guaranteed by SQL
    $halls[$hid]['rows'][]=['class_code'=>$r['class_code'],'students'=>$stud];
    $halls[$hid]['total'] += $stud;
  }
  // ✨ Only return halls that actually have students
  $halls=array_values(array_filter($halls,fn($h)=>($h['total']??0)>0));
  out(true,['meta'=>$meta,'halls'=>$halls]);
}

/* 2) Allocation by DATE (with subjects; strict by date+shift; prevent prefix bleed) */
if ($action === 'allocationByDate') {
  $year_id   = (int)($REQ['academic_year_id'] ?? 0);
  $sem_id    = (int)($REQ['semester_id'] ?? 0);
  $term_id   = (int)($REQ['term_id'] ?? 0);
  $campus_id = (int)($REQ['campus_id'] ?? 0);
  $shift_id  = (int)($REQ['shift_id'] ?? 0);
  $exam_date = trim($REQ['exam_date'] ?? '');

  if (!$year_id || !$sem_id || !$term_id || !$campus_id || !$shift_id || $exam_date === '') {
    out(false, 'Missing selectors');
  }

  if (!shift_in_campus_and_allowed($conn, $shift_id, $campus_id)) {
    out(false, 'Selected shift is not allowed for this campus.');
  }

  // ---- meta (now includes campus leaders) ----
  $meta = [
    'campus'         => fetch_label($conn, "SELECT name FROM campus WHERE id = ?",   "i", $campus_id),
    'campus_leaders' => fetch_label($conn, "SELECT leaders FROM campus WHERE id = ?", "i", $campus_id), // ✨ added
    'year'           => fetch_label($conn, "SELECT name FROM academic_year WHERE id = ?", "i", $year_id),
    'semester'       => fetch_label($conn, "SELECT name FROM semester WHERE id = ?",      "i", $sem_id),
    'term'           => fetch_label($conn, "SELECT name FROM term WHERE id = ?",          "i", $term_id),
    'shift'          => null,
    'date'           => $exam_date,
    'day_label'      => null
  ];

  $st = $conn->prepare("SELECT name, DATE_FORMAT(start_time,'%h:%i %p'), DATE_FORMAT(end_time,'%h:%i %p') FROM shift WHERE id = ?");
  $st->bind_param("i", $shift_id);
  $st->execute();
  $st->bind_result($sn, $s1, $s2);
  if ($st->fetch()) {
    $meta['shift'] = ['name' => $sn, 'start_time' => $s1, 'end_time' => $s2];
  }
  $st->close();

  [$ts, $te] = get_term_window($conn, $term_id);
  $meta['day_label'] = weekday_occurrence_label($exam_date, $ts, $te);

  if (!table_exists($conn, 'class_hall_allocation')) {
    out(false, 'No allocation table found (class_hall_allocation)');
  }

  $sql = "
    WITH dedup_alloc AS (
      SELECT
        a.hall_id, a.class_id,
        a.academic_year_id, a.semester_id, a.term_id, a.shift_id,
        SUM(a.allocated_students) AS allocated_students
      FROM class_hall_allocation a
      WHERE a.academic_year_id = ?
        AND a.semester_id      = ?
        AND a.term_id          = ?
        AND a.shift_id         = ?
      GROUP BY a.hall_id, a.class_id, a.academic_year_id, a.semester_id, a.term_id, a.shift_id
      HAVING SUM(a.allocated_students) > 0
    ),
    best_event AS (
      SELECT
        ev.class_id, ev.academic_year_id, ev.semester_id, ev.term_id, ev.shift_id, ev.exam_date,
        MAX(ev.id) AS best_id
      FROM exam_event ev
      JOIN subject s  ON s.id  = ev.subject_id
      JOIN `class` c2 ON c2.id = ev.class_id
      WHERE ev.academic_year_id = ?
        AND ev.semester_id      = ?
        AND ev.term_id          = ?
        AND ev.shift_id         = ?
        AND ev.exam_date        = ?
        AND s.department_id     = c2.department_id
        AND EXISTS (
          SELECT 1 FROM dedup_alloc da
          WHERE da.class_id         = ev.class_id
            AND da.academic_year_id = ev.academic_year_id
            AND da.semester_id      = ev.semester_id
            AND da.term_id          = ev.term_id
            AND da.shift_id         = ev.shift_id
        )
      GROUP BY ev.class_id, ev.academic_year_id, ev.semester_id, ev.term_id, ev.shift_id, ev.exam_date
    )
    SELECT
      h.id   AS hall_id,
      h.name AS hall_name,
      h.capacity,
      c.code AS class_code,
      COALESCE(s.subject_name, '') AS subject_name,
      COALESCE(s.level,'Specific') AS subject_type,
      ev.exam_date                 AS assigned_date,
      da.allocated_students        AS students
    FROM dedup_alloc da
    JOIN hall h    ON h.id = da.hall_id
    JOIN `class` c ON c.id = da.class_id
    LEFT JOIN exam_event ev
      ON ev.id = (
        SELECT be.best_id
        FROM best_event be
        WHERE be.class_id         = da.class_id
          AND be.academic_year_id = da.academic_year_id
          AND be.semester_id      = da.semester_id
          AND be.term_id          = da.term_id
          AND be.shift_id         = da.shift_id
      )
    LEFT JOIN subject s ON s.id = ev.subject_id
    WHERE h.campus_id = ?
      AND da.allocated_students > 0
    ORDER BY h.name, c.code, subject_name
  ";

  $st = $conn->prepare($sql);
  if (!$st) out(false, 'prepare error: ' . $conn->error);

  // bind: alloc (4) + best_event (4 + date) + campus (1) = 10 params
  $st->bind_param(
    "iiiiiiiisi",
    $year_id, $sem_id, $term_id, $shift_id,
    $year_id, $sem_id, $term_id, $shift_id, $exam_date,
    $campus_id
  );

  $st->execute();
  $res = $st->get_result();

  $halls = [];
  while ($r = $res->fetch_assoc()) {
    $hid = (int)$r['hall_id'];
    if (!isset($halls[$hid])) {
      $halls[$hid] = [
        'hall_id'   => $hid,
        'hall_name' => $r['hall_name'],
        'capacity'  => (int)$r['capacity'],
        'total'     => 0,
        'rows'      => []
      ];
    }
    $stud = (int)($r['students'] ?? 0);
    $halls[$hid]['rows'][] = [
      'class_code'    => $r['class_code'],
      'subject_name'  => $r['subject_name'],
      'subject_type'  => $r['subject_type'] ?? 'Specific',
      'assigned_date' => $r['assigned_date'],
      'students'      => $stud
    ];
    $halls[$hid]['total'] += $stud;
  }

  // only halls with content
  $halls = array_values(array_filter($halls, fn($h) => !empty($h['rows'])));

  out(true, ['meta' => $meta, 'halls' => $halls]);
}


  /* ---------- OPEN HALLS (by campus+shift over a period) ---------- */
/* Input:
   - academic_year_id, semester_id, term_id, campus_id, shift_id (required)
   Output:
   {
     meta: {...},
     count_open_halls: N,
     halls: [
       {
         hall_id, hall_name, capacity, total,
         rows: [{ class_code, students }]
       }, ...
     ]
   }
*/
if ($action === 'openHallsByCampusShift') {
  $year_id  = (int)($REQ['academic_year_id'] ?? 0);
  $sem_id   = (int)($REQ['semester_id'] ?? 0);
  $term_id  = (int)($REQ['term_id'] ?? 0);
  $campus_id= (int)($REQ['campus_id'] ?? 0);
  $shift_id = (int)($REQ['shift_id'] ?? 0);

  if (!$year_id || !$sem_id || !$term_id || !$campus_id || !$shift_id) {
    out(false, 'Missing selectors');
  }

  // policy: chosen shift must belong to campus & be allowed
  if (!shift_in_campus_and_allowed($conn, $shift_id, $campus_id)) {
    out(false, 'Selected shift is not allowed for this campus.');
  }

  $meta = [
    'campus'   => fetch_label($conn, "SELECT name FROM campus WHERE id=?", "i", $campus_id),
    'year'     => fetch_label($conn, "SELECT name FROM academic_year WHERE id=?", "i", $year_id),
    'semester' => fetch_label($conn, "SELECT name FROM semester WHERE id=?", "i", $sem_id),
    'term'     => fetch_label($conn, "SELECT name FROM term WHERE id=?", "i", $term_id),
    'shift'    => null
  ];
  $st = $conn->prepare("SELECT name, DATE_FORMAT(start_time,'%h:%i %p'), DATE_FORMAT(end_time,'%h:%i %p') FROM shift WHERE id=?");
  $st->bind_param("i", $shift_id); $st->execute(); $st->bind_result($sn,$s1,$s2);
  if ($st->fetch()) $meta['shift']=['name'=>$sn,'start_time'=>$s1,'end_time'=>$s2];
  $st->close();

  if (!table_exists($conn, 'class_hall_allocation')) {
    out(false, 'No allocation table found (class_hall_allocation).');
  }

  /* We consider a hall "open" for this period+shift if it has at least one
     allocation row (>0 students) in class_hall_allocation for the given
     year/sem/term/shift and belongs to the selected campus.
  */
  $sql = "
    SELECT
      h.id          AS hall_id,
      h.name        AS hall_name,
      h.capacity    AS capacity,
      a.allocated_students AS students
    FROM hall h
    JOIN class_hall_allocation a
      ON a.hall_id = h.id
     AND a.academic_year_id = ?
     AND a.semester_id      = ?
     AND a.term_id          = ?
     AND a.shift_id         = ?
     AND a.allocated_students > 0
    WHERE h.campus_id = ?
    ORDER BY h.name
  ";
  if (!$st = $conn->prepare($sql)) out(false, 'prepare(openHallsByCampusShift) failed: '.$conn->error);
  $st->bind_param("iiiii", $year_id, $sem_id, $term_id, $shift_id, $campus_id);
  $st->execute();
  $res = $st->get_result();

  $halls = [];
  while ($r = $res->fetch_assoc()) {
    $hid = (int)$r['hall_id'];
    if (!isset($halls[$hid])) {
      $halls[$hid] = [
        'hall_id'   => $hid,
        'hall_name' => $r['hall_name'],
        'capacity'  => (int)$r['capacity'],
        'total'     => 0,
        'rows'      => []
      ];
    }
    $stud = (int)($r['students'] ?? 0);
    if ($stud > 0) {
      $halls[$hid]['rows'][] = [
        'students'   => $stud
      ];
      $halls[$hid]['total'] += $stud;
    }
  }
  $st->close();

  // keep only halls that actually have rows
  $halls = array_values(array_filter($halls, fn($h) => !empty($h['rows'])));
  $count = count($halls);

  out(true, ['meta'=>$meta, 'count_open_halls'=>$count, 'halls'=>$halls]);
}

/* ---------- new: subjects assigned (grouped by department, filtered by date) ---------- */
if ($action === 'subjectsAssignedByDate') {
  $year_id   = (int)($REQ['academic_year_id'] ?? 0);
  $sem_id    = (int)($REQ['semester_id'] ?? 0);
  $term_id   = (int)($REQ['term_id'] ?? 0);
  $dept_id   = (int)($REQ['department_id'] ?? 0); // 0 or missing = All
  $exam_date = trim($REQ['exam_date'] ?? '');

  if (!$year_id || !$sem_id || !$term_id || $exam_date === '') {
    out(false, 'Missing selectors (Year/Semester/Term/Date required).');
  }

  // Meta for header
  $meta = [
    'year'     => fetch_label($conn, "SELECT name FROM academic_year WHERE id=?", "i", $year_id),
    'semester' => fetch_label($conn, "SELECT name FROM semester WHERE id=?", "i", $sem_id),
    'term'     => fetch_label($conn, "SELECT name FROM term WHERE id=?", "i", $term_id),
    'date'     => $exam_date,
    'department' => $dept_id
      ? fetch_label($conn, "SELECT name FROM department WHERE id=?", "i", $dept_id)
      : 'All Departments'
  ];

  // Pull assigned subjects on that date (exam_event table is canonical)
  // If multiple classes share the same cohort + subject on that day, we will compress in PHP.
  $sql = "
    SELECT
      d.id   AS dept_id,
      d.name AS dept_name,
      c.code AS class_code,
      s.subject_name
    FROM exam_event ev
    JOIN `class` c   ON c.id = ev.class_id
    JOIN subject s   ON s.id = ev.subject_id
    LEFT JOIN department d ON d.id = c.department_id
    WHERE ev.academic_year_id = ?
      AND ev.semester_id      = ?
      AND ev.term_id          = ?
      AND ev.exam_date        = ?
      AND (? = 0 OR d.id = ?)
    ORDER BY d.name, c.code, s.subject_name
  ";

  $st = $conn->prepare($sql);
  if (!$st) out(false, 'prepare(subjectsAssignedByDate) failed: '.$conn->error);
  $st->bind_param("iiisii", $year_id, $sem_id, $term_id, $exam_date, $dept_id, $dept_id);
  $st->execute();
  $res = $st->get_result();

  // Cohort label helper (e.g., "CA241" => "CA24's")
  $cohort_of = function(string $code): string {
    if (preg_match('/^([A-Za-z]+)(\d{2})/', $code, $m)) {
      return strtoupper($m[1].$m[2]."'s");
    }
    // fallback to code itself
    return strtoupper($code);
  };

  // Group by department
  $depts = []; // dept_id => ['dept_id','dept_name','rows'=>[['cohort','subject']]]
  while ($r = $res->fetch_assoc()) {
    $did   = (int)($r['dept_id'] ?? 0);
    $dname = $r['dept_name'] ?? 'Unassigned Department';
    if (!isset($depts[$did])) {
      $depts[$did] = ['dept_id'=>$did, 'dept_name'=>$dname, 'rows'=>[]];
    }
    $cohort = $cohort_of($r['class_code'] ?? '');
    $subject = trim((string)$r['subject_name']);

    // Deduplicate cohort+subject pairs in PHP (avoid repeats if many classes share same)
    $key = $cohort.'|'.$subject;
    if (!isset($depts[$did]['__seen'])) $depts[$did]['__seen'] = [];
    if (isset($depts[$did]['__seen'][$key])) continue;
    $depts[$did]['__seen'][$key] = true;

    $depts[$did]['rows'][] = ['cohort' => $cohort, 'subject' => $subject];
  }
  $st->close();

  // Sort cohorts inside each dept naturally (CA24's, CA23's...)
  foreach ($depts as &$D) {
    usort($D['rows'], function($a,$b){
      // Try to sort by cohort then by subject
      return strnatcasecmp($a['cohort'], $b['cohort']) ?: strnatcasecmp($a['subject'], $b['subject']);
    });
    unset($D['__seen']);
  }
  // Keep stable department order by name
  usort($depts, fn($a,$b)=>strnatcasecmp($a['dept_name'],$b['dept_name']));

  out(true, ['meta'=>$meta, 'departments'=>$depts]);
}


/* ---- fallback ---- */
out(false,'Unknown action: '.$action_raw);
