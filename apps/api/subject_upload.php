<?php
// apps/api/subject_upload.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

// keep JSON clean in production
ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok,$data){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !isset($_POST['action'])) out(false,'Action required');
$action = $_POST['action'];

/* ====== XLSX helpers (no composer) ====== */
function xlsx_open_zip($path){
  $z = new ZipArchive();
  $ok = ($z->open($path) === TRUE);
  return [$ok ? $z : null, $ok ? null : 'Failed to open .xlsx'];
}
function xlsx_text($zip,$file){ $fp=$zip->getStream($file); if(!$fp) return null; $s=stream_get_contents($fp); fclose($fp); return $s; }
function xlsx_shared_strings($zip){
  $xml=xlsx_text($zip,'xl/sharedStrings.xml'); if($xml===null) return [];
  $sx=new SimpleXMLElement($xml); $out=[];
  foreach($sx->si as $si){
    $out[] = isset($si->t) ? (string)$si->t
           : implode('', array_map(fn($r)=>(string)$r->t, iterator_to_array($si->r)));
  }
  return $out;
}
function xlsx_sheets($zip){
  $wb=xlsx_text($zip,'xl/workbook.xml'); if($wb===null) return [];
  $rels=xlsx_text($zip,'xl/_rels/workbook.xml.rels'); $rid2target=[];
  if($rels){ $sx=new SimpleXMLElement($rels); foreach($sx->Relationship as $r){ $rid2target[(string)$r['Id']] = (string)$r['Target']; } }
  $sx=new SimpleXMLElement($wb); $sheets=[];
  foreach($sx->sheets->sheet as $sh){
    $rid=(string)$sh->attributes('r',true)['id']; $t=$rid2target[$rid]??null;
    if($t) $sheets[]=['name'=>(string)$sh['name'],'path'=>"xl/".$t];
  }
  return $sheets;
}
function xlsx_rows($zip,$sheetPath,$shared){
  $xml = xlsx_text($zip,$sheetPath); if($xml===null) return [];
  $rows = [];
  $xr = new XMLReader();
  $xr->XML($xml, null, LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);

  // Always start from row A3 (skip headers)
  $START_ROW = 3;

  while ($xr->read()) {
    if ($xr->nodeType == XMLReader::ELEMENT && $xr->name === 'row') {
      $row = new SimpleXMLElement($xr->readOuterXML());

      // row index
      $ri = isset($row['r']) ? intval($row['r']) : null;
      if ($ri === null) {
        foreach ($row->c as $c) {
          if (isset($c['r'])) { $ri = intval(preg_replace('/^[A-Z]+/i','',(string)$c['r'])); break; }
        }
      }
      if ($ri !== null && $ri < $START_ROW) { continue; }

      // cells
      $arr = [];
      foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $col = preg_replace('/\d+/', '', $ref);
        $t = (string)$c['t'];
        $v = isset($c->v) ? (string)$c->v : '';

        if ($t === 's') {
          $idx = ctype_digit($v) ? intval($v) : -1;
          $val = $idx >= 0 && isset($shared[$idx]) ? $shared[$idx] : '';
        } elseif ($t === 'inlineStr') {
          $val = isset($c->is->t) ? (string)$c->is->t : '';
        } else {
          $val = $v;
        }
        $arr[$col] = trim((string)$val);
      }

      // Only consider first 6 columns (A–F)
      $dense = [];
      foreach (['A','B','C','D','E','F'] as $L) { $dense[] = $arr[$L] ?? ''; }
      $rows[] = $dense;
    }
  }
  $xr->close();
  return $rows;
}
function excel_serial_to_date($v){
  if($v===''||!is_numeric($v)) return null;
  $base=new DateTime('1899-12-30'); $base->modify('+'.intval($v).' days'); return $base->format('Y-m-d');
}

/* ====== detection helpers ====== */
function has_letters($s){ return preg_match('/[A-Za-z]/',$s)===1; }
function is_intish($s){ return preg_match('/^\d+$/',$s)===1; }
function is_dateish($s){ return $s!=='' && (preg_match('/^\d{4}-\d{2}-\d{2}$/',$s) || (is_numeric($s) && (int)$s>20000 && (int)$s<60000)); }

