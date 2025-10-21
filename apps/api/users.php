<?php
session_start();
include '../config/conn.php';
header('Content-Type: application/json');


function readAllUsers($conn){
$q = "SELECT u.id, u.full_name, u.username, u.role, u.is_active, u.created_at
FROM users u
ORDER BY u.role DESC, u.full_name";
$res = $conn->query($q);
$rows = [];
if($res){ while($r = $res->fetch_assoc()) $rows[] = $r; echo json_encode(["status"=>true, "data"=>$rows]); }
else { echo json_encode(["status"=>false, "data"=>$conn->error]); }
}


// function readLookups($conn){
// $fac=[]; $dep=[];
// $rf = $conn->query("SELECT id,name FROM faculties ORDER BY name");
// if($rf){ while($r=$rf->fetch_assoc()) $fac[]=$r; }
// $rd = $conn->query("SELECT id,name,faculty_id FROM departments ORDER BY name");
// if($rd){ while($r=$rd->fetch_assoc()) $dep[]=$r; }
// echo json_encode(["status"=>true, "data"=>["faculties"=>$fac, "departments"=>$dep]]);
// }


function registerUser($conn){
extract($_POST); // full_name, username, role, faculty_id, department_id, password
$full_name = $conn->real_escape_string($full_name);
$username = $conn->real_escape_string($username);
$role = strtoupper($conn->real_escape_string($role));
if(strlen($password) < 6){ echo json_encode(["status"=>false, "data"=>"Password too short"]); return; }
$passhash = password_hash($password, PASSWORD_BCRYPT);
$q = "INSERT INTO users(full_name,username,password_hash,role,is_active) VALUES
('$full_name','$username','$passhash','$role',1)";
$ok = $conn->query($q);
echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"User created":"$conn->error"]);
}


function updateUser($conn){
extract($_POST); // id, full_name, role,
$id = (int)$id; $full_name=$conn->real_escape_string($full_name);
$role = strtoupper($conn->real_escape_string($role));
$is_active = isset($is_active)? (int)$is_active : 1;
$q = "UPDATE users SET full_name='$full_name', role='$role', is_active=$is_active WHERE id=$id";
$ok = $conn->query($q);
echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"Updated":"$conn->error"]);
}


function toggleActive($conn){
extract($_POST); // id, is_active
$id=(int)$id; $is_active=(int)$is_active;
$ok = $conn->query("UPDATE users SET is_active=$is_active WHERE id=$id");
echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"Toggled":"$conn->error"]);
}


function resetPassword($conn){
extract($_POST); // id, new_password
if(strlen($new_password)<6){ echo json_encode(["status"=>false, "data"=>"Password too short"]); return; }
$id=(int)$id; $passhash = password_hash($new_password, PASSWORD_BCRYPT);
$ok=$conn->query("UPDATE users SET password_hash='$passhash' WHERE id=$id");
echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"Password reset":"$conn->error"]);
}


if(isset($_POST['action'])){
$action = $_POST['action'];
if(function_exists($action)) $action($conn); else echo json_encode(["status"=>false, "data"=>"Unknown action"]);
}else{
echo json_encode(["status"=>false, "data"=>"Action is Required"]);
}