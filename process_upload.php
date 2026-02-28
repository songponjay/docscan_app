<?php
session_start();
// ตรวจสอบ Login ก่อน
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

// ฟังก์ชันทำความสะอาดชื่อโฟลเดอร์ (Requirement 4)
function clean_folder_name($name) {
    $name = trim($name);
    // เก็บตัวอักษร (รวมภาษาไทย), ตัวเลข และแทนที่อักขระพิเศษอื่นๆ ด้วย _
    return preg_replace('/[^\p{L}\p{N}]/u', '_', $name);
}

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
    
    // --- ส่วนที่เพิ่ม: ดึงชื่อประเภทเพื่อใช้ตั้งชื่อโฟลเดอร์ (Requirement 1) ---
    $type_folder = 'uncategorized'; // ค่าเริ่มต้นกรณีหาไม่เจอ
    $type_prefix = 'DOC';
    $last_number = 0;
    $stmt_type = $conn->prepare("SELECT type_name, type_prefix, last_number FROM type WHERE type_id = ?");
    $stmt_type->bind_param("i", $type_id);
    $stmt_type->execute();
    $res_type = $stmt_type->get_result();
    if ($row_type = $res_type->fetch_assoc()) {
        $type_folder = clean_folder_name($row_type['type_name']);
        $type_prefix = !empty($row_type['type_prefix']) ? $row_type['type_prefix'] : 'DOC';
        $last_number = intval($row_type['last_number']);
    }
    $stmt_type->close();
    // -------------------------------------------------------------------

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
        
        // --- Logic ตั้งชื่อไฟล์แบบ Running Number ---
        $next_number = $last_number + 1;
        $gen_file_name = $type_prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT); // ชื่อไฟล์ระบบ เช่น A000001
        $new_file_name = $gen_file_name . '.' . $file_ext;
        
        // --- ส่วนที่เพิ่ม: สร้างโฟลเดอร์และกำหนด Path ใหม่ (Requirement 2, 3) ---
        $target_dir = $upload_dir . $type_folder . '/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $destination = $target_dir . $new_file_name;
        
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
            $doc_name, // ใช้ชื่อที่ผู้ใช้พิมพ์ ($doc_name) เก็บลงตาราง document เพื่อให้อ่านเข้าใจง่าย
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

        // --- ส่วนที่ 4: อัปเดต last_number ในตาราง type ---
        $stmt_update_type = $conn->prepare("UPDATE type SET last_number = last_number + 1 WHERE type_id = ?");
        $stmt_update_type->bind_param("i", $type_id);
        if (!$stmt_update_type->execute()) {
            // หากการอัปเดตตัวนับล้มเหลว ให้ Transaction ทำการ Rollback เพื่อป้องกันเลขกระโดด
            throw new Exception("Error updating type counter: " . $stmt_update_type->error);
        }

        // --- ส่วนที่ 4: อัปเดต last_number ในตาราง type ---
        $stmt_update_type = $conn->prepare("UPDATE type SET last_number = last_number + 1 WHERE type_id = ?");
        $stmt_update_type->bind_param("i", $type_id);
        if (!$stmt_update_type->execute()) {
            // หากการอัปเดตตัวนับล้มเหลว ให้ Transaction ทำการ Rollback เพื่อป้องกันเลขกระโดด
            throw new Exception("Error updating type counter: " . $stmt_update_type->error);
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

// URL-encode the file path for safe display in <img> src, especially for non-ASCII folder names
if (isset($file_path)) {
    $encoded_file_path = implode('/', array_map('rawurlencode', explode('/', $file_path)));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการอัปโหลด</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .scan-preview {
            max-width: 100%;
            border: 10px solid white;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
        }
        @media print {
            .no-print { display: none !important; }
            .scan-preview { border: none; box-shadow: none; max-width: 100%; }
            body { background-color: white; }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header <?php echo $success ? 'bg-success' : 'bg-danger'; ?> text-white">
                <h4 class="mb-0">
                    <i class="bi <?php echo $success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i> 
                    <?php echo $success ? 'บันทึกเอกสารสำเร็จ' : 'เกิดข้อผิดพลาด'; ?>
                </h4>
            </div>
            <div class="card-body text-center">
                <?php if ($success): ?>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <!-- Image Preview -->
                            <img src="<?php echo isset($encoded_file_path) ? htmlspecialchars($encoded_file_path) : ''; ?>" class="img-fluid scan-preview" alt="Scanned Document">
                            
                            <!-- Information -->
                            <div class="card mb-3 text-start">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><i class="bi bi-info-circle"></i> รายละเอียดเอกสาร</h5>
                                    <p class="card-text mb-1"><strong>รหัสเอกสาร (Doc ID):</strong> <?php echo $doc_id; ?></p>
                                    <p class="card-text mb-1"><strong>ชื่อเอกสาร:</strong> <?php echo htmlspecialchars($doc_name); ?></p>
                                    <p class="card-text mb-0"><strong>วันที่สแกน:</strong> <?php echo htmlspecialchars($upload_time); ?></p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-center gap-2 no-print">
                                <!--
                                <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-primary" download>
                                    <i class="bi bi-download"></i> ดาวน์โหลดรูปภาพ
                                </a>
                                <button onclick="window.print()" class="btn btn-secondary">
                                    <i class="bi bi-printer"></i> พิมพ์เอกสาร
                                </button>
                                -->
                                <a href="smart_upload.php" class="btn btn-outline-success">
                                    <i class="bi bi-plus-circle"></i> สแกนเพิ่ม
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-dark">
                                    <i class="bi bi-house"></i> กลับหน้าหลัก
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">การบันทึกเอกสารล้มเหลว</h5>
                        <p><?php echo htmlspecialchars($error_msg); ?></p>
                    </div>
                    <div class="mt-3">
                        <a href="scan_form.php" class="btn btn-primary">ลองใหม่อีกครั้ง</a>
                        <a href="dashboard.php" class="btn btn-secondary">กลับหน้าหลัก</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>