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
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            background: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #f1f8ff;
        }
        .img-container {
            max-height: 70vh;
            background-color: #333;
            margin-bottom: 20px;
            border-radius: 5px;
            overflow: hidden;
        }
        img {
            max-width: 100%;
            display: block;
        }
        /* Mobile Buttons */
        .mobile-btn {
            height: 120px;
            border-radius: 15px;
            font-size: 1.2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .mobile-btn i { font-size: 2.5rem; margin-bottom: 10px; }
        
        #step-crop { display: none; }
        #desktop-controls { display: none; }
        #mobile-controls { display: none; }
        
        /* Loading Overlay */
        #loading {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.8); z-index: 9999;
            display: none; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>
    <div id="loading"><div class="spinner-border text-primary" role="status"></div></div>

    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-file-earmark-arrow-up"></i> อัปโหลดเอกสาร</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-light border">
                    ประเภทเอกสาร: <span class="badge bg-info text-dark fs-6"><?php echo htmlspecialchars($selected_type_name); ?></span>
                </div>

                <form id="uploadForm" action="process_upload.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="type_id" value="<?php echo $selected_type_id; ?>">
                    
                    <div class="mb-4">
                        <label for="doc_name" class="form-label fw-bold">ชื่อเรื่องเอกสาร <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" name="doc_name" id="doc_name" required placeholder="เช่น ใบเสร็จรับเงิน...">
                    </div>

                    <!-- Step 1: Select Source -->
                    <div id="step-select-source">
                        
                        <!-- Mobile UI -->
                        <div id="mobile-controls" class="row g-3">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-primary w-100 mobile-btn" onclick="document.getElementById('cameraInput').click()">
                                    <i class="bi bi-camera-fill"></i> ถ่ายรูป
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-success w-100 mobile-btn" onclick="document.getElementById('galleryInput').click()">
                                    <i class="bi bi-images"></i> อัปโหลด
                                </button>
                            </div>
                            <div class="col-12 text-center text-muted mt-2">
                                <small>รองรับ .jpg, .png, .jfif</small>
                            </div>
                        </div>

                        <!-- Desktop UI -->
                        <div id="desktop-controls">
                            <div class="upload-area p-5 text-center" id="dropZone">
                                <i class="bi bi-cloud-arrow-up display-4 text-primary"></i>
                                <h4 class="mt-3">ลากไฟล์มาวางที่นี่</h4>
                                <p class="text-muted">หรือคลิกเพื่อเลือกไฟล์จากคอมพิวเตอร์</p>
                                <button type="button" class="btn btn-primary mt-2" onclick="document.getElementById('galleryInput').click()">เลือกไฟล์</button>
                                <p class="small text-muted mt-3">รองรับไฟล์ .jpg, .png, .jfif</p>
                            </div>
                        </div>

                        <!-- Hidden Inputs -->
                        <!-- กล้อง (Mobile Only) -->
                        <input type="file" id="cameraInput" accept="image/png, image/jpeg, image/pjpeg, .jfif" capture="environment" style="display: none;">
                        <!-- อัปโหลดทั่วไป (Mobile & Desktop) -->
                        <input type="file" id="galleryInput" accept="image/png, image/jpeg, image/pjpeg, .jfif" style="display: none;">
                    </div>

                    <!-- Step 2: Crop & Preview -->
                    <div id="step-crop">
                        <h5 class="mb-3"><i class="bi bi-crop"></i> ปรับแต่งรูปภาพ</h5>
                        <div class="img-container">
                            <img id="image" src="" alt="Picture">
                        </div>
                        
                        <div class="row g-2 mt-3">
                            <div class="col-6">
                                <button type="button" class="btn btn-secondary w-100" id="btnRetake">
                                    <i class="bi bi-arrow-counterclockwise"></i> เลือกใหม่
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-success w-100" id="btnConfirm">
                                    <i class="bi bi-check-circle-fill"></i> ยืนยันการบันทึก
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Input file จริงที่จะส่งไป PHP -->
                    <input type="file" name="document_file" id="finalFile" style="display: none;">
                    
                    <div class="mt-3 text-center">
                        <a href="scan_form.php" class="text-decoration-none text-muted">ยกเลิก / กลับไปหน้าเลือกประเภท</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        // Device Detection
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        // UI Elements
        const mobileControls = document.getElementById('mobile-controls');
        const desktopControls = document.getElementById('desktop-controls');
        const stepSelect = document.getElementById('step-select-source');
        const stepCrop = document.getElementById('step-crop');
        const dropZone = document.getElementById('dropZone');
        
        // Inputs
        const cameraInput = document.getElementById('cameraInput');
        const galleryInput = document.getElementById('galleryInput');
        const image = document.getElementById('image');
        const uploadForm = document.getElementById('uploadForm');
        const finalFile = document.getElementById('finalFile');
        const btnRetake = document.getElementById('btnRetake');
        const loading = document.getElementById('loading');
        
        let cropper;

        // Initial UI State
        if (isMobile) {
            mobileControls.style.display = 'flex';
        } else {
            desktopControls.style.display = 'block';
        }

        // Function to handle file selection
        function handleFileSelect(files) {
            if (files && files.length > 0) {
                const file = files[0];
                
                // ตรวจสอบว่าเป็นรูปภาพหรือไม่
                if (/^image\/\w+/.test(file.type)) {
                    const url = URL.createObjectURL(file);
                    
                    // Switch View
                    stepSelect.style.display = 'none';
                    stepCrop.style.display = 'block';
                    
                    image.src = url;

                    // ถ้ามี Cropper เดิมอยู่ให้ทำลายก่อน
                    if (cropper) {
                        cropper.destroy();
                    }

                    // เริ่มต้น Cropper
                    cropper = new Cropper(image, {
                        aspectRatio: NaN, // อิสระ (Free crop)
                        viewMode: 1,
                        autoCropArea: 0.9,
                        background: false,
                    });
                }
            }
            // Reset inputs
            cameraInput.value = '';
            galleryInput.value = '';
        }

        // Event Listeners for Inputs
        cameraInput.addEventListener('change', (e) => handleFileSelect(e.target.files));
        galleryInput.addEventListener('change', (e) => handleFileSelect(e.target.files));

        // Drag & Drop Logic (Desktop)
        if (!isMobile) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                handleFileSelect(e.dataTransfer.files);
            });
        }

        // Retake Button
        btnRetake.addEventListener('click', () => {
            if (cropper) cropper.destroy();
            image.src = "";
            stepCrop.style.display = 'none';
            stepSelect.style.display = isMobile ? 'block' : 'block'; // Show parent container
            // Ensure correct controls are visible
            if (isMobile) mobileControls.style.display = 'flex';
            else desktopControls.style.display = 'block';
        });

        // ก่อนส่งฟอร์ม ให้แปลงภาพที่ Crop เป็นไฟล์
        uploadForm.addEventListener('submit', function (e) {
            if (!document.getElementById('doc_name').value) return; // Let HTML5 validation handle it

            if (cropper) {
                e.preventDefault(); // หยุดการส่งฟอร์มชั่วคราว
                loading.style.display = 'flex';
                
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
                }, 'image/jpeg', 0.85); // คุณภาพ JPEG 85%
            }
            // ถ้าไม่ได้ใช้ Cropper (เช่นอัปโหลด PDF หรือไม่ได้เลือกรูป) ก็ให้ส่งตามปกติ
        });
    </script>
</body>
</html>


       