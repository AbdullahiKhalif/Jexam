<?php
// apps/api/allocation_upload.php
header('Content-Type: application/json');

@ini_set('max_execution_time','0');
@ini_set('memory_limit','-1');

@include_once __DIR__ . '/../config/conn.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
  echo json_encode(['status'=>false,'data'=>'DB not initialized']); exit;
}
function out($ok,$data=null){ echo json_encode(['status'=>$ok,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }

$action = $_POST['action'] ?? '';
if ($action !== 'uploadExcel') out(false,'Invalid action');

/* Required selectors */
$year_id   = (int)($_POST['academic_year_id'] ?? 0);
$sem_id    = (int)($_POST['semester_id'] ?? 0);
$term_id   = (int)($_POST['term_id'] ?? 0);
$campus_id = (int)($_POST['campus_id'] ?? 0);
$shift_id  = (int)($_POST['shift_id'] ?? 0);
if (!$year_id || !$sem_id || !$term_id || !$campus_id || !$shift_id) out(false,'Missing period/campus/shift');

/* File */
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) out(false,'File upload failed');
$xlsxPath = $_FILES['file']['tmp_name'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') out(false,'Only .xlsx files are accepted');

/* ---------------- minimal XLSX reader (first sheet, streaming) ---------------- */
function xlsx_paths_first_sheet($xlsx){
  $z=new ZipArchive(); if($z->open($xlsx)!==true) throw new Exception('Cannot open XLSX zip');
  $wb=$z->getFromName('xl/workbook.xml'); if($wb===false) throw new Exception('xl/workbook.xml missing');
  $wbx=new SimpleXMLElement($wb); $wbx->registerXPathNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');
  $s=$wbx->sheets->sheet[0]??null; if(!$s) throw new Exception('No sheets in workbook');
  $rid=(string)$s->attributes('r',true)['id'];
  $rels=$z->getFromName('xl/_rels/workbook.xml.rels'); if($rels===false) throw new Exception('workbook rels missing');
  $rx=new SimpleXMLElement($rels); $map=[]; foreach($rx->Relationship as $r){ $map[(string)$r['Id']]=(string)$r['Target']; }
  if(!isset($map[$rid])) throw new Exception('Worksheet relationship missing');
  $sheetPath='xl/'.ltrim($map[$rid],'/'); $sharedPath='xl/sharedStrings.xml'; $z->close();
  return [$sheetPath,$sharedPath];
}
function xlsx_reader_from_zip($xlsx,$inner){
  $xr=new XMLReader(); $uri="zip://".str_replace('\\','/',$xlsx)."#".$inner;
  if(@$xr->open($uri)) return $xr;
  $z=new ZipArchive(); if($z->open($xlsx)!==true) throw new Exception('Cannot open XLSX zip (fallback)');
  $xml=$z->getFromName($inner); $z->close(); if($xml===false) throw new Exception("Entry not found: $inner");
  if(!$xr->XML($xml,null,LIBXML_NONET|LIBXML_COMPACT)) throw new Exception("XMLReader failed for $inner");
  return $xr;
}
function xlsx_load_shared_strings($xlsx,$sharedPath){
  $z=new ZipArchive(); if($z->open($xlsx)!==true) throw new Exception('Cannot open XLSX zip (sharedStrings)');
  $xml=$z->getFromName($sharedPath); $z->close();
  if ($xml===false) return [];
  $out=[]; $xr=new XMLReader();
  if(!$xr->XML($xml,null,LIBXML_NONET|LIBXML_COMPACT)) return [];
  while($xr->read()){
    if($xr->nodeType===XMLReader::ELEMENT && $xr->name==='si'){
      $si=new SimpleXMLElement($xr->readOuterXML()); $txt=[];
      foreach($si->t as $t) $txt[]=(string)$t;
      if(empty($txt) && isset($si->r)) foreach($si->r as $r) if(isset($r->t)) $txt[]=(string)$r->t;
      $out[]=implode('',$txt);
    }
  } $xr->close(); return $out;
}
function col_to_idx($a1){ $c=preg_replace('/[^A-Z]/','',strtoupper($a1)); $n=0; for($i=0;$i<strlen($c);$i++) $n=$n*26+(ord($c[$i])-64); return $n-1; }
function xlsx_stream_rows($xlsx){
  list($sheetPath,$sharedPath)=xlsx_paths_first_sheet($xlsx);
  $shared=xlsx_load_shared_strings($xlsx,$sharedPath);
  $xr=xlsx_reader_from_zip($xlsx,$sheetPath);
  while($xr->read()){
    if($xr->nodeType===XMLReader::ELEMENT && $xr->name==='row'){
      $rx=new SimpleXMLElement($xr->readOuterXML()); $row=[]; $max=-1;
      foreach($rx->c as $c){
        $ref=(string)$c['r']; $t=(string)$c['t']; $v=isset($c->v)?(string)$c->v:''; $col=col_to_idx($ref); if($col>$max)$max=$col;
        if($t==='s'){ $val=$shared[(int)$v]??''; }
        elseif($t==='inlineStr' && isset($c->is->t)){ $val=(string)$c->is->t; }
        else { $val=$v; }
        $val=trim((string)$val);
        if($val!=='' && is_numeric($val)){ $ival=(int)$val; $val=((string)$ival===(string)$val)?$ival:(float)$val; }
        $row[$col]=$val;
      }
      for($i=0;$i<=$max;$i++) if(!array_key_exists($i,$row)) $row[$i]='';
      ksort($row); yield array_values($row);
    }
  } $xr->close();
}

/* ---------------- prepared statements ---------------- */
$selHall=$conn->prepare("SELECT id,capacity FROM hall WHERE campus_id=? AND name=?");
$insHall=$conn->prepare("INSERT INTO hall(campus_id,name,capacity,is_active) VALUES(?,?,?,1)");
$updHall=$conn->prepare("UPDATE hall SET capacity=? WHERE id=?");

$ruleSel=$conn->prepare("SELECT department_id FROM department_rule WHERE prefix=? ORDER BY priority ASC LIMIT 1");

$selClass=$conn->prepare("SELECT id, academic_year_group, department_id FROM `class` WHERE code=?");
$updClass=$conn->prepare("UPDATE `class` SET academic_year_group=?, department_id=?, level=? WHERE id=?");
$insClass=$conn->prepare("INSERT INTO `class`(code, academic_year_group, department_id, level, is_active) VALUES (?,?,?,?,1)");

$insSize=$conn->prepare(
  "INSERT INTO class_size (class_id, academic_year_id, semester_id, term_id, student_count)
   VALUES (?,?,?,?,?)
   ON DUPLICATE KEY UPDATE student_count=VALUES(student_count)"
);
$insAlloc=$conn->prepare(
  "INSERT INTO class_hall_allocation (class_id, hall_id, academic_year_id, semester_id, term_id, shift_id, allocated_students)
   VALUES (?,?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE allocated_students=VALUES(allocated_students)"
);
/* FIXED: 6 placeholders & 6 params (is_available is a placeholder now) */
$insAvail=$conn->prepare(
  "INSERT INTO hall_shift_availability (hall_id, shift_id, academic_year_id, semester_id, term_id, is_available)
   VALUES (?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE is_available=VALUES(is_available)"
);

/* ---------------- parse & register ---------------- */
$headerFound=false; $capsFound=false;
$idxClass=null; $idxTotal=null; $hallNames=[]; $hallCols=[]; $capacities=[];
$hallIdByName=[]; $warnings=[]; $WARN_LIMIT=200;
$created=0; $updated=0; $sizes=0; $allocs=0;

$conn->begin_transaction();
try {

  /* ============================================================
     PRE-CLEAR: Remove any existing allocations for these filters
     (campus + year + sem + term + shift) BEFORE importing new.
     ============================================================ */
  // Delete allocations tied to halls of the campus for this period+shift
  if (!$delAlloc = $conn->prepare(
    "DELETE a FROM class_hall_allocation a
     INNER JOIN hall h ON h.id = a.hall_id
     WHERE a.academic_year_id = ?
       AND a.semester_id      = ?
       AND a.term_id          = ?
       AND a.shift_id         = ?
       AND h.campus_id        = ?"
  )) throw new Exception('prepare(delAlloc) failed: '.$conn->error);
  $delAlloc->bind_param("iiiii", $year_id, $sem_id, $term_id, $shift_id, $campus_id);
  $delAlloc->execute();
  $delAlloc->close();

  // Also remove hall availability rows for the same scope (optional but consistent)
  if (!$delAvail = $conn->prepare(
    "DELETE ha FROM hall_shift_availability ha
     INNER JOIN hall h ON h.id = ha.hall_id
     WHERE ha.academic_year_id = ?
       AND ha.semester_id      = ?
       AND ha.term_id          = ?
       AND ha.shift_id         = ?
       AND h.campus_id         = ?"
  )) throw new Exception('prepare(delAvail) failed: '.$conn->error);
  $delAvail->bind_param("iiiii", $year_id, $sem_id, $term_id, $shift_id, $campus_id);
  $delAvail->execute();
  $delAvail->close();
  /* ======================= end pre-clear ======================= */

  foreach (xlsx_stream_rows($xlsxPath) as $row) {
    // trim tail empties
    for($i=count($row)-1; $i>=0 && trim((string)$row[$i])===''; $i--) unset($row[$i]);
    $row=array_values($row);
    if (trim(implode('', array_map('strval',$row)))==='') continue;

    /* ---- header: find "Class" and "# of Std." anywhere in row ---- */
    if (!$headerFound) {
      $lower = array_map(fn($x)=>strtolower(trim((string)$x)), $row);
      $candClass = array_search('class', $lower, true);
      $candTotal = null;
      if ($candClass !== false) {
        for ($c=$candClass+1; $c<count($lower); $c++) {
          if ($lower[$c]==='# of std.' || $lower[$c]==='# of std' || strpos($lower[$c],'students')!==false || strpos($lower[$c],'std')!==false) {
            $candTotal = $c; break;
          }
        }
      }
      if ($candClass !== false && $candTotal !== null) {
        $idxClass = $candClass; $idxTotal = $candTotal;
        // halls start right after total and are non-empty on the header row
        for ($c=$idxTotal+1; $c<count($row); $c++) { $nm=trim((string)$row[$c]); if($nm!==''){ $hallNames[]=$nm; $hallCols[]=$c; } }
        if (!$hallNames) throw new Exception('No hall columns found in header');
        $headerFound=true;
      }
      continue;
    }

    /* ---- capacities row: look for "Size" (prefer same column as Class) ---- */
    if (!$capsFound) {
      $isSizeHere = strtolower(trim((string)($row[$idxClass] ?? ''))) === 'size';
      if (!$isSizeHere) {
        $isSizeHere = in_array('size', array_map(fn($x)=>strtolower(trim((string)$x)), $row), true);
      }
      if ($isSizeHere) {
        foreach ($hallCols as $k=>$c) $capacities[$k] = isset($row[$c]) ? (int)$row[$c] : 0;

        // upsert halls (name + capacity) for this campus
        foreach ($hallNames as $k=>$nm) {
          $cap = (int)($capacities[$k] ?? 0);
          $selHall->bind_param("is",$campus_id,$nm); $selHall->execute();
          $res=$selHall->get_result();
          if ($h=$res->fetch_assoc()) {
            $hid=(int)$h['id']; $hallIdByName[$nm]=$hid;
            if ($cap>0 && $cap!=(int)$h['capacity']) { $updHall->bind_param("ii",$cap,$hid); $updHall->execute(); }
          } else {
            $insHall->bind_param("isi",$campus_id,$nm,$cap); $insHall->execute();
            $hallIdByName[$nm]=(int)$insHall->insert_id;
          }
        }
        $capsFound=true;
      }
      continue;
    }

    /* ---- data rows ---- */
    $code = trim((string)($row[$idxClass] ?? ''));
    if ($code==='' || !preg_match('/^[A-Za-z]{2,}\d+/', $code)) continue; // skip totals/footer rows

    // Declared total; if missing/0, sum hall splits
    $declared = isset($row[$idxTotal]) ? (int)$row[$idxTotal] : 0;
    $allocPairs=[]; $sum=0;
    foreach ($hallCols as $k=>$c) {
      $n = isset($row[$c]) ? (int)$row[$c] : 0;
      if ($n>0) { $allocPairs[] = [$hallNames[$k], $n]; $sum += $n; }
    }
    $total = $declared > 0 ? $declared : $sum;
    if ($declared>0 && $sum!==$declared && count($warnings)<$WARN_LIMIT) $warnings[]="$code (total=$declared, allocated=$sum)";

    // class → id (create/update); department by prefix; cohort from first two digits after letters
    $selClass->bind_param("s",$code); $selClass->execute();
    $cRes=$selClass->get_result();
    if ($cRow=$cRes->fetch_assoc()) {
      $class_id=(int)$cRow['id'];
      $dept_id=(int)$cRow['department_id'];
      if ($dept_id===0 && preg_match('/^([A-Za-z]+)/',$code,$m)) {
        $pref=strtoupper($m[1]); $ruleSel->bind_param("s",$pref); $ruleSel->execute();
        if ($rp=$ruleSel->get_result()->fetch_assoc()) $dept_id=(int)$rp['department_id'];
      }
      $cohort=(int)$cRow['academic_year_group'];
      if ($cohort===0 && preg_match('/^[A-Za-z]+(\d{2})/',$code,$m2)) $cohort=(int)$m2[1];
      $level=$dept_id?'Specific':'General';
      if ($cohort !== (int)$cRow['academic_year_group'] || $dept_id !== (int)$cRow['department_id']) {
        $updClass->bind_param("iisi",$cohort,$dept_id,$level,$class_id); $updClass->execute(); $updated++;
      }
    } else {
      $dept_id=null;
      if (preg_match('/^([A-Za-z]+)/',$code,$m)) { $pref=strtoupper($m[1]); $ruleSel->bind_param("s",$pref); $ruleSel->execute(); if($rp=$ruleSel->get_result()->fetch_assoc()) $dept_id=(int)$rp['department_id']; }
      $cohort=null; if (preg_match('/^[A-Za-z]+(\d{2})/',$code,$m2)) $cohort=(int)$m2[1];
      $level=$dept_id?'Specific':'General';

      /* FIXED: bind types (code:string, cohort:int, dept:int, level:string) */
      $insClass->bind_param("siis",$code,$cohort,$dept_id,$level);
      $insClass->execute(); $class_id=(int)$insClass->insert_id; $created++;
    }

    // class_size upsert
    $insSize->bind_param("iiiii",$class_id,$year_id,$sem_id,$term_id,$total); $insSize->execute(); $sizes++;

    // per-hall allocations + availability upsert for this period+shift
    $one=1;
    foreach($allocPairs as [$hallName,$cnt]){
      if(!isset($hallIdByName[$hallName])) throw new Exception("Unknown hall '$hallName'");
      $hid=$hallIdByName[$hallName];

      /* 6 params for 6 placeholders */
      $insAvail->bind_param("iiiiii",$hid,$shift_id,$year_id,$sem_id,$term_id,$one); $insAvail->execute();

      $insAlloc->bind_param("iiiiiii",$class_id,$hid,$year_id,$sem_id,$term_id,$shift_id,$cnt); $insAlloc->execute(); $allocs++;
    }
  }

  if(!$headerFound) throw new Exception('Header row not found (need "Class" and "# of Std." on the same row)');
  if(!$capsFound)   throw new Exception('Capacities row not found (look for "Size")');

  $conn->commit();
  out(true, [
    'halls'                => $hallNames,
    'created_classes'      => $created,
    'updated_classes'      => $updated,
    'upserted_sizes'       => $sizes,
    'upserted_allocations' => $allocs,
    'warnings'             => $warnings
  ]);

} catch (Throwable $e){
  $conn->rollback();
  out(false, $e->getMessage());
}
