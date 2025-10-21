<?php
// api/hall.php
header('Content-Type: application/json');
include '../config/conn.php';
// NOTE: This file uses raw echo json_encode([...]) like your original.
// For consistency with class.php you can also include helpers and use jsonOut() if you prefer.

function readAllHall($conn){
    $sql = "SELECT h.id, c.name AS campus_name, h.campus_id, h.name, h.capacity, h.is_active
            FROM hall h JOIN campus c ON c.id=h.campus_id
            ORDER BY h.id DESC";
    $res = $conn->query($sql);
    $data = [];
    if($res){
        while($r = $res->fetch_assoc()){ $data[]=$r; }
        echo json_encode(["status"=>true,"data"=>$data]); return;
    }
    echo json_encode(["status"=>false,"data"=>$conn->error]);
}

function registerHall($conn){
    $campus_id = intval($_POST['campus_id']??0);
    $name = $_POST['name']??'';
    $cap  = intval($_POST['capacity']??0);
    $stmt = $conn->prepare("INSERT INTO hall(campus_id,name,capacity,is_active) VALUES(?,?,?,1)");
    $stmt->bind_param("isi",$campus_id,$name,$cap);
    if($stmt->execute()){ echo json_encode(["status"=>true,"data"=>"Hall created ✅"]); return; }
    echo json_encode(["status"=>false,"data"=>$conn->error]);
}

function updateHall($conn){
    $id = intval($_POST['id']??0);
    $campus_id = intval($_POST['campus_id']??0);
    $name = $_POST['name']??'';
    $cap  = intval($_POST['capacity']??0);
    $active = isset($_POST['is_active'])? intval($_POST['is_active']):1;
    $stmt = $conn->prepare("UPDATE hall SET campus_id=?, name=?, capacity=?, is_active=? WHERE id=?");
    $stmt->bind_param("isiii",$campus_id,$name,$cap,$active,$id);
    if($stmt->execute()){ echo json_encode(["status"=>true,"data"=>"Hall updated ✔"]); return; }
    echo json_encode(["status"=>false,"data"=>$conn->error]);
}

function deleteHall($conn){
    $id = intval($_POST['id']??0);
    $stmt = $conn->prepare("DELETE FROM hall WHERE id=?");
    $stmt->bind_param("i",$id);
    if($stmt->execute()){ echo json_encode(["status"=>true,"data"=>"Hall deleted ✅"]); return; }
    echo json_encode(["status"=>false,"data"=>$conn->error]);
}

/**
 * Legacy (non-period) availability: create/overwrite a row hall_id + shift_id
 */
function setHallAvailability($conn){
    $hall_id = intval($_POST['hall_id']??0);
    $shift_id = intval($_POST['shift_id']??0);
    $is_available = intval($_POST['is_available']??1);

    // upsert (try update; if 0 affected, insert)
    $stmt = $conn->prepare("UPDATE hall_shift_availability SET is_available=? WHERE hall_id=? AND shift_id=? AND academic_year_id IS NULL AND semester_id IS NULL AND term_id IS NULL");
    $stmt->bind_param("iii",$is_available,$hall_id,$shift_id);
    $stmt->execute();
    if($stmt->affected_rows === 0){
        $stmt = $conn->prepare("INSERT INTO hall_shift_availability(hall_id,shift_id,is_available,academic_year_id,semester_id,term_id) VALUES(?,?,?,NULL,NULL,NULL)");
        $stmt->bind_param("iii",$hall_id,$shift_id,$is_available);
        if(!$stmt->execute()){ echo json_encode(["status"=>false,"data"=>$conn->error]); return; }
    }
    echo json_encode(["status"=>true,"data"=>"Availability saved ✅"]);
}

/* ================= NEW: Period-aware endpoints ================= */

/**
 * List halls in a campus and report availability for a specific
 * (year, semester, term, shift) combination.
 * Expects: campus_id, shift_id, academic_year_id?, semester_id?, term_id?
 * – If the period IDs are empty/omitted, matches the "global" rows (NULLs).
 */
