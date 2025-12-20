<?php
// ไฟล์: logout.php

session_start();

// 1. ล้างข้อมูลทั้งหมดใน $_SESSION
$_SESSION = array();

// 2. ทำลาย Session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// เปลี่ยนเส้นทางไปหน้า Login
header("location: index.php"); 
exit;
?>