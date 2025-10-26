<?php
session_start();
include '../config/conn.php';
header('Content-Type: application/json');

/* small helper for consistent responses */
function out($ok, $data) {
  echo json_encode(["status"=>$ok?true:false, "data"=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

function readAllUsers($conn){
  $q = "SELECT u.id, u.full_name, u.username, u.role, u.is_active, u.created_at
        FROM users u
        ORDER BY u.role DESC, u.full_name";
  $res = $conn->query($q);
  $rows = [];
  if($res){
    while($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(["status"=>true, "data"=>$rows]);
  } else {
    echo json_encode(["status"=>false, "data"=>$conn->error]);
  }
}


function registerUser($conn){
  extract($_POST); // full_name, username, role, faculty_id, department_id, password
  $full_name = $conn->real_escape_string($full_name);
  $username  = $conn->real_escape_string($username);
  $role      = strtoupper($conn->real_escape_string($role));
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

/* ========= Hardened (same behavior & params): admin resets someone else ========= */
function resetPassword($conn){
  // expects: id, new_password (keeps min length 6 to avoid changing your existing logic)
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $new_password = $_POST['new_password'] ?? '';
  if(strlen($new_password) < 6){ echo json_encode(["status"=>false, "data"=>"Password too short"]); return; }
  if ($id <= 0) { echo json_encode(["status"=>false, "data"=>"Invalid user id"]); return; }

  $passhash = password_hash($new_password, PASSWORD_BCRYPT);

  // prepared statement to avoid injection
  $st = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
  if (!$st) { echo json_encode(["status"=>false, "data"=>"Prepare failed"]); return; }
  $st->bind_param("si", $passhash, $id);
  $ok = $st->execute();

  echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"Password reset":"Update failed"]);
}

/* ========= New: self-service change (for your header modal) ========= */
/* Change own password (from header modal) */
function change_password($conn){
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION['user_id'])) { echo json_encode(["status"=>false,"data"=>"Login required"]); return; }

  $uid  = (int)$_SESSION['user_id'];
  $new  = trim($_POST['new_password'] ?? '');
  $conf = trim($_POST['confirm_password'] ?? '');

  if ($new === '' || $conf === '') { echo json_encode(["status"=>false,"data"=>"Both password fields are required"]); return; }
  if ($new !== $conf)               { echo json_encode(["status"=>false,"data"=>"Passwords do not match"]); return; }
  if (strlen($new) < 3)             { echo json_encode(["status"=>false,"data"=>"Password must be at least 3 characters"]); return; }

  $hash = password_hash($new, PASSWORD_BCRYPT);
  $st = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
  if (!$st) { echo json_encode(["status"=>false,"data"=>"Prepare failed"]); return; }
  $st->bind_param('si', $hash, $uid);
  $ok = $st->execute();
  echo json_encode(["status"=>$ok?true:false, "data"=>$ok?"Password updated":"Update failed"]);
}



/* ========= Dispatch ========= */
if(isset($_POST['action'])){
  $action = $_POST['action'];
  if(function_exists($action)) $action($conn);
  else echo json_encode(["status"=>false, "data"=>"Unknown action"]);
}else{
  echo json_encode(["status"=>false, "data"=>"Action is Required"]);
}

