<?php
// apps/api/subject_upload.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conn.php';

ini_set('display_errors','0');
ini_set('log_errors','1');

function out($ok,$data){
  echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD']!=='POST' || !isset($_POST['action'])) out(false,'Action required');
$action = $_POST['action'];

/* ================= XLSX helpers (no composer) ================ */
function xlsx_open_zip($path){ $z=new ZipArchive(); $ok=($z->open($path)===TRUE); return [$ok?$z:null, $ok?null:'Failed to open .xlsx']; }
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
/** Read a sheet with header on row 1. Returns [$hIndex, $rows, $headersRaw]. */
function xlsx_rows_with_headers($zip,$sheetPath,$shared){
  $xml = xlsx_text($zip,$sheetPath); if($xml===null) return [[],[]];
  $xr = new XMLReader();
  $xr->XML($xml, null, LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);

  $rows=[]; $headers=[]; $seenHeader=false;

  while ($xr->read()) {
    if ($xr->nodeType == XMLReader::ELEMENT && $xr->name === 'row') {
      $row = new SimpleXMLElement($xr->readOuterXML());

      $arr = []; $maxColIdx = 0; $cellsByCol=[];
      foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $col = preg_replace('/\d+/', '', $ref);
        $ci = 0; for($i=0;$i<strlen($col);$i++){ $ci = $ci*26 + (ord($col[$i]) - 64); }
        if($ci>$maxColIdx) $maxColIdx=$ci;

        $t = (string)$c['t']; $v = isset($c->v) ? (string)$c->v : '';
        if ($t === 's') {
          $idx = ctype_digit($v) ? intval($v) : -1;
          $val = $idx >= 0 && isset($shared[$idx]) ? $shared[$idx] : '';
        } elseif ($t === 'inlineStr') {
          $val = isset($c->is->t) ? (string)$c->is->t : '';
        } else {
          $val = $v;
        }
        $cellsByCol[$ci] = trim((string)$val);
      }
      for($i=1;$i<=$maxColIdx;$i++){ $arr[] = $cellsByCol[$i] ?? ''; }

      if(!$seenHeader){
        $headers = array_map(function($h){
          $h = strtolower(trim($h));
          $h = preg_replace('/\s+/', ' ', $h);
          $map = [
            'no'             => 'no',
            'department'     => 'department',
            'dept'           => 'department',
            'department name'=> 'department',
            'class'          => 'class',
            'class code'     => 'class',
            'course code'    => 'course_code',
            'code'           => 'course_code',
            'course name'    => 'course_name',
            'subject'        => 'course_name',
            'shift'          => 'shift',
            'date'           => 'date',
            'exam date'      => 'date',
            'campus'         => 'campus',
          ];
          return $map[$h] ?? $h;
        }, $arr);
        $seenHeader=true;
      }else{
        $rows[] = $arr;
      }
    }
  }
  $xr->close();

  $hIndex=[]; foreach($headers as $i=>$h){ if($h!=='') $hIndex[$h]=$i; }
  return [$hIndex,$rows,$headers];
}

/* ================= Date utils ================== */
function excel_serial_to_date($v){
  if($v===''||!is_numeric($v)) return null;
  $base=new DateTime('1899-12-30'); $base->modify('+'.intval($v).' days'); return $base->format('Y-m-d');
}
function parse_date_flex($s){
  $s = trim((string)$s);
  if($s==='') return null;
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) return $s;         // YYYY-MM-DD
  if (is_numeric($s)) return excel_serial_to_date($s);           // Excel serial
  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',$s,$m)){
    $d=str_pad($m[1],2,'0',STR_PAD_LEFT);
    $M=str_pad($m[2],2,'0',STR_PAD_LEFT);
    return $m[3].'-'.$M.'-'.$d;
  }
  return null;
}

/* ================= General utils ================== */
function column_exists(mysqli $c, $table, $col){
  $res = $c->query("SHOW COLUMNS FROM `".$c->real_escape_string($table)."` LIKE '".$c->real_escape_string($col)."'");
  return $res && $res->num_rows>0;
}
function normalize_subject($name){
  $name = preg_replace('/\s+/u',' ', trim((string)$name));
  return $name;
}

/* ============= CIT-by-class-code helpers ============= */
function is_cit_by_classcode(string $code): bool {
  $t = strtoupper(trim($code));
  return preg_match('/^(CA|CNS|CM)\d{2}/', $t) === 1; // extend if you add more prefixes
}

