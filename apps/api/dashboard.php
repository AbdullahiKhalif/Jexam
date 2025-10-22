<?php
// apps/api/dashboard.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

// production hygiene
ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok,$data){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }

/* ---------- helpers ---------- */
function fetch_label(mysqli $conn, string $sql, string $types='', ...$params){
  if(!$st=$conn->prepare($sql)) return null;
  if($types!=='') $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($v); $ok=$st->fetch(); $st->close();
  return $ok? $v: null;
}

/** Make cohort label from class code, e.g., CA218 -> CA21'S; already like CA24'S stays. */
function cohort_label(string $code): string {
  $raw = strtoupper(trim($code));
  if (preg_match("/^[A-Z]+[0-9]{2}'S$/", $raw)) return $raw;
  if (preg_match('/^([A-Z]+)\s*?([0-9]{2})/', $raw, $m)) return $m[1].$m[2]."'S";
  if (preg_match('/([A-Z]+)(\d{2})/', $raw, $m)) return $m[1].$m[2]."'S";
  return $raw;
}

/**
 * Build (Department, Cohort, Campus, Subject) for “Classes Having Exam Today”.
 * Strict filter by campus_id/shift_id when provided (non-zero).
 * Priority per class: exam_event(date) > subject.assigned_date(date).
 * Campus per class = dominant campus FROM class_hall_allocation for the selected period (+ shift if chosen).
 *
 * Output rows also include campus_id and shift_id so the frontend can be strict if needed.
 */
