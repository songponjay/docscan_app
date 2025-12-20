<?php
// 1. เริ่ม Session และเรียกไฟล์เชื่อมต่อฐานข้อมูล
session_start();
require_once 'db_connect.php'; // ตรวจสอบว่าไฟล์เชื่อมต่อฐานข้อมูลชื่อนี้จริง

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$error = '';
$success = '';

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาจากฟอร์มสมัครสมาชิกหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2. รับและทำความสะอาดข้อมูล (Sanitize Input)
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $name     = trim($_POST['name']);
    $surname  = trim($_POST['surname']);

    // กำหนดค่าสถานะเริ่มต้น: 1 = User ทั่วไป (ตาม Use Case)
    $status = 1; 

    // 3. ตรวจสอบข้อมูลเบื้องต้น
    if (empty($username) || empty($password) || empty($name) || empty($surname)) {
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } elseif (strlen($password) < 4) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 4 ตัวอักษร";
    }

    if (empty($error)) {
        // 4. ตรวจสอบว่า Username นี้ถูกใช้ไปแล้วหรือไม่
        $check_stmt = $conn->prepare("SELECT user_id FROM user WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "ชื่อผู้ใช้ (Username) นี้มีผู้ใช้งานแล้ว";
        } else {
            // 5. เข้ารหัสรหัสผ่าน (Hashing) ก่อนบันทึก
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 6. เตรียมคำสั่ง SQL สำหรับบันทึกข้อมูล
            $insert_stmt = $conn->prepare("INSERT INTO user (username, pass, name, surname, status) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssi", $username, $hashed_password, $name, $surname, $status); // ssss: string, i: integer

            // 7. ดำเนินการบันทึก
            if ($insert_stmt->execute()) {
                $success = "สมัครสมาชิกสำเร็จ! คุณสามารถเข้าสู่ระบบได้ทันที";
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
    // ปิดการเชื่อมต่อฐานข้อมูล
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลการสมัครสมาชิก</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .message-box { width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>

<div class="message-box">
    <?php if (!empty($success)): ?>
        <h2 class="success">✅ สมัครสมาชิกสำเร็จ</h2>
        <p><?php echo $success; ?></p>
        <p><a href="index.php">คลิกที่นี่เพื่อเข้าสู่ระบบ</a></p>
    <?php elseif (!empty($error)): ?>
        <h2 class="error">❌ สมัครสมาชิกไม่สำเร็จ</h2>
        <p><?php echo $error; ?></p>
        <p><a href="register.php">คลิกที่นี่เพื่อกลับไปสมัครใหม่</a></p>
    <?php else: ?>
        <p>กำลังประมวลผล...</p>
        <p><a href="register.php">กลับสู่หน้าสมัครสมาชิก</a></p>
    <?php endif; ?>
</div>

</body>
</html>