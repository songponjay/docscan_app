<?php
// เริ่ม Session ทุกครั้งที่ต้องการใช้ตัวแปร Session
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // ถ้ายังไม่ล็อกอิน ให้เปลี่ยนเส้นทางกลับไปหน้า Login
    header("location: index.php");
    exit;
}

// ข้อมูลผู้ใช้ที่ล็อกอินอยู่
$username = $_SESSION['username'];
// *** ดึงสถานะผู้ใช้มาใช้ในการตรวจสอบ ***
// สมมติว่า Admin คือ status = 2
// **ต้องแน่ใจว่าได้เก็บ $_SESSION['status'] เมื่อ Login สำเร็จ**
$is_admin = (isset($_SESSION['status']) && $_SESSION['status'] == 2);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - ระบบสแกนเอกสาร</title>
</head>
<body>
    <h2>ยินดีต้อนรับสู่ระบบสแกนเอกสาร</h2>
    <p>ผู้ใช้งานปัจจุบัน: <strong><?php echo htmlspecialchars($username); ?></strong> 
       (<?php echo $is_admin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'; ?>)</p>
    <hr>
    <h3>เมนูหลัก</h3>
    <ul>
                <li><a href="scan_form.php">อัปโหลด/สแกนเอกสาร</a></li>
        <li><a href="view_documents.php">ดูรายการเอกสารทั้งหมด</a></li> 
                <?php if ($is_admin): ?>
        <li><a href="manage_types.php">จัดการประเภทเอกสาร</a> <span style="font-size: small;">(สำหรับ Admin)</span></li>
        <li><a href="manage_users.php">จัดการข้อมูลผู้ใช้ระบบ</a> <span style="font-size: small;">(สำหรับ Admin)</span></li>
        <li><a href="manage_status.php">จัดการสถานะเอกสาร</a> <span style="font-size: small;">(สำหรับ Admin)</span></li>
        <?php endif; ?>
    </ul>
    <hr>
     
    <p><a href="logout.php">ออกจากระบบ (Logout)</a></p>
</body>
</html>