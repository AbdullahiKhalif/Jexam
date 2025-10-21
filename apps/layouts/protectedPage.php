<?php
session_start();
if(!isset($_SESSION['name'])){
	header("Location: ../views/login.php");
	exit();
}
?>