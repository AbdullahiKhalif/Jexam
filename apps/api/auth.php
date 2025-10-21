<?php
session_start();
include '../config/conn.php';
header('Content-Type: application/json');


function loginUser($conn){
extract($_POST);
if(empty($username) || empty($password)){
echo json_encode(["status"=>false, "data"=>"Username and password are required"]); return;
}
$em = $conn->real_escape_string($username);
$q = "SELECT id, full_name, username, role, password_hash, is_active
FROM users WHERE username='$em' LIMIT 1";
$res = $conn->query($q);


if($res && $res->num_rows===1){
$u = $res->fetch_assoc();
if((int)$u['is_active'] !== 1){ echo json_encode(["status"=>false, "data"=>"Account is disabled"]); return; }
if(password_verify($password, $u['password_hash'])){
$_SESSION['user_id'] = (int)$u['id'];
$_SESSION['name'] = $u['full_name'];
$_SESSION['username'] = $u['username'];
$_SESSION['role'] = $u['role'];
echo json_encode(["status"=>true, "data"=>[
"id"=>$u['id'], "name"=>$u['full_name'], "username"=>$u['username'], "role"=>$u['role']
]]);
} else {
echo json_encode(["status"=>false, "data"=>"Invalid credentials"]);
}
} else {
echo json_encode(["status"=>false, "data"=>"Invalid credentials"]);
}
}


function logoutUser($conn){ session_unset(); session_destroy(); echo json_encode(["status"=>true, "data"=>"Logged out"]); }
function whoAmI($conn){
if(isset($_SESSION['user_id'])){
echo json_encode(["status"=>true, "data"=>[
"id"=>$_SESSION['user_id'],
"name"=>$_SESSION['name'] ?? '',
"username"=>$_SESSION['username'] ?? '',
"role"=>$_SESSION['role'] ?? '',
]]);
} else echo json_encode(["status"=>false, "data"=>"Not logged in"]);
}


if(isset($_POST['action'])){ $a=$_POST['action']; if(function_exists($a)) $a($conn); else echo json_encode(["status"=>false, "data"=>"Unknown action"]);}
else echo json_encode(["status"=>false, "data"=>"Action is Required"]);