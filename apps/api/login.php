<?php
session_start();
include '../config/conn.php';
header('Content-Type: application/json');

/**
 * POST actions:
 * - action=login    (username, password)
 * - action=logout
 * - action=whoami
 */

function login($conn){
    extract($_POST); // expects $username, $password
    if (empty($username) || empty($password)) {
        echo json_encode(["status"=>false, "data"=>"Username and password are required"]); return;
    }
    $em = $conn->real_escape_string($username);
    $sql = "SELECT id, full_name, username, role, password_hash, is_active
            FROM users WHERE username='$em' LIMIT 1";
    $res = $conn->query($sql);

    if ($res && $res->num_rows === 1) {
        $u = $res->fetch_assoc();
        if ((int)$u['is_active'] !== 1) {
            echo json_encode(["status"=>false, "data"=>"Account is disabled"]); return;
        }
        if (password_verify($password, $u['password_hash'])) {
            $_SESSION['user_id']      = (int)$row['id'];
            $_SESSION['full_name']    = $row['full_name'];
            $_SESSION['role']         = strtoupper(trim($row['role'])); // <-- normalize
           

            echo json_encode([
                "status"=>true,
                "data"=>[
                    "id"=>$u['id'], "name"=>$u['full_name'], "username"=>$u['username'],
                    "role"=>$u['role']
                ]
            ]);
        } else {
            echo json_encode(["status"=>false, "data"=>"Invalid credentials"]);
        }
    } else {
        echo json_encode(["status"=>false, "data"=>"Invalid credentials"]);
    }
}

function logout($conn){
    session_unset();
    session_destroy();
    echo json_encode(["status"=>true, "data"=>"Logged out"]);
}

function whoami($conn){
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            "status"=>true,
            "data"=>[
                "user_id"=>$_SESSION['user_id']??null,
                "full_name"=>$_SESSION['full_name']??null,
                "role"=>$_SESSION['role']??null,

            ]
        ]);
    } else {
        echo json_encode(["status"=>false, "data"=>"Not logged in"]);
    }
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if (function_exists($action)) { $action($conn); }
    else { echo json_encode(["status"=>false, "data"=>"Unknown action"]); }
} else {
    echo json_encode(["status"=>false, "data"=>"Action is Required"]);
}
