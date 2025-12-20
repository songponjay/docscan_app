<?php
session_start();
// ตรวจสอบ Login ก่อน (สำคัญทุกหน้า)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

require_once 'db_connect.php'; 

// 1. ดึงข้อมูลประเภทเอกสารจากตาราง type
$type_sql = "SELECT type_id, type_name FROM type ORDER BY type_name ASC";
$type_result = $conn->query($type_sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขั้นตอนที่ 1: เลือกประเภทเอกสาร</title>
</head>
<body>
    <h2>ขั้นตอนที่ 1: เลือกประเภทเอกสาร</h2>
    <p>ผู้ใช้งาน: <?php 
    $user_name = $_SESSION['name'];
    $user_surname = isset($_SESSION['surname']) ? ' ' . $_SESSION['surname'] : '';
    echo htmlspecialchars($user_name . $user_surname); 
    ?></p>
    <hr>

    <form action="upload_document.php" method="post">
        
        <div>
            <label for="type_id">ประเภทเอกสาร:</label>
            <select name="type_id" id="type_id" required>
                <option value="">-- เลือกประเภท --</option>
                <?php
                // วนลูปแสดงผลประเภทเอกสารใน Dropdown
                if ($type_result->num_rows > 0) {
                    while($row = $type_result->fetch_assoc()) {
                        // value คือ type_id ส่วนข้อความที่แสดงคือ type_name
                        echo '<option value="' . $row["type_id"] . '">' . htmlspecialchars($row["type_name"]) . '</option>';
                    }
                } else {
                    echo '<option value="" disabled>ไม่มีข้อมูลประเภทเอกสาร (กรุณาเพิ่มในฐานข้อมูล)</option>';
                }
                ?>
            </select>
        </div>
        
        <div style="margin-top: 15px;">
            <input type="submit" value="ถัดไป">
        </div>
        
        <p style="margin-top: 20px;"><a href="dashboard.php">กลับสู่หน้าหลัก</a></p>
    </form>
    
</body>
</html>