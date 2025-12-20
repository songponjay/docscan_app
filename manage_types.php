<?php
session_start();
// 1. เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// ตรวจสอบ Login ก่อน
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // หากยังไม่ได้ Login ให้ส่งไปหน้า Login
    header("location: index.php");
    exit;
}

// *** 2. ตรวจสอบสิทธิ์ (AUTHORIZATION) ***
// สมมติว่า Admin คือ status = 2
// **ต้องแน่ใจว่าได้เก็บ $_SESSION['status'] เมื่อ Login สำเร็จ**
// หากไม่มีค่า status ใน session ให้ดึงจากฐานข้อมูลก่อน
if (!isset($_SESSION['status'])) {
    // ต้อง Query ฐานข้อมูลเพื่อดึง status
    $stmt_status = $conn->prepare("SELECT status FROM user WHERE user_id = ?");
    $stmt_status->bind_param("i", $_SESSION['user_id']);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    if ($result_status->num_rows == 1) {
        $user_status = $result_status->fetch_assoc();
        $_SESSION['status'] = $user_status['status'];
    }
    $stmt_status->close();
}

// หากไม่ใช่ Admin (status ไม่เท่ากับ 2) ให้แสดงข้อความแจ้งเตือนและยกเลิก
if ($_SESSION['status'] != 2) {
    header("location: dashboard.php"); // ส่งไปหน้าหลักแทน
    // หรือจะแสดงหน้าข้อผิดพลาด Access Denied ก็ได้
    // exit; 
    
    // หากเลือกที่จะแสดงข้อความ Access Denied แทนการ Redirect:
    // echo "<h1>❌ ไม่ได้รับอนุญาต!</h1><p>เฉพาะผู้ดูแลระบบเท่านั้นที่เข้าถึงหน้านี้ได้</p>";
    // exit;
}
// *** จบการตรวจสอบสิทธิ์ ***

$message = '';

// ตรวจสอบการเพิ่มข้อมูลใหม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_type_name'])) {
    $new_type_name = trim($_POST['new_type_name']);
    
    // ... โค้ดเดิมสำหรับเพิ่มประเภทเอกสาร (Create) ...
    if (!empty($new_type_name)) {
        // เตรียมคำสั่งเพิ่มประเภทเอกสารใหม่
        $stmt = $conn->prepare("INSERT INTO type (type_name) VALUES (?)");
        $stmt->bind_param("s", $new_type_name);
        if ($stmt->execute()) {
            $message = "<p style='color:green;'>✅ เพิ่มประเภทเอกสาร: " . htmlspecialchars($new_type_name) . " สำเร็จ!</p>";
        } else {
            $message = "<p style='color:red;'>❌ เพิ่มประเภทเอกสารล้มเหลว: " . $stmt->error . "</p>";
        }
        $stmt->close();
         // Redirect เพื่อป้องกันการส่งซ้ำเมื่อ Refresh
        header("Location: manage_types.php?msg=" . urlencode(strip_tags($message)));
        exit;
    }
}


// --- ส่วนจัดการการลบ (Delete) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['type_id'])) {
// ... (โค้ด DELETE ที่เหลือเหมือนเดิม) ...
    $delete_id = intval($_GET['type_id']);
    // คำสั่ง DELETE
    $stmt = $conn->prepare("DELETE FROM type WHERE type_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "<p style='color:green;'>✅ ลบประเภทเอกสาร (ID: {$delete_id}) สำเร็จ!</p>";
    } else {
        // ข้อความผิดพลาดอาจแสดงว่ามีเอกสารอื่นอ้างอิงถึง type_id นี้อยู่ (Foreign Key)
        $message = "<p style='color:red;'>❌ ลบประเภทเอกสารล้มเหลว หรือมีเอกสารอ้างอิงอยู่: " . $stmt->error . "</p>";
    }
    $stmt->close();
    // Redirect เพื่อป้องกันการลบซ้ำเมื่อ Refresh
    header("Location: manage_types.php?msg=" . urlencode(strip_tags($message)));
    exit;
}

// --- ส่วนจัดการการแก้ไข (Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_type_id']) && isset($_POST['edited_type_name'])) {
// ... (โค้ด UPDATE ที่เหลือเหมือนเดิม) ...
    $edit_id = intval($_POST['edit_type_id']);
    $edited_name = trim($_POST['edited_type_name']);
    if (!empty($edited_name)) {
        // คำสั่ง UPDATE
        $stmt = $conn->prepare("UPDATE type SET type_name = ? WHERE type_id = ?");
        $stmt->bind_param("si", $edited_name, $edit_id);
        if ($stmt->execute()) {
             $message = "<p style='color:green;'>✅ แก้ไขประเภทเอกสาร (ID: {$edit_id}) สำเร็จ!</p>";
        } else {
            $message = "<p style='color:red;'>❌ แก้ไขประเภทเอกสารล้มเหลว: " . $stmt->error . "</p>";
        }
        $stmt->close();
        // Redirect เพื่อป้องกันการส่งซ้ำเมื่อ Refresh
        header("Location: manage_types.php?msg=" . urlencode(strip_tags($message)));
        exit;
    }
}

// ตรวจสอบข้อความแจ้งเตือนจาก Redirect
if (isset($_GET['msg'])) {
    $message = "<p style='color:green;'>" . htmlspecialchars(urldecode($_GET['msg'])) . "</p>";
}

// ดึงรายการประเภทเอกสารทั้งหมดมาแสดง
$types = [];
$result = $conn->query("SELECT type_id, type_name FROM type ORDER BY type_id ASC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $types[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการประเภทเอกสาร</title>
</head>
<body>
    <h2>จัดการประเภทเอกสาร</h2>
    <?php echo $message; ?>
    <hr>

    <h3>เพิ่มประเภทเอกสารใหม่</h3>
         <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
         <label for="new_type_name">ชื่อประเภท:</label>
        <input type="text" id="new_type_name" name="new_type_name" required>
        <input type="submit" value="เพิ่ม">
    </form>
    <hr>
    <h3>รายการประเภทเอกสารปัจจุบัน</h3>
    <?php if (!empty($types)): ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>ชื่อประเภทเอกสาร</th>
            <th>Action</th> </tr>
        <?php foreach ($types as $type): ?>
        <tr>
            <td><?php echo $type['type_id']; ?></td>
            <?php if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['type_id']) && $_GET['type_id'] == $type['type_id']): ?>
                <form method="post" action="manage_types.php" style="display:inline;">
                    <td>
                        <input type="text" name="edited_type_name" value="<?php echo htmlspecialchars($type['type_name']); ?>" required>
                        <input type="hidden" name="edit_type_id" value="<?php echo $type['type_id']; ?>">
                    </td>
                    <td>
                        <input type="submit" value="บันทึก">
                        <a href="manage_types.php">ยกเลิก</a>
                    </td>
                </form>
            <?php else: ?>
                <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                <td>
                    <a href="manage_types.php?action=edit&type_id=<?php echo $type['type_id']; ?>">แก้ไข</a> 
                    | 
                    <a href="manage_types.php?action=delete&type_id=<?php echo $type['type_id']; ?>" 
                    onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบประเภท ID: <?php echo $type['type_id']; ?>?');">ลบ</a>
                </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>ยังไม่มีประเภทเอกสารในฐานข้อมูล</p>
    <?php endif; ?>
    <p style="margin-top: 20px;"><a href="dashboard.php">กลับสู่หน้าหลัก</a></p>
</body>
</html>