/* ====== level/Type handling ====== */
function normalize_level($s){
  $t = strtolower(trim((string)$s));
  if($t==='') return 'Specific';
  // strip non-letters to allow e.g. "G / general"
  $t = preg_replace('/[^a-z]/','',$t);

  // General synonyms
  if ($t==='g' || $t==='gen' || $t==='general' || $t==='generalstudies' || $t==='generalstudy'
      || $t==='common' || $t==='foundation' || $t==='found' || $t==='gs') {
    return 'General';
  }
  // Specific synonyms
  if ($t==='s' || $t==='spec' || $t==='specific' || $t==='sp') {
    return 'Specific';
  }
  // default
  return 'Specific';
}
function is_typeish($s){
  $t = strtolower(trim((string)$s));
  if($t==='') return false;
  $t = preg_replace('/[^a-z]/','',$t);
  return in_array($t, [
    'g','gen','general','generalstudies','generalstudy',
    'common','foundation','found','gs',
    's','spec','specific','sp'
  ], true);
}

/* Detect Subject + Date + Type columns (Type is optional) */
function detect_columns_subject_date_type($rows){
  $n = min(count($rows), 50);
  $scoreDate = array_fill(0,6,0);
  $scoreText = array_fill(0,6,0);
  $scoreType = array_fill(0,6,0);

  for($i=0; $i<$n; $i++){
    $r = $rows[$i];
    for($c=0; $c<6; $c++){
      $v = $r[$c] ?? '';
      if($v==='') continue;
      if (is_dateish($v)) $scoreDate[$c]++;
      if (!is_dateish($v) && has_letters($v)) $scoreText[$c]++;
      if (is_typeish($v)) $scoreType[$c]++;
    }
  }

  // pick the most date-like column
  $dateIdx = array_keys($scoreDate, max($scoreDate))[0] ?? 1;

  // pick subject column as "most text-like that's not the date"
  $subjIdx = 0; $best = -1;
  for($c=0; $c<6; $c++){
    if($c === $dateIdx) continue;
    $score = $scoreText[$c] - $scoreDate[$c];
    if($score > $best){ $best = $score; $subjIdx = $c; }
  }

  // pick type column as "most type-like that's not date or subject"
  $typeIdx = null; $tbest = 0;
  for($c=0; $c<6; $c++){
    if($c === $dateIdx || $c === $subjIdx) continue;
    if($scoreType[$c] > $tbest){ $tbest = $scoreType[$c]; $typeIdx = $c; }
  }

  return ['subject'=>$subjIdx, 'date'=>$dateIdx, 'type'=>$typeIdx]; // type may be null
}

/* ====== cohort helpers ====== */
function classes_for_cohort(mysqli $conn,$department_id,$prefix){
  $like=$prefix.'%';
  if($department_id){
    $st=$conn->prepare("SELECT id,code FROM `class` WHERE code LIKE ? AND department_id=? AND is_active=1");
    if(!$st){ out(false, 'prepare(classes_for_cohort dept) failed: '.$conn->error); }
    $st->bind_param("si",$like,$department_id);
  }else{
    $st=$conn->prepare("SELECT id,code FROM `class` WHERE code LIKE ? AND is_active=1");
    if(!$st){ out(false, 'prepare(classes_for_cohort any) failed: '.$conn->error); }
    $st->bind_param("s",$like);
  }
  $st->execute(); $res=$st->get_result(); $arr=[]; while($r=$res->fetch_assoc()) $arr[]=$r; $st->close(); return $arr;
}
function find_classes_for_sheet(mysqli $conn, $department_id, $sheetName){
  $prefix=null;
  if(preg_match("/([A-Za-z]+)\\s*(\\d{2})/",$sheetName,$m)) $prefix=strtoupper($m[1].$m[2]);
  elseif(preg_match("/([A-Za-z]+\\d{2})/",$sheetName,$m))   $prefix=strtoupper($m[1]);
  if($prefix){ $cls=classes_for_cohort($conn,$department_id,$prefix); if(!empty($cls)) return [$prefix,$cls]; }
  return [null,[]];
}

