<?php
session_start();
// ตรวจสอบ Login ก่อน
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

require_once 'db_connect.php';

// ตั้งค่าโฟลเดอร์สำหรับเก็บไฟล์
$upload_dir = 'uploads/';
// สร้างโฟลเดอร์อัตโนมัติถ้ายังไม่มี
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// เพิ่มนามสกุลที่รองรับ (.jfif, .jpg, .jpeg, .png)
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/pjpeg']; // pjpeg บางครั้งใช้กับ jfif
$max_file_size = 50 * 1024 * 1024; // 50 MB

// ตรวจสอบว่ามีการส่งข้อมูลผ่าน POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ดึงข้อมูล Metadata จากฟอร์ม
    $type_id = intval($_POST['type_id']);
    $user_id = $_SESSION['user_id'];
    $doc_name = trim($_POST['doc_name']);
    $upload_time = date('Y-m-d H:i:s'); // เวลาปัจจุบัน
    $status_id = 1; // กำหนดค่าเริ่มต้น status_id = 1 (เช่น ปกติ/รอตรวจสอบ)
    $docsize_id = 1; // กำหนดค่าเริ่มต้น docsize_id = 1 (เนื่องจากไม่ได้ส่งมาจากฟอร์ม)

    // ข้อมูลไฟล์ที่ถูกอัปโหลด
    $file = $_FILES['document_file'];
    $file_tmp_name = $file['tmp_name'];
    $file_type = $file['type'];
    $file_error = $file['error'];
    $file_size = $file['size'];

    // เริ่มต้น Transaction
    $conn->begin_transaction();
    $success = false;
    $error_msg = "";

    try {
        // --- ส่วนที่ 1: ตรวจสอบไฟล์และอัปโหลดจริง ---
        
        if ($file_error !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: Code " . $file_error);
        }
        
        if ($file_size > $max_file_size) {
             throw new Exception("File size exceeds limit.");
        }

        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid file type: Only PDF, JPG, PNG, JFIF are allowed.");
        }

        // สร้างชื่อไฟล์ที่ไม่ซ้ำกัน
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('doc_', true) . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;
        
        // อัปโหลดไฟล์จาก Temp ไปยังโฟลเดอร์ uploads/
        if (!move_uploaded_file($file_tmp_name, $destination)) {
            throw new Exception("Failed to move uploaded file.");
        }
        
        $file_path = $destination; // Path ที่จะถูกบันทึกใน DB

        // --- ส่วนที่ 2: บันทึก Metadata ลงในตาราง document ---
        
        // แก้ไข SQL ให้ตรงกับคอลัมน์: doc_name, type_id, user_id, status_id, docsize_id, doc_scandate
        $stmt_doc = $conn->prepare(
            "INSERT INTO document (doc_name, type_id, user_id, status_id, docsize_id, doc_scandate) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt_doc->bind_param(
            "siiiis", 
            $doc_name,
            $type_id, 
            $user_id,
            $status_id,
            $docsize_id,
            $upload_time
        );
        
        if (!$stmt_doc->execute()) {
            throw new Exception("Error saving document metadata: " . $stmt_doc->error);
        }
        
        $doc_id = $conn->insert_id; // ดึง PK (doc_id) ที่เพิ่งสร้างมาใช้ในตาราง docfile

        // --- ส่วนที่ 3: บันทึกข้อมูลไฟล์ลงในตาราง docfile ---
        
        $stmt_file = $conn->prepare(
            "INSERT INTO docfile (doc_id, file_path, upload_at) 
             VALUES (?, ?, ?)"
        );
        $stmt_file->bind_param(
            "iss", 
            $doc_id, 
            $file_path, 
            $upload_time
        );
        
        if (!$stmt_file->execute()) {
            throw new Exception("Error saving file record: " . $stmt_file->error);
        }

        // ถ้าสำเร็จทั้งหมด: Commit Transaction
        $conn->commit();
        $success = true;

    } catch (Exception $e) {
        // ถ้าเกิดข้อผิดพลาด: Rollback และลบไฟล์ที่อาจจะอัปโหลดไปแล้ว
        $conn->rollback();
        $error_msg = "การอัปโหลดล้มเหลว: " . $e->getMessage();
        // ถ้าไฟล์ถูกอัปโหลดจริงแต่ Transaction ล้มเหลว ให้ลบไฟล์
        if (isset($destination) && file_exists($destination)) {
            unlink($destination); 
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลการอัปโหลด</title>
</head>
<body>
    <h2>ผลการบันทึกเอกสาร</h2>
    
    <?php if ($success): ?>
        <p style="color:green;">✅ บันทึกเอกสารและอัปโหลดไฟล์สำเร็จแล้ว!</p>
        <p>คุณสามารถตรวจสอบไฟล์ได้ที่: <?php echo htmlspecialchars($file_path); ?></p>
        <p>เลขที่เอกสาร (Doc ID): <?php echo $doc_id; ?></p>
    <?php else: ?>
        <p style="color:red;">❌ การบันทึกเอกสารล้มเหลว:</p>
        <p><?php echo htmlspecialchars($error_msg); ?></p>
    <?php endif; ?>
    
    <p style="margin-top: 20px;"><a href="scan_form.php">อัปโหลดเอกสารอีกครั้ง</a></p>
    <p><a href="dashboard.php">กลับสู่หน้าหลัก</a></p>
</body>
</html>