function listAvailability($conn){
    $campus_id = intval($_POST['campus_id'] ?? 0);
    $shift_id  = intval($_POST['shift_id'] ?? 0);

    // period filters can be null
    $year_id = $_POST['academic_year_id'] ?? null;
    $sem_id  = $_POST['semester_id'] ?? null;
    $term_id = $_POST['term_id'] ?? null;

    if (!$campus_id || !$shift_id) {
        echo json_encode(["status"=>false,"data"=>"campus_id and shift_id required"]); return;
    }

    // Using NULL-safe equality `<=>` so passing NULL matches NULL rows
    $sql = "SELECT h.id, h.name, h.capacity, h.is_active,
                   COALESCE(a.is_available, 0) AS is_available
            FROM hall h
            LEFT JOIN hall_shift_availability a
              ON a.hall_id = h.id
             AND a.shift_id = ?
             AND (a.academic_year_id <=> ?)
             AND (a.semester_id      <=> ?)
             AND (a.term_id          <=> ?)
            WHERE h.campus_id = ?
            ORDER BY h.name";

    $stmt = $conn->prepare($sql);

    // Normalize '' → NULL for NULL-safe comparison
    $y = ($year_id === '' ? null : $year_id);
    $s = ($sem_id  === '' ? null : $sem_id);
    $t = ($term_id === '' ? null : $term_id);

    // bind: i i i i i (NULLs are allowed)
    $stmt->bind_param("iiiii", $shift_id, $y, $s, $t, $campus_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if(!$res){ echo json_encode(["status"=>false,"data"=>$conn->error]); return; }
    $out = [];
    while($row = $res->fetch_assoc()) $out[] = $row;
    echo json_encode(["status"=>true,"data"=>$out]);
}

/**
 * Upsert a hall_shift_availability row for the exact (hall, shift, year, sem, term).
 * Expects: hall_id, shift_id, is_available, academic_year_id?, semester_id?, term_id?
 */
function setAvailabilityForPeriod($conn){
    $hall_id = intval($_POST['hall_id'] ?? 0);
    $shift_id= intval($_POST['shift_id'] ?? 0);
    $is_available = isset($_POST['is_available']) ? intval($_POST['is_available']) : 0;

    $year_id = $_POST['academic_year_id'] ?? null;
    $sem_id  = $_POST['semester_id'] ?? null;
    $term_id = $_POST['term_id'] ?? null;

    if(!$hall_id || !$shift_id){
        echo json_encode(["status"=>false,"data"=>"hall_id and shift_id required"]); return;
    }

    $y = ($year_id === '' ? null : $year_id);
    $s = ($sem_id  === '' ? null : $sem_id);
    $t = ($term_id === '' ? null : $term_id);

    // Try UPDATE first
    $upd = $conn->prepare("UPDATE hall_shift_availability
                              SET is_available=?
                            WHERE hall_id=? AND shift_id=?
                              AND (academic_year_id <=> ?)
                              AND (semester_id      <=> ?)
                              AND (term_id          <=> ?)");
    $upd->bind_param("iiiiii", $is_available, $hall_id, $shift_id, $y, $s, $t);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        // INSERT when not exists (unique key on hall_id+shift_id+period should prevent dupes)
        $ins = $conn->prepare("INSERT INTO hall_shift_availability
                                (hall_id, shift_id, is_available, academic_year_id, semester_id, term_id)
                               VALUES (?,?,?,?,?,?)");
        $ins->bind_param("iiiiii", $hall_id, $shift_id, $is_available, $y, $s, $t);
        if (!$ins->execute()) {
            echo json_encode(["status"=>false,"data"=>$conn->error]); return;
        }
    }

    echo json_encode(["status"=>true,"data"=>"Saved ✅"]);
}

/* ================= DISPATCH ================= */

if(isset($_POST['action'])){
    $action = $_POST['action'];
    if(function_exists($action)) $action($conn);
    else echo json_encode(["status"=>false,"data"=>"Unknown action"]);
}else{
    echo json_encode(["status"=>false,"data"=>"Action is required"]);
}
