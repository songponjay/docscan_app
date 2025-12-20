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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ขั้นตอนที่ 2: อัปโหลดเอกสาร</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <style>
        .img-container {
            max-height: 500px;
            background-color: #f7f7f7;
            margin-bottom: 20px;
            display: none; /* ซ่อนไว้ก่อนจนกว่าจะเลือกรูป */
        }
        img {
            max-width: 100%;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>ขั้นตอนที่ 2: อัปโหลดเอกสาร</h2>
        <div class="alert alert-info">
            ประเภทที่เลือก: <strong><?php echo htmlspecialchars($selected_type_name); ?></strong>
        </div>

        <form id="uploadForm" action="process_upload.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="type_id" value="<?php echo $selected_type_id; ?>">
            
            <div class="mb-3">
                <label for="doc_name" class="form-label">ชื่อเรื่องของเอกสาร:</label>
                <input type="text" class="form-control" name="doc_name" id="doc_name" required placeholder="ระบุชื่อเอกสาร...">
            </div>
            
            <div class="mb-3">
                <label for="cameraInput" class="form-label">ถ่ายรูปหรือเลือกไฟล์:</label>
                <!-- capture="environment" บังคับให้มือถือเปิดกล้องหลังทันที -->
                <input type="file" class="form-control" id="cameraInput" accept="image/*" capture="environment">
                <div class="form-text">รองรับไฟล์ .jpg, .png, .jfif (ถ่ายรูปแล้วสามารถ Crop ได้)</div>
            </div>

            <!-- พื้นที่สำหรับ Crop รูป -->
            <div class="img-container">
                <img id="image" src="" alt="Picture">
            </div>

            <!-- Input file ที่ซ่อนอยู่สำหรับส่งไป PHP (จะถูกแทนที่ด้วยรูปที่ Crop แล้ว) -->
            <input type="file" name="document_file" id="finalFile" style="display: none;">

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg" id="btnSubmit">
                    <i class="bi bi-cloud-upload"></i> บันทึกและอัปโหลดเอกสาร
                </button>
                <a href="scan_form.php" class="btn btn-secondary">ย้อนกลับ</a>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        const cameraInput = document.getElementById('cameraInput');
        const image = document.getElementById('image');
        const imgContainer = document.querySelector('.img-container');
        const uploadForm = document.getElementById('uploadForm');
        const finalFile = document.getElementById('finalFile');
        let cropper;

        // เมื่อมีการเลือกไฟล์หรือถ่ายรูป
        cameraInput.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                
                // ตรวจสอบว่าเป็นรูปภาพหรือไม่
                if (/^image\/\w+/.test(file.type)) {
                    const url = URL.createObjectURL(file);
                    
                    // แสดง Container
                    imgContainer.style.display = 'block';
                    image.src = url;

                    // ถ้ามี Cropper เดิมอยู่ให้ทำลายก่อน
                    if (cropper) {
                        cropper.destroy();
                    }

                    // เริ่มต้น Cropper
                    cropper = new Cropper(image, {
                        aspectRatio: NaN, // อิสระ (Free crop)
                        viewMode: 1,      // จำกัดกรอบให้อยู่ในภาพ
                        autoCropArea: 0.8,
                    });
                    
                    // ล้างค่า input เดิมเพื่อป้องกันการส่งไฟล์ต้นฉบับที่ยังไม่ crop
                    cameraInput.value = ''; 
                }
            }
        });

        // ก่อนส่งฟอร์ม ให้แปลงภาพที่ Crop เป็นไฟล์
        uploadForm.addEventListener('submit', function (e) {
            if (cropper) {
                e.preventDefault(); // หยุดการส่งฟอร์มชั่วคราว
                
                // แปลงส่วนที่ Crop เป็น Blob (ไฟล์)
                cropper.getCroppedCanvas().toBlob((blob) => {
                    // สร้าง File Object ใหม่จาก Blob
                    const file = new File([blob], "scanned_doc.jpg", { type: "image/jpeg" });
                    
                    // ใช้ DataTransfer เพื่อใส่ไฟล์เข้าไปใน input type="file" ที่ซ่อนอยู่
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    finalFile.files = dataTransfer.files;

                    // ส่งฟอร์มจริงๆ
                    uploadForm.submit();
                }, 'image/jpeg', 0.8); // คุณภาพ JPEG 80%
            }
            // ถ้าไม่ได้ใช้ Cropper (เช่นอัปโหลด PDF หรือไม่ได้เลือกรูป) ก็ให้ส่งตามปกติ
        });
    </script>
</body>
</html>


       