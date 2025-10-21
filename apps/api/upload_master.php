<?php
// api/upload_master.php — .xlsx import using chosen period & campus/shift
include '../config/conn.php';
include '../config/helpers.php';

header('Content-Type: application/json');
@ini_set('max_execution_time','300');
@ini_set('memory_limit','1024M');

if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(["status"=>false,"data"=>"POST required"]); exit; }
if (!isset($_FILES['excel']))          { echo json_encode(["status"=>false,"data"=>"Missing file 'excel'"]); exit; }

$year_id    = (int)($_POST['year_id'] ?? 0);
$semester_id= (int)($_POST['semester_id'] ?? 0);
$term_id    = (int)($_POST['term_id'] ?? 0);
$campus_id  = (int)($_POST['campus_id'] ?? 0);
$shift_id   = (int)($_POST['shift_id'] ?? 0);

if (!$year_id || !$semester_id || !$term_id || !$campus_id || !$shift_id) {
  echo json_encode(["status"=>false,"data"=>"Please select Academic Year, Semester, Term, Campus, and Shift"]); exit;
}

$tmp = $_FILES['excel']['tmp_name'];
$name= $_FILES['excel']['name'];
if (!is_uploaded_file($tmp)) { echo json_encode(["status"=>false,"data"=>"Upload failed"]); exit; }
if (!preg_match('/\.xlsx$/i',$name)) { echo json_encode(["status"=>false,"data"=>"Please upload .xlsx"]); exit; }

/* -------- Minimal XLSX reader (no namespaces) -------- */
if (!class_exists('XlsxReaderLite')) {
  class XlsxReaderLite {
    private ZipArchive $zip;
    private array $sharedStrings=[];
    private array $sheetNameToIndex=[];
    public function __construct(private string $xlsxPath) {}
    public function open(): bool {
      $this->zip=new ZipArchive();
      if ($this->zip->open($this->xlsxPath)!==true) return false;
      $this->loadWorkbook(); $this->loadSharedStrings(); return true;
    }
    public function close(): void { $this->zip->close(); }
    private function loadWorkbook(): void {
      $xml=$this->zip->getFromName('xl/workbook.xml'); if($xml===false) return;
      $sxe=simplexml_load_string($xml); if(!$sxe || !isset($sxe->sheets->sheet)) return;
      $i=1; foreach($sxe->sheets->sheet as $sheet){ $attr=$sheet->attributes(); $this->sheetNameToIndex[(string)$attr['name']]=$i; $i++; }
    }
    private function loadSharedStrings(): void {
      $xml=$this->zip->getFromName('xl/sharedStrings.xml'); if($xml===false){$this->sharedStrings=[];return;}
      $sxe=simplexml_load_string($xml); if(!$sxe || !isset($sxe->si)){ $this->sharedStrings=[]; return; }
      $out=[]; foreach($sxe->si as $si){
        $text=''; if(isset($si->t)) $text=(string)$si->t; elseif(isset($si->r)) foreach($si->r as $r){ if(isset($r->t)) $text.=(string)$r->t; }
        $out[]=$text;
      } $this->sharedStrings=$out;
    }
    private static function colIdx(string $ref): int {
      if(!preg_match('/([A-Z]+)/',strtoupper($ref),$m)) return 0; $letters=$m[1]; $n=0;
      for($i=0;$i<strlen($letters);$i++){ $n=$n*26+(ord($letters[$i])-64); } return $n-1;
    }
    public function readSheetByName(string $name): array {
      if(!isset($this->sheetNameToIndex[$name])) return [];
      $idx=$this->sheetNameToIndex[$name]; $xml=$this->zip->getFromName("xl/worksheets/sheet{$idx}.xml"); if($xml===false)return [];
      $sxe=simplexml_load_string($xml); $rows=[]; if(!isset($sxe->sheetData)) return $rows;
      foreach($sxe->sheetData->row as $row){ $rowArr=[];
        foreach($row->c as $c){ $ref=(string)$c['r']; $t=(string)$c['t']; $v=$c->v ?? null; $i=self::colIdx($ref); $val='';
          if($t==='s'){ $si=(int)$v; $val=$this->sharedStrings[$si]??''; }
          elseif($t==='b'){ $val=((string)$v==='1')?'TRUE':'FALSE'; }
          elseif($t==='inlineStr'){ $val=isset($c->is->t)?(string)$c->is->t:''; }
          else { $val=isset($v)?(string)$v:''; }
          $rowArr[$i]=trim($val);
        }
        if(!empty($rowArr)){ ksort($rowArr); $rows[]=array_values($rowArr); }
      } return $rows;
    }
  }
}

/* -------- Read sheets -------- */
$xr = new XlsxReaderLite($tmp);
if (!$xr->open()) { echo json_encode(["status"=>false,"data"=>"Cannot open .xlsx (ZipArchive)"]); exit; }

/* Optional: DepartmentRules */
$rules = $xr->readSheetByName('DepartmentRules');
if ($rules && count($rules)>1) {
  $head = array_map('trim',$rules[0]); $Hmap=array_flip($head);
  foreach(array_slice($rules,1) as $r){
    $prefix = isset($Hmap['Prefix'], $r[$Hmap['Prefix']]) ? strtoupper(trim($r[$Hmap['Prefix']])) : '';
    $dept   = isset($Hmap['Department'], $r[$Hmap['Department']]) ? trim($r[$Hmap['Department']]) : '';
    if($prefix!=='' && $dept!==''){
      $sql="INSERT INTO department (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
      $st=$conn->prepare($sql); $st->bind_param("s",$dept); $st->execute(); $depId=$conn->insert_id; $st->close();
      $prio=10; $sql="INSERT INTO department_rule (prefix,department_id,priority) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE department_id=VALUES(department_id), priority=VALUES(priority)";
      $st=$conn->prepare($sql); $st->bind_param("sii",$prefix,$depId,$prio); $st->execute(); $st->close();
    }
  }
}

/* Required: MasterUpload (reduced headers) */
$rows = $xr->readSheetByName('MasterUpload');
$xr->close();
if (!$rows || count($rows)<2) { echo json_encode(["status"=>false,"data"=>"Sheet 'MasterUpload' not found or empty"]); exit; }

$header = array_map('trim',$rows[0]); $H = array_flip($header);
$required = ['HallName','HallCapacity','ClassCode','StudentCount'];
$missing = array_diff($required, array_keys($H));
if (!empty($missing)) { echo json_encode(["status"=>false,"data"=>"Missing columns: ".implode(', ',$missing)]); exit; }

if (!function_exists('xlsxCellH')) {
  function xlsxCellH(array $row, string $name, array $H): string {
    return (isset($H[$name],$row[$H[$name]])) ? trim((string)$row[$H[$name]]) : '';
  }
}

$imported=0; $errors=[];
for($i=1;$i<count($rows);$i++){
  $r=$rows[$i];
  $HallName     = xlsxCellH($r,'HallName',$H);
  $HallCapacity = (int) xlsxCellH($r,'HallCapacity',$H);
  $ClassCode    = xlsxCellH($r,'ClassCode',$H);
  $StudentCount = (int) xlsxCellH($r,'StudentCount',$H);

  if ($HallName==='' && $ClassCode==='') continue;
  $miss=[]; if($HallName==='')$miss[]='HallName'; if($ClassCode==='')$miss[]='ClassCode';
  if(!empty($miss)){ $errors[]="Row ".($i+1).": missing ".implode(', ',$miss); continue; }

  try {
    // hall under the chosen campus + availability in chosen shift
    $hallId = get_or_create_hall($conn, $campus_id, $HallName, max(0,$HallCapacity), 1);
    set_hall_shift_available($conn, $hallId, $shift_id, 1);

    // class & department
    $prefix  = extract_prefix($ClassCode);
    $deptId  = get_or_create_department_by_prefix($conn, $prefix);
    $classId = get_or_create_class($conn, $ClassCode, $deptId);

    // set class size for the chosen academic period
    upsert_class_size($conn, $classId, $year_id, $semester_id, $term_id, max(0,$StudentCount));

    $imported++;
  } catch(Throwable $e){
    $errors[] = "Row ".($i+1).": ".$e->getMessage(); continue;
  }
}

$out=["status"=>true,"data"=>"Imported rows: $imported"];
if(!empty($errors)) $out["warnings"]=$errors;
echo json_encode($out);
