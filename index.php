<?php
// 1. เริ่ม Session (ต้องอยู่บรรทัดแรกเสมอ)
session_start();
// 2. เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

$error = ''; // ตัวแปรสำหรับเก็บข้อความแสดงข้อผิดพลาด

// ตรวจสอบว่ามีการส่งข้อมูลฟอร์ม Login มาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 3. ป้องกัน SQL Injection และเตรียมคำสั่ง Query (Prepared Statements)
    // ค้นหาผู้ใช้จาก username
    // *** แก้ไขที่นี่: เพิ่มคอลัมน์ 'status' เข้ามาใน SELECT ***
    $stmt = $conn->prepare("SELECT user_id, username, pass, name, surname, status FROM user WHERE username = ?");
    $stmt->bind_param("s", $username); // 's' คือ string (VARCHAR)
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
         $user = $result->fetch_assoc();
        // 4. ตรวจสอบรหัสผ่านที่ถูก Hash
        if (password_verify($password, $user['pass'])) {
            // รหัสผ่านถูกต้อง!
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name']; // เก็บชื่อเพื่อแสดงผลใน dashboard
            $_SESSION['surname'] = $user['surname']; //
                
            // *** แก้ไขที่นี่: เก็บค่าสถานะ (status) ปัจจุบันที่ดึงมาจากฐานข้อมูล ***
            $_SESSION['status'] = $user['status']; // เก็บสถานะล่าสุด 
            // เปลี่ยนเส้นทางไปหน้า Dashboard
            header("location: dashboard.php");
            exit;
        } else {
            // รหัสผ่านไม่ถูกต้อง
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        // ไม่พบชื่อผู้ใช้
        $error = "ไม่พบชื่อผู้ใช้";
    }
}

// ตรวจสอบว่าผู้ใช้ Login แล้วหรือไม่ (เผื่อมีคนพิมพ์ URL ตรงมา)
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบสแกนเอกสาร - เข้าสู่ระบบ</title>
</head>
<body>
        <h2>เข้าสู่ระบบ</h2>
    <p>กรุณาป้อนชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบ</p>
    <?php if (!empty($error)) { echo '<p style="color:red;">' . $error . '</p>'; } ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div>
            <label>ชื่อผู้ใช้ (Username)</label><br>
            <input type="text" name="username" required>
        </div>
        <div style="margin-top: 10px;">
            <label>รหัสผ่าน (Password)</label><br>
            <input type="password" name="password" required>
        </div>
        <div style="margin-top: 15px;">
            <input type="submit" value="เข้าสู่ระบบ">
        </div>
    </form>
    <div style="margin-top: 20px; font-size: 0.9em;">
        ยังไม่มีบัญชีใช่ไหม? 
        <a href="register.php">คลิกที่นี่เพื่อสมัครสมาชิก</a>
    </div>
</body>
</html>