/* ====== subject + links ====== */
function find_or_create_subject(mysqli $conn,$subject_name,$department_id,&$created=false,$assigned_date=null,$shift_id=null,$level='Specific'){
  $created=false;
  $st=$conn->prepare("SELECT id FROM subject WHERE subject_name=? AND (department_id<=>?) LIMIT 1");
  if(!$st){ out(false,'prepare(select subject) failed: '.$conn->error); }
  $st->bind_param("si",$subject_name,$department_id);
  $st->execute(); $st->bind_result($id);
  if($st->fetch()){
    $st->close();
    $u=$conn->prepare("UPDATE subject SET assigned_date=?, shift_id=?, level=? WHERE id=?");
    if(!$u){ out(false,'prepare(update subject) failed: '.$conn->error); }
    $u->bind_param("sisi",$assigned_date,$shift_id,$level,$id);
    if(!$u->execute()){ out(false,'execute(update subject) failed: '.$conn->error); }
    $u->close();
    return (int)$id;
  }
  $st->close();

  $ins=$conn->prepare("INSERT INTO subject(subject_name,department_id,level,assigned_date,shift_id,is_active) VALUES(?,?,?,?,?,1)");
  if(!$ins){ out(false,'prepare(insert subject) failed: '.$conn->error); }
  $ins->bind_param("sissi",$subject_name,$department_id,$level,$assigned_date,$shift_id);
  if(!$ins->execute()){ out(false,'execute(insert subject) failed: '.$conn->error); }
  $sid=(int)$ins->insert_id; $ins->close();
  $created=true; return $sid;
}
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

/* ====== pick shift automatically from class_hall_allocation (by period) ====== */
function get_class_shift_for_period(mysqli $conn, int $class_id, int $year_id, int $sem_id, int $term_id): ?int {
  $sql = "SELECT shift_id, COUNT(*) AS cnt
          FROM class_hall_allocation
          WHERE class_id=? AND academic_year_id=? AND semester_id=? AND term_id=?
          GROUP BY shift_id
          ORDER BY cnt DESC, shift_id ASC
          LIMIT 1";
  if(!$st=$conn->prepare($sql)) out(false,'prepare(get_class_shift_for_period) failed: '.$conn->error);
  $st->bind_param("iiii",$class_id,$year_id,$sem_id,$term_id);
  $st->execute(); $st->bind_result($sid,$cnt);
  $ok=$st->fetch(); $st->close();
  return $ok ? (int)$sid : null;
}

/* ====== exam_event upsert (slot-based) ====== */
function upsert_exam_event(mysqli $conn,$class_id,$subject_id,$year_id,$sem_id,$term_id,$exam_date,$shift_id,$duration=120){
  if(!$shift_id || !$exam_date) return 0; // nothing

  // Exact slot (class + period + shift + date)
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
    return 2; // updated
  }
  $sel->close();

  $ins=$conn->prepare("INSERT INTO exam_event
    (class_id, academic_year_id, semester_id, term_id, shift_id, exam_date, subject_id, duration_minutes, status)
    VALUES (?,?,?,?,?,?,?,?,'draft')");
  if(!$ins){ out(false,'prepare(insert exam_event) failed: '.$conn->error); }
  $ins->bind_param("iiiiisii",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date,$subject_id,$duration);
  if(!$ins->execute()){ out(false,'execute(insert exam_event) failed: '.$conn->error); }
  $ins->close();
  return 1; // created
}