/* DB fallback: does a CIT-class event exist for this subj+date in this year+sem (any term)? */
function exists_cit_event_db(mysqli $c,int $year,int $sem,string $subject_name,string $exam_date): bool {
  $sql="SELECT 1
        FROM exam_event ev
        JOIN subject s ON s.id=ev.subject_id
        JOIN class c   ON c.id=ev.class_id
        WHERE ev.academic_year_id=? AND ev.semester_id=? AND ev.exam_date=?
          AND s.subject_name=? AND c.code REGEXP '^(CA|CNS|CM)'
        LIMIT 1";
  if(!$st=$c->prepare($sql)) return false;
  $st->bind_param("iiss",$year,$sem,$exam_date,$subject_name);
  $st->execute(); $st->store_result(); $ok=$st->num_rows>0; $st->close();
  return $ok;
}

/* ================= Tolerant lookups ================== */
function get_department_id(mysqli $c,$nameOrCode){
  $nameOrCode = trim((string)$nameOrCode);
  if ($nameOrCode==='') return null;

  $q="SELECT id FROM department WHERE name=? OR code=? LIMIT 1";
  if($st=$c->prepare($q)){ $st->bind_param("ss",$nameOrCode,$nameOrCode); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  $q2="SELECT id FROM department WHERE LOWER(name)=LOWER(?) OR LOWER(code)=LOWER(?) LIMIT 1";
  if($st=$c->prepare($q2)){ $st->bind_param("ss",$nameOrCode,$nameOrCode); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  return null;
}
function get_class_id(mysqli $c,$code,$deptId=null){
  $code = trim((string)$code);
  if ($code==='') return null;

  if($deptId){
    $q="SELECT id FROM class
        WHERE UPPER(TRIM(code)) = UPPER(TRIM(?))
          AND department_id = ?
          AND is_active = 1
        LIMIT 1";
    if(!$st=$c->prepare($q)) return null;
    $st->bind_param("si",$code,$deptId);
  }else{
    $q="SELECT id FROM class
        WHERE UPPER(TRIM(code)) = UPPER(TRIM(?))
          AND is_active = 1
        LIMIT 1";
    if(!$st=$c->prepare($q)) return null;
    $st->bind_param("s",$code);
  }
  $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close();

  // Fallback: allow inactive
  if($deptId){
    $q="SELECT id FROM class WHERE UPPER(TRIM(code)) = UPPER(TRIM(?)) AND department_id = ? LIMIT 1";
    if(!$st=$c->prepare($q)) return null;
    $st->bind_param("si",$code,$deptId);
  }else{
    $q="SELECT id FROM class WHERE UPPER(TRIM(code)) = UPPER(TRIM(?)) LIMIT 1";
    if(!$st=$c->prepare($q)) return null;
    $st->bind_param("s",$code);
  }
  $st->execute(); $st->bind_result($id); $ok=$st->fetch(); $st->close();
  return $ok ? (int)$id : null;
}
function get_shift_id(mysqli $c,$name){
  $name = trim((string)$name); if($name==='') return null;
  $q="SELECT id FROM shift WHERE name=? LIMIT 1";
  if($st=$c->prepare($q)){ $st->bind_param("s",$name); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  $q2="SELECT id FROM shift WHERE LOWER(name)=LOWER(?) LIMIT 1";
  if($st=$c->prepare($q2)){ $st->bind_param("s",$name); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  return null;
}
function get_campus_id(mysqli $c,$name){
  $name = trim((string)$name); if($name==='') return null;
  $q="SELECT id FROM campus WHERE name=? LIMIT 1";
  if($st=$c->prepare($q)){ $st->bind_param("s",$name); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  $q2="SELECT id FROM campus WHERE LOWER(name)=LOWER(?) LIMIT 1";
  if($st=$c->prepare($q2)){ $st->bind_param("s",$name); $st->execute(); $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); }
  return null;
}

/* ============== Subject upsert (prefers course_code if column exists) ============== */
function find_or_create_subject_ext(mysqli $c,$dept_id,$course_code,$course_name,$level='Specific', &$created=false){
  $created=false;
  $course_code = trim((string)$course_code);
  $course_name = normalize_subject($course_name);
  $id = null;

  $hasCode = column_exists($c,'subject','course_code');

  if($hasCode && $course_code!==''){
    if(!$st=$c->prepare("SELECT id FROM subject WHERE department_id<=>? AND course_code=? LIMIT 1"))
      out(false,'prepare(select subject by code) failed: '.$c->error);
    $st->bind_param("is",$dept_id,$course_code);
    $st->execute(); $st->bind_result($id);
    if($st->fetch()){ $st->close();
      if($u=$c->prepare("UPDATE subject SET subject_name=?, level=? WHERE id=?")){
        $u->bind_param("ssi",$course_name,$level,$id); $u->execute(); $u->close();
      }
      return (int)$id;
    }
    $st->close();

    $ins=$c->prepare("INSERT INTO subject(subject_name,course_code,department_id,level,is_active) VALUES(?,?,?,?,1)");
    if(!$ins) out(false,'prepare(insert subject by code) failed: '.$c->error);
    $ins->bind_param("ssis",$course_name,$course_code,$dept_id,$level);
    if(!$ins->execute()) out(false,'execute(insert subject by code) failed: '.$c->error);
    $newId=(int)$ins->insert_id; $ins->close();
    $created=true; return $newId;
  }

  // Fallback: identity by name+department
  if(!$st=$c->prepare("SELECT id FROM subject WHERE subject_name=? AND (department_id<=>?) LIMIT 1"))
    out(false,'prepare(select subject by name) failed: '.$c->error);
  $st->bind_param("si",$course_name,$dept_id);
  $st->execute(); $st->bind_result($id);
  if($st->fetch()){
    $st->close();
    if($u=$c->prepare("UPDATE subject SET level=? WHERE id=?")){ $u->bind_param("si",$level,$id); $u->execute(); $u->close(); }
    return (int)$id;
  }
  $st->close();

  $ins=$c->prepare("INSERT INTO subject(subject_name,department_id,level,is_active) VALUES(?,?,?,1)");
  if(!$ins) out(false,'prepare(insert subject by name) failed: '.$c->error);
  $ins->bind_param("sis",$course_name,$dept_id,$level);
  if(!$ins->execute()) out(false,'execute(insert subject by name) failed: '.$c->error);
  $newId=(int)$ins->insert_id; $ins->close();
  $created=true; return $newId;
}

/* ============== Link class↔subject (per period) ============== */
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

/* ============== Exam event upsert (slot considers campus if column exists) ============== */
function upsert_exam_event(mysqli $c,$class_id,$subject_id,$year_id,$sem_id,$term_id,$exam_date,$shift_id,$campus_id,$duration=120){
  if(!$shift_id || !$exam_date) return 0;
  $hasCampus = column_exists($c,'exam_event','campus_id');

  if($hasCampus){
    $sel=$c->prepare("SELECT id FROM exam_event
                       WHERE class_id=? AND academic_year_id=? AND semester_id=? AND term_id=?
                         AND shift_id=? AND exam_date=? AND campus_id <=> ?
                       LIMIT 1");
    if(!$sel) out(false,'prepare(select exam_event with campus) failed: '.$c->error);
    $sel->bind_param("iiiiisi",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date,$campus_id);
  }else{
    $sel=$c->prepare("SELECT id FROM exam_event
                       WHERE class_id=? AND academic_year_id=? AND semester_id=? AND term_id=?
                         AND shift_id=? AND exam_date=?
                       LIMIT 1");
    if(!$sel) out(false,'prepare(select exam_event) failed: '.$c->error);
    $sel->bind_param("iiiiis",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date);
  }

  $sel->execute(); $sel->bind_result($eid);
  if($sel->fetch()){
    $sel->close();
    if($hasCampus){
      $u=$c->prepare("UPDATE exam_event SET subject_id=?, duration_minutes=?, campus_id=? WHERE id=?");
      if(!$u) out(false,'prepare(update exam_event) failed: '.$c->error);
      $u->bind_param("iiii",$subject_id,$duration,$campus_id,$eid);
    }else{
      $u=$c->prepare("UPDATE exam_event SET subject_id=?, duration_minutes=? WHERE id=?");
      if(!$u) out(false,'prepare(update exam_event) failed: '.$c->error);
      $u->bind_param("iii",$subject_id,$duration,$eid);
    }
    if(!$u->execute()) out(false,'execute(update exam_event) failed: '.$c->error);
    $u->close();
    return 2;
  }
  $sel->close();

  if($hasCampus){
    $ins=$c->prepare("INSERT INTO exam_event
      (class_id, academic_year_id, semester_id, term_id, shift_id, exam_date, campus_id, subject_id, duration_minutes, status)
      VALUES (?,?,?,?,?,?,?,? ,?,'draft')");
    if(!$ins) out(false,'prepare(insert exam_event) failed: '.$c->error);
    $ins->bind_param("iiiiisiii",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date,$campus_id,$subject_id,$duration);
  }else{
    $ins=$c->prepare("INSERT INTO exam_event
      (class_id, academic_year_id, semester_id, term_id, shift_id, exam_date, subject_id, duration_minutes, status)
      VALUES (?,?,?,?,?,?,?,?,'draft')");
    if(!$ins) out(false,'prepare(insert exam_event) failed: '.$c->error);
    $ins->bind_param("iiiiisii",$class_id,$year_id,$sem_id,$term_id,$shift_id,$exam_date,$subject_id,$duration);
  }
  if(!$ins->execute()) out(false,'execute(insert exam_event) failed: '.$c->error);
  $ins->close();
  return 1;
}

/* ============== Terms helper ============== */
function list_active_terms(mysqli $c,$semester_id){
  $out=[]; $st=$c->prepare("SELECT id FROM term WHERE semester_id=? AND is_active=1 ORDER BY id");
  if(!$st) return $out; $st->bind_param("i",$semester_id); $st->execute(); $res=$st->get_result();
  while($r=$res->fetch_assoc()) $out[]=(int)$r['id']; $st->close(); return $out;
}

/* ================= Controller ================== */
if ($action==='upload'){
  $year_id=(int)($_POST['academic_year_id']??0);
  $sem_id=(int)($_POST['semester_id']??0);
  $term_id=(int)($_POST['term_id']??0);          // can be 0 when all_terms=1
  $all_terms=(int)($_POST['all_terms']??0)===1;
  $duration=(int)($_POST['default_duration']??120);

  if(!$year_id||!$sem_id) out(false,'Academic Year and Semester are required.');
  if(!$all_terms && !$term_id) out(false,'Term is required (or enable All Terms).');
  if(!isset($_FILES['file'])) out(false,'File is required.');
  $f=$_FILES['file']; if($f['error']!==UPLOAD_ERR_OK) out(false,'Upload error: '.$f['error']);
  if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='xlsx') out(false,'Only .xlsx files are allowed');

  list($zip,$err)=xlsx_open_zip($f['tmp_name']); if(!$zip) out(false,$err);
  $shared=xlsx_shared_strings($zip); $sheets=xlsx_sheets($zip);
  if(!$sheets) out(false,'No sheets found in workbook');

  $terms = $all_terms ? list_active_terms($conn,$sem_id) : [$term_id];
  if(empty($terms)) out(false,'No active terms for selected semester.');

  $summary=[
    'sheets'           => [],
    'subjects_created' => 0,
    'subjects_updated' => 0,
    'links_created'    => 0,
    'events_created'   => 0,
    'events_updated'   => 0,
    'skipped_rows'     => 0,
    'lookup_failed'    => 0,
    'skipped_event_no_shift_or_campus' => 0,
    'failed_examples'  => []
  ];

  $hasCampusCol = column_exists($conn,'exam_event','campus_id');

  // Seen map to detect cross-group sharing within this upload (ignore term)
  // key: year|sem|lower(subject_name)|date -> ['cit'=>bool,'other'=>bool]
  $seen=[];

  $conn->begin_transaction();
  try {
    foreach($sheets as $sh){
      $sheetName=$sh['name'];

      list($H,$rows,$rawHeaders)=xlsx_rows_with_headers($zip,$sh['path'],$shared);
      foreach (['department','class','course_name','date'] as $k){
        if(!isset($H[$k])){
          $summary['sheets'][]=['name'=>$sheetName,'error'=>"Missing required header: $k"];
          continue 2;
        }
      }

      $stat=[
        'name'=>$sheetName,'rows'=>0,'created_subjects'=>0,'updated_subjects'=>0,'linked'=>0,
        'events'=>0,'events_updated'=>0,'skipped'=>0,'lookup_failed'=>0,'skipped_event_no_shift_or_campus'=>0
      ];

      foreach($rows as $r){
        $get=function($key) use($H,$r){ return isset($H[$key]) ? trim((string)($r[$H[$key]]??'')) : ''; };

        $deptName  = $get('department'); // optional for this logic
        $classCode = $get('class');
        $courseCode= $get('course_code');
        $courseName= normalize_subject($get('course_name'));
        $shiftName = $get('shift');
        $dateRaw   = $get('date');
        $campusName= $get('campus');

        if($classCode===''&&$courseName===''&&$dateRaw===''){ $stat['skipped']++; continue; }

        // Resolve dept via class when possible (kept for your schema integrity)
        $deptId  = null;
        $classId = null;

        if ($classCode !== '') {
          $classId = get_class_id($conn, $classCode, null);
          if ($classId) {
            if($q=$conn->prepare("SELECT department_id FROM class WHERE id=?")){
              $q->bind_param("i",$classId); $q->execute(); $q->bind_result($did);
              if($q->fetch()) $deptId = (int)$did;
              $q->close();
            }
          }
        }
        if (!$deptId) $deptId = get_department_id($conn, $deptName);
        if(!$classId && $deptId && $classCode!==''){ $classId = get_class_id($conn, $classCode, $deptId); }

        if(!$deptId || !$classId){
          $stat['lookup_failed']++; $summary['lookup_failed']++;
          if (count($summary['failed_examples']) < 10) {
            $summary['failed_examples'][] = ['sheet'=>$sheetName,'department_cell'=>$deptName,'class_cell'=>$classCode,'reason'=>(!$deptId&&!$classId?'department+class not found':(!$deptId?'department not found':'class not found'))];
          }
          continue;
        }
        if($courseName===''){ $stat['skipped']++; continue; }

        $examDate = parse_date_flex($dateRaw);
        $isCit    = is_cit_by_classcode($classCode);

        // --- decide level for this row ---
        // Make a key ignoring term
        $key="$year_id|$sem_id|".mb_strtolower($courseName)."|".($examDate??'');
        if(!isset($seen[$key])) $seen[$key]=['cit'=>false,'other'=>false];
        $seen[$key][$isCit?'cit':'other']=true;

        // Non-CIT becomes General if there is also a CIT for same subject+date (either already seen in this upload or in DB)
        $thereIsCit = $seen[$key]['cit'] || ($examDate ? exists_cit_event_db($conn,$year_id,$sem_id,$courseName,$examDate) : false);
        $level = ($isCit ? 'Specific' : ($thereIsCit ? 'General' : 'Specific'));

        // Subject upsert
        $created=false;
        $sid = find_or_create_subject_ext($conn,$deptId,$courseCode,$courseName,$level,$created);
        if($created){ $stat['created_subjects']++; $summary['subjects_created']++; }
        else        { $stat['updated_subjects']++; $summary['subjects_updated']++; }

        // Link class↔subject (for each selected term)
        foreach($terms as $tId){
          upsert_class_subject($conn,$classId,$sid,$year_id,$sem_id,$tId);
          $stat['linked']++; $summary['links_created']++;

          // Events only if we have date+shift (+campus if present)
          $shiftId  = ($shiftName!=='') ? get_shift_id($conn,$shiftName) : null;
          $campusId = ($campusName!=='') ? get_campus_id($conn,$campusName) : null;

          if(!$examDate || !$shiftId || ($hasCampusCol && !$campusId)){
            $stat['skipped_event_no_shift_or_campus']++; $summary['skipped_event_no_shift_or_campus']++; continue;
          }

          $res = upsert_exam_event($conn,$classId,$sid,$year_id,$sem_id,$tId,$examDate,$shiftId,$campusId,$duration);
          if($res===1){ $stat['events']++; $summary['events_created']++; }
          elseif($res===2){ $stat['events_updated']++; $summary['events_updated']++; }
        }

        $stat['rows']++;
      }

      $summary['skipped_rows'] += $stat['skipped'];
      $summary['sheets'][]=$stat;
    }

    /* ===== Post-pass: if a subject+date appears for BOTH CIT and non-CIT in this upload,
       force level = 'General' for the non-CIT subjects only (CIT stays 'Specific'). */
    foreach($seen as $k=>$flags){
      if(!($flags['cit'] && $flags['other'])) continue;

      // key format: year|sem|lower(subject_name)|date  (no term)
      $parts = explode('|', $k, 4);
      if (count($parts) < 4) continue;
      [$yy,$ss,$sub_lc,$dt] = [$parts[0],$parts[1],$parts[2],$parts[3]];
      if ($dt === '') continue;

      $sql = "
        UPDATE subject s
        JOIN exam_event ev ON ev.subject_id = s.id
        JOIN class c       ON c.id = ev.class_id
        SET s.level = 'General'
        WHERE LOWER(s.subject_name) = ?
          AND ev.academic_year_id = ?
          AND ev.semester_id      = ?
          AND ev.exam_date        = ?
          AND NOT (c.code REGEXP '^(CA|CNS|CM)')
      ";
      if (!$st = $conn->prepare($sql)) {
        throw new Exception('prepare(post-pass generalize) failed: '.$conn->error);
      }
      $types = "siis";
      $params = [$sub_lc, (int)$yy, (int)$ss, $dt];
      $st->bind_param($types, ...$params);
      $st->execute();
      $st->close();
    }

    $conn->commit();
  } catch(Throwable $e){
    $conn->rollback();
    $zip->close();
    out(false,'Upload failed: '.$e->getMessage());
  }

  $zip->close();
  out(true,$summary);
}

out(false,'Unknown action');