function classes_having_exam_today(mysqli $conn, array $filt, string $campusLabel): array {
  $year_id   = (int)$filt['year_id'];
  $sem_id    = (int)$filt['sem_id'];
  $term_id   = (int)$filt['term_id'];
  $campus_id = (int)$filt['campus_id']; // 0 = All Campuses
  $shift_id  = (int)$filt['shift_id'];  // 0 = All Shifts
  $date      = trim($filt['date']);

  // ---- 1) Dominant campus per class for this period (and shift if selected)
  $sqlDom = "
    WITH counts AS (
      SELECT cha.class_id, h.campus_id, COUNT(*) AS cnt
      FROM class_hall_allocation cha
      JOIN hall h ON h.id = cha.hall_id
      WHERE cha.academic_year_id=? AND cha.semester_id=? AND cha.term_id=?
      ".($shift_id ? " AND cha.shift_id=? " : "")."
      GROUP BY cha.class_id, h.campus_id
    ),
    pick AS (
      SELECT class_id, campus_id,
             ROW_NUMBER() OVER (PARTITION BY class_id ORDER BY cnt DESC, campus_id) AS rn
      FROM counts
    )
    SELECT class_id, campus_id
    FROM pick
    WHERE rn=1
  ";
  $typesDom = "iii"; $bindDom = [$year_id,$sem_id,$term_id];
  if ($shift_id){ $typesDom.="i"; $bindDom[]=$shift_id; }

  if(!$st=$conn->prepare($sqlDom)) out(false, 'prepare(domCampus) failed: '.$conn->error);
  $st->bind_param($typesDom, ...$bindDom);
  $st->execute(); $res=$st->get_result();
  $domCampus = []; // class_id => campus_id
  while($r=$res->fetch_assoc()){ $domCampus[(int)$r['class_id']] = (int)$r['campus_id']; }
  $st->close();

  // Campus names for display
  $campusNameById = [];
  if(!empty($domCampus)){
    $ids = array_values(array_unique(array_filter(array_values($domCampus))));
    if($ids){
      $in  = implode(',', array_fill(0, count($ids), '?'));
      $typ = str_repeat('i', count($ids));
      $sql = "SELECT id,name FROM campus WHERE id IN ($in)";
      if(!$st=$conn->prepare($sql)) out(false,'prepare(campus) failed: '.$conn->error);
      $st->bind_param($typ, ...$ids);
      $st->execute(); $res=$st->get_result();
      while($r=$res->fetch_assoc()) $campusNameById[(int)$r['id']] = (string)$r['name'];
      $st->close();
    }
  }

  // ---- 2) Events on the date (preferred)
  $sqlEv = "
    SELECT ev.class_id, ev.shift_id, s.subject_name
    FROM exam_event ev
    JOIN subject s ON s.id = ev.subject_id
    WHERE ev.academic_year_id=? AND ev.semester_id=? AND ev.term_id=?
      AND ev.exam_date=?
      ".($shift_id ? " AND ev.shift_id=? " : "")."
  ";
  $typesEv = "iiis"; $bindEv = [$year_id,$sem_id,$term_id,$date];
  if ($shift_id){ $typesEv.="i"; $bindEv[]=$shift_id; }

  if(!$st=$conn->prepare($sqlEv)) out(false, 'prepare(eventsToday) failed: '.$conn->error);
  $st->bind_param($typesEv, ...$bindEv);
  $st->execute(); $res=$st->get_result();
  $eventByClass = []; // class_id => ['subject'=>..., 'shift_id'=>...]
  while($r=$res->fetch_assoc()){
    $eventByClass[(int)$r['class_id']] = [
      'subject'  => (string)$r['subject_name'],
      'shift_id' => (int)$r['shift_id']
    ];
  }
  $st->close();

  // ---- 3) Fallback: subject.assigned_date = date (only if no event for that class)
  $sqlAsg = "
    SELECT cs.class_id, s.subject_name
    FROM class_subject cs
    JOIN subject s ON s.id = cs.subject_id
    WHERE cs.academic_year_id=? AND cs.semester_id=? AND cs.term_id=?
      AND s.assigned_date=?
  ";
  if(!$st=$conn->prepare($sqlAsg)) out(false, 'prepare(assignedToday) failed: '.$conn->error);
  $st->bind_param("iiis", $year_id,$sem_id,$term_id,$date);
  $st->execute(); $res=$st->get_result();
  $assignedByClass = []; // class_id => subject
  while($r=$res->fetch_assoc()){
    $cid = (int)$r['class_id'];
    if(!isset($eventByClass[$cid])){
      $assignedByClass[$cid] = (string)$r['subject_name'];
    }
  }
  $st->close();

  // Merge class ids
  $classIds = array_unique(array_merge(array_keys($eventByClass), array_keys($assignedByClass)));
  if(!$classIds) return [];

  // ---- 4) Class + department meta
  $in = implode(',', array_fill(0, count($classIds), '?'));
  $types = str_repeat('i', count($classIds));
  $sqlMeta = "SELECT c.id AS class_id, c.code, d.name AS department
              FROM class c
              JOIN department d ON d.id = c.department_id
              WHERE c.id IN ($in)";
  if(!$st=$conn->prepare($sqlMeta)) out(false, 'prepare(meta) failed: '.$conn->error);
  $st->bind_param($types, ...$classIds);
  $st->execute(); $res=$st->get_result();
  $meta = []; // class_id => ['code'=>..., 'department'=>...]
  while($r=$res->fetch_assoc()){
    $meta[(int)$r['class_id']] = [
      'code'       => (string)$r['code'],
      'department' => (string)$r['department']
    ];
  }
  $st->close();

  // ---- 5) Build rows; strictly honor campus/shift filters
  $rows = [];
  foreach($classIds as $cid){
    if(!isset($meta[$cid])) continue;

    // pick subject + shift for this class
    $subject = null;
    $rowShiftId = 0;
    if(isset($eventByClass[$cid])){
      $subject    = $eventByClass[$cid]['subject'];
      $rowShiftId = (int)$eventByClass[$cid]['shift_id'];
    } elseif(isset($assignedByClass[$cid])) {
      $subject    = $assignedByClass[$cid];
      $rowShiftId = 0; // unknown from assigned; still allowed if shift filter is 0
    } else {
      continue;
    }

    // strict shift filter: if a specific shift selected and this row has a shift (event) that doesn't match -> skip
    if($shift_id && $rowShiftId && $rowShiftId !== $shift_id) continue;

    // dominant campus for class within this period (+shift)
    $domCampusId = $domCampus[$cid] ?? 0;
    if($campus_id && $domCampusId !== $campus_id) continue; // strict campus filter

    $campusName = $domCampusId ? ($campusNameById[$domCampusId] ?? "Campus $domCampusId") : $campusLabel;
    $cohort     = cohort_label($meta[$cid]['code']);

    $rows[] = [
      'department' => $meta[$cid]['department'],
      'cohort'     => $cohort,
      'campus'     => $campusName,
      'subject'    => $subject,
      'campus_id'  => $domCampusId,
      'shift_id'   => $rowShiftId
    ];
  }

  if(!$rows) return [];

  // Deduplicate to one per (dept, cohort, campus)
  $seen = [];
  $out = [];
  foreach($rows as $r){
    $k = strtoupper($r['department']).'|'.strtoupper($r['cohort']).'|'.(int)$r['campus_id'];
    if(isset($seen[$k])) continue;
    $seen[$k]=1;
    $out[]=$r;
  }

  usort($out, function($a,$b){
    $d = strcasecmp($a['department'],$b['department']); if($d) return $d;
    $c = strcasecmp($a['cohort'],$b['cohort']);         if($c) return $c;
    return strcasecmp($a['campus'],$b['campus']);
  });

  return $out;
}

/* ---------- Controller ---------- */

if ($_SERVER['REQUEST_METHOD']!=='POST' || !isset($_POST['action'])) out(false, 'Action required');
$action = $_POST['action'];

/* ===== Summary action ===== */
if ($action === 'summary') {
  $year_id   = (int)($_POST['academic_year_id'] ?? 0);
  $sem_id    = (int)($_POST['semester_id'] ?? 0);
  $term_id   = (int)($_POST['term_id'] ?? 0);
  $campus_id = (int)($_POST['campus_id'] ?? 0);     // 0 = All Campuses
  $shift_id  = (int)($_POST['shift_id'] ?? 0);      // 0 = All Shifts
  $date      = trim($_POST['date'] ?? '');

  if(!$year_id || !$sem_id || !$term_id || $date==='') out(false,'Missing filters (Year/Sem/Term/Date).');

  // Meta labels
  $meta = [
    'year'      => fetch_label($conn,"SELECT name FROM academic_year WHERE id=?","i",$year_id),
    'semester'  => fetch_label($conn,"SELECT name FROM semester WHERE id=?","i",$sem_id),
    'term'      => fetch_label($conn,"SELECT name FROM term WHERE id=?","i",$term_id),
    'campus'    => $campus_id ? fetch_label($conn,"SELECT name FROM campus WHERE id=?","i",$campus_id) : 'All Campuses',
    'shift'     => $shift_id  ? fetch_label($conn,"SELECT name FROM shift  WHERE id=?","i",$shift_id)  : 'All Shifts',
    'date'      => $date
  ];

  /* === Existing “halls / allocations” logic (kept) === */
  $sql = "SELECT a.hall_id, h.name AS hall_name, h.capacity, a.allocated_students
          FROM class_hall_allocation a
          JOIN hall h ON h.id=a.hall_id
          WHERE a.academic_year_id=? AND a.semester_id=? AND a.term_id=?";
  $types = "iii"; $bind = [$year_id,$sem_id,$term_id];
  if ($campus_id) { $sql .= " AND h.campus_id=?"; $types.="i"; $bind[]=$campus_id; }
  if ($shift_id)  { $sql .= " AND a.shift_id=?";   $types.="i"; $bind[]=$shift_id; }

  if(!$st=$conn->prepare($sql)) out(false,'prepare(alloc) failed: '.$conn->error);
  $st->bind_param($types, ...$bind);
  $st->execute(); $res=$st->get_result();

  $engagedHalls=[]; $seats=0;
  while($r=$res->fetch_assoc()){
    $hid=(int)$r['hall_id'];
    $engagedHalls[$hid] = ($engagedHalls[$hid]??0) + (int)$r['allocated_students'];
    $seats += (int)$r['allocated_students'];
  }
  $st->close();

  $hallsOpen = count($engagedHalls);
  $capacity = 0;
  if($hallsOpen){
    $capSql="SELECT SUM(capacity) FROM hall WHERE id IN (".implode(',',array_keys($engagedHalls)).")";
    $r=$conn->query($capSql); $capacity=$r? (int)$r->fetch_row()[0]:0;
  }
  $utilization = $capacity>0? round(($seats/$capacity)*100,1):0;

  // Over-capacity halls
  $over=[]; foreach($engagedHalls as $id=>$alloc){
    $q=$conn->query("SELECT name,capacity FROM hall WHERE id=$id");
    if($q && $row=$q->fetch_assoc()){
      if($alloc > (int)$row['capacity']){
        $over[]=['hall_name'=>$row['name'],'capacity'=>(int)$row['capacity'],'allocated'=>$alloc];
      }
    }
  }

  // Empty halls (in selection)
  $empty=[];
  $hallWhere = $campus_id? "WHERE is_active=1 AND campus_id=$campus_id":"WHERE is_active=1";
  $q=$conn->query("SELECT id,name,capacity FROM hall $hallWhere");
  while($r=$q->fetch_assoc()){
    if(!isset($engagedHalls[$r['id']])){
      $empty[]=['hall_name'=>$r['name'],'capacity'=>(int)$r['capacity']];
    }
  }

  /* === NEW: Classes Having Exam Today === */
  $classesToday = classes_having_exam_today(
    $conn,
    [
      'year_id'   => $year_id,
      'sem_id'    => $sem_id,
      'term_id'   => $term_id,
      'campus_id' => $campus_id,
      'shift_id'  => $shift_id,
      'date'      => $date
    ],
    (string)$meta['campus']
  );

  out(true,[
    'meta' => $meta,
    'cards'=>[
      'halls_open'        => $hallsOpen,
      'seats_allocated'   => $seats,
      'engaged_capacity'  => $capacity,
      'utilization_pct'   => $utilization,
      'classes_covered'   => count($classesToday)
    ],
    'over_capacity' => $over,
    'empty_halls'   => $empty,
    'classes_today' => $classesToday
  ]);
}

/* ---- fallback ---- */
out(false,'Unknown action');