/* ====== Controller ====== */
if ($action==='upload'){
  $year_id=(int)($_POST['academic_year_id']??0);
  $sem_id=(int)($_POST['semester_id']??0);
  $term_id=(int)($_POST['term_id']??0);
  $dept_id=(int)($_POST['department_id']??0);
  $duration=(int)($_POST['default_duration']??120);

  // Optional: force a shift for all rows (if you pass it)
  $forced_shift_id = (int)($_POST['shift_id'] ?? 0);

  if(!$year_id||!$sem_id||!$term_id||!$dept_id) out(false,'Year/Semester/Term/Department are required.');
  if(!isset($_FILES['file'])) out(false,'File is required.');
  $f=$_FILES['file']; if($f['error']!==UPLOAD_ERR_OK) out(false,'Upload error: '.$f['error']);
  if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='xlsx') out(false,'Only .xlsx files are allowed');

  list($zip,$err)=xlsx_open_zip($f['tmp_name']); if(!$zip) out(false,$err);
  $shared=xlsx_shared_strings($zip); $sheets=xlsx_sheets($zip);

  // ===== summary (initialize all keys) =====
  $summary=[
    'sheets'           => [],
    'subjects_created' => 0,
    'subjects_updated' => 0,
    'links_created'    => 0,
    'events_created'   => 0,
    'events_updated'   => 0,
    'skipped_rows'     => 0
  ];

  $conn->begin_transaction();

  foreach($sheets as $sh){
    $sheetName=$sh['name'];
    list($prefix,$classes) = find_classes_for_sheet($conn,$dept_id,$sheetName);
    if(!$prefix || !$classes){
      $summary['sheets'][]=['name'=>$sheetName,'error'=>'No classes for this sheet'];
      continue;
    }

    $rows=xlsx_rows($zip,$sh['path'],$shared);
    if(!$rows){
      $summary['sheets'][]=['name'=>$sheetName,'prefix'=>$prefix,'error'=>'Empty sheet'];
      continue;
    }

    // detect subject + date + (optional) type column
    $map=detect_columns_subject_date_type($rows);

    // per-sheet stats (include events_updated)
    $stat=[
      'name'              => $sheetName,
      'prefix'            => $prefix,
      'rows'              => 0,
      'created_subjects'  => 0,
      'updated_subjects'  => 0,
      'linked'            => 0,
      'events'            => 0, // created
      'events_updated'    => 0, // updated
      'skipped'           => 0
    ];

    foreach($rows as $row){
      $subject_name=$row[$map['subject']]??''; 
      $d=$row[$map['date']]??'';
      $raw_type = (isset($map['type']) && $map['type']!==null) ? ($row[$map['type']] ?? '') : '';

      if($subject_name==='' && $d===''){ $stat['skipped']++; continue; }

      // try salvage subject text if a number leaked into subject column
      if(is_intish($subject_name)){
        $cand='';
        foreach($row as $c){
          if($c!=='' && has_letters($c) && !is_dateish($c)){
            if(strlen($c)>strlen($cand)) $cand=$c;
          }
        }
        if($cand!=='') $subject_name=$cand;
      }

      // swap if columns are reversed
      if(is_dateish($subject_name) && !is_dateish($d)){ $tmp=$d; $d=$subject_name; $subject_name=$tmp; }

      $subject_name=trim($subject_name);
      if($subject_name===''){ $stat['skipped']++; continue; }

      // normalize type -> level (General/Specific)
      $level = normalize_level($raw_type);

      // parse date from Excel
      $assigned_date=null;
      if($d!==''){
        $assigned_date = preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) ? $d : (is_numeric($d)? excel_serial_to_date($d): null);
      }

      // Subject upsert (by department + name) with level
      $created=false;
      $sid = find_or_create_subject($conn,$subject_name,$dept_id,$created,$assigned_date,null,$level);
      if(!$sid){ $stat['skipped']++; continue; }
      if($created) $stat['created_subjects']++; else $stat['updated_subjects']++;

      $subject_shift_applied = null;

      // For all classes in the cohort/sheet
      foreach($classes as $cl){
        $class_id = (int)$cl['id'];

        // choose shift: forced OR most frequent from allocation
        $shift_id_to_use = $forced_shift_id ?: get_class_shift_for_period($conn,$class_id,$year_id,$sem_id,$term_id);

        // link class ↔ subject (per period) — idempotent
        upsert_class_subject($conn,$class_id,$sid,$year_id,$sem_id,$term_id);
        $stat['linked']++;

        // create/update exam_event when we have both date & shift
        if($assigned_date && $shift_id_to_use){
          $res = upsert_exam_event($conn,$class_id,$sid,$year_id,$sem_id,$term_id,$assigned_date,$shift_id_to_use,$duration);
          if($res===1){ $stat['events']++; $summary['events_created']++; }
          elseif($res===2){ $stat['events_updated']++; $summary['events_updated']++; }
          if($subject_shift_applied===null && $res){ $subject_shift_applied = $shift_id_to_use; }
        }
      }

      // backfill subject.shift_id once per subject for consistency
      if($subject_shift_applied!==null){
        $u=$conn->prepare("UPDATE subject SET shift_id=? WHERE id=?");
        if($u){ $u->bind_param("ii",$subject_shift_applied,$sid); $u->execute(); $u->close(); }
      }

      $stat['rows']++;
    }

    // roll up per-sheet stats into the global summary
    $summary['subjects_created'] += $stat['created_subjects'];
    $summary['subjects_updated'] += $stat['updated_subjects'];
    $summary['links_created']    += $stat['linked'];
    $summary['events_created']   += $stat['events'];
    $summary['events_updated']   += $stat['events_updated'];
    $summary['skipped_rows']     += $stat['skipped'];

    $summary['sheets'][] = $stat;
  }

  $conn->commit();
  $zip->close();
  out(true,$summary);
}

out(false,'Unknown action');