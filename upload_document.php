<?php
session_start();
// ตรวจสอบ Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

require_once 'db_connect.php';

// 1. รับค่า type_id ที่ผู้ใช้เลือกมาจากหน้า scan_form.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['type_id'])) {
    $selected_type_id = intval($_POST['type_id']);
    
    // ดึง type_name มาแสดงผล
    $stmt = $conn->prepare("SELECT type_name FROM type WHERE type_id = ?");
    $stmt->bind_param("i", $selected_type_id); // 'i' คือ integer (INT)
    $stmt->execute();
    $type_info = $stmt->get_result()->fetch_assoc();
    $selected_type_name = $type_info['type_name'];
    
} else {
    // ถ้าไม่มี type_id ส่งมา ให้กลับไปหน้าเลือกประเภท
    header("location: scan_form.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขั้นตอนที่ 2: อัปโหลดเอกสาร</title>
</head>
<body>
    <h2>ขั้นตอนที่ 2: อัปโหลดเอกสาร</h2>
    <p>ประเภทที่เลือก: <strong><?php echo htmlspecialchars($selected_type_name); ?></strong></p>
    <hr>

    <form action="process_upload.php" method="post" enctype="multipart/form-data">
        
        <input type="hidden" name="type_id" value="<?php echo $selected_type_id; ?>">
        
        <div>
            <label for="doc_name">ชื่อเรื่องของเอกสาร:</label><br>
            <input type="text" name="doc_name" id="doc_name" size="50" required>
        </div>
        
        
        <div style="margin-top: 20px;">
            <label for="document_file">เลือกไฟล์เอกสาร (PDF, JPG):</label><br>
            <input type="file" name="document_file" id="document_file" required>
        </div>
        
        <div style="margin-top: 25px;">
            <input type="submit" value="บันทึกและอัปโหลดเอกสาร">
        </div>
    </form>
    
</body>
</html>


       