<?php
session_start();

// ตรวจสอบ Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

require_once 'db_connect.php';

// ดึงข้อมูลประเภทเอกสาร
$type_sql = "SELECT type_id, type_name FROM type ORDER BY type_name ASC";
$type_result = $conn->query($type_sql);

// ชื่อผู้ใช้สำหรับแสดงผล
$fullname = $_SESSION['name'] . (isset($_SESSION['surname']) ? ' ' . $_SESSION['surname'] : '');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Upload - DocScan</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .main-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            background: white;
            overflow: hidden;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 20px;
        }
        .step-label {
            font-weight: 600;
            color: #764ba2;
            margin-bottom: 10px;
            display: block;
        }
        .mobile-btn {
            height: 100px;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            background: #f8f9fa;
            color: #6c757d;
            cursor: pointer;
        }
        .mobile-btn:hover {
            border-color: #764ba2;
            color: #764ba2;
            background: #f0f4ff;
        }
        .mobile-btn i {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        /* Desktop Upload Area */
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            background: #f8f9fa;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #764ba2;
            background: #f0f4ff;
            color: #764ba2;
        }
        /* Crop UI */
        .img-container {
            position: relative;
            width: 100%;
            max-height: 70vh;
            background-color: #333;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            touch-action: none;
        }
        img {
            max-width: 100%;
            max-height: 70vh;
            display: block;
            user-select: none;
        }
        .crop-handle {
            position: absolute;
            width: 24px;
            height: 24px;
            background-color: rgba(0, 255, 0, 0.8);
            border: 2px solid white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            cursor: move;
            z-index: 10;
        }
        .crop-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 5;
        }
        #loading {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.9); z-index: 9999;
            display: none; align-items: center; justify-content: center; flex-direction: column;
        }
        /* Hide controls by default, JS will toggle */
        #mobile-controls, #desktop-controls {
            display: none;
        }
    </style>
</head>
<body>
    <div id="loading">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div class="text-muted">กำลังประมวลผลภาพ...</div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card main-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Smart Upload</h4>
                            <small class="text-muted">ผู้ใช้: <?php echo htmlspecialchars($fullname); ?></small>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-times"></i></a>
                    </div>
                    <div class="card-body p-4">
                        
                        <form id="uploadForm" action="process_upload.php" method="post" enctype="multipart/form-data">
                            
                            <!-- Step 1: Select Type -->
                            <div class="mb-4">
                                <label for="type_id" class="step-label"><i class="fas fa-tag me-1"></i> 1. เลือกประเภทเอกสาร</label>
                                <select name="type_id" id="type_id" class="form-select form-select-lg" required>
                                    <option value="">-- กรุณาเลือก --</option>
                                    <?php
                                    if ($type_result->num_rows > 0) {
                                        while($row = $type_result->fetch_assoc()) {
                                            echo '<option value="' . $row["type_id"] . '">' . htmlspecialchars($row["type_name"]) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Step 2: Document Name -->
                            <div class="mb-4">
                                <label for="doc_name" class="step-label"><i class="fas fa-pen me-1"></i> 2. ชื่อเอกสาร</label>
                                <input type="text" class="form-control form-control-lg" name="doc_name" id="doc_name" placeholder="ระบุชื่อเอกสาร..." required>
                            </div>

                            <!-- Step 3: Camera/Upload -->
                            <div id="step-source">
                                <label class="step-label"><i class="fas fa-camera me-1"></i> 3. อัปโหลดรูปภาพ</label>
                                <!-- Mobile View -->
                                <div id="mobile-controls" class="row g-3">
                                    <div class="col-6">
                                        <div class="mobile-btn" onclick="document.getElementById('cameraInput').click()">
                                            <i class="fas fa-camera"></i>
                                            <span>ถ่ายรูป</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mobile-btn" onclick="document.getElementById('galleryInput').click()">
                                            <i class="fas fa-images"></i>
                                            <span>อัปโหลด</span>
                                        </div>
                                    </div>
                                    <div class="col-12 text-center text-muted small mt-2">รองรับไฟล์ .jpg, .png, .jfif</div>
                                </div>

                                <!-- Desktop View -->
                                <div id="desktop-controls">
                                    <div class="upload-area" id="dropZone">
                                        <i class="fas fa-file-image fa-4x mb-3 text-secondary"></i>
                                        <h5 class="fw-bold text-dark">ลากไฟล์เอกสารมาวางที่นี่</h5>
                                        <p class="text-muted mb-0">หรือคลิกเพื่อเลือกไฟล์จากคอมพิวเตอร์</p>
                                        <small class="text-muted mt-2 d-block">รองรับไฟล์ .jpg, .png, .jfif</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Crop Area (Hidden initially) -->
                            <div id="step-crop" style="display: none;">
                                <label class="step-label text-success"><i class="fas fa-crop-alt me-1"></i> ปรับแต่งภาพ (ลาก 4 มุม)</label>
                                <div class="img-container" id="cropContainer">
                                    <img id="image" src="" alt="Picture">
                                    <svg class="crop-overlay">
                                        <polygon id="cropPolygon" points="" style="fill: rgba(0, 255, 0, 0.2); stroke: #00ff00; stroke-width: 2;" />
                                    </svg>
                                    <div class="crop-handle" id="tl"></div>
                                    <div class="crop-handle" id="tr"></div>
                                    <div class="crop-handle" id="br"></div>
                                    <div class="crop-handle" id="bl"></div>
                                </div>
                                <div class="row g-2 mt-3">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-secondary w-100 py-2" id="btnRetake">
                                            <i class="fas fa-redo me-1"></i> เลือกใหม่
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="btnConfirm">
                                            <i class="fas fa-check me-1"></i> ยืนยัน
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden Inputs -->
                            <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display: none;">
                            <input type="file" id="galleryInput" accept="image/*" style="display: none;">
                            <input type="file" name="document_file" id="finalFile" style="display: none;">

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script async src="https://docs.opencv.org/4.8.0/opencv.js" onload="onOpenCvReady();" type="text/javascript"></script>
    <script>
        let openCvReady = false;
        function onOpenCvReady() { openCvReady = true; }

        let originalImage = new Image(); // Object สำหรับเก็บภาพความละเอียดเต็ม

        const stepSource = document.getElementById('step-source');
        const stepCrop = document.getElementById('step-crop');
        const image = document.getElementById('image');
        const uploadForm = document.getElementById('uploadForm');
        const finalFile = document.getElementById('finalFile');
        const loading = document.getElementById('loading');
        
        // Device Detection & UI Toggling
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 992;
        const mobileControls = document.getElementById('mobile-controls');
        const desktopControls = document.getElementById('desktop-controls');
        const dropZone = document.getElementById('dropZone');

        if (isMobile) {
            mobileControls.style.display = 'flex';
        } else {
            desktopControls.style.display = 'block';
        }
        
        // Handles & Points
        const handles = { tl: document.getElementById('tl'), tr: document.getElementById('tr'), br: document.getElementById('br'), bl: document.getElementById('bl') };
        const cropPolygon = document.getElementById('cropPolygon');
        const cropContainer = document.getElementById('cropContainer');
        let points = { tl: {x:0.1, y:0.1}, tr: {x:0.9, y:0.1}, br: {x:0.9, y:0.9}, bl: {x:0.1, y:0.9} };

        // File Selection
        function handleFileSelect(files) {
            if (files && files.length > 0) {
                const file = files[0];
                if (/^image\/\w+/.test(file.type)) {
                    const url = URL.createObjectURL(file);
                    // 1. โหลดภาพเข้า Object `originalImage` เพื่อให้ได้ขนาดจริง
                    originalImage.src = url;
                    originalImage.onload = () => {
                        // 2. เมื่อโหลดเสร็จแล้ว จึงแสดงภาพ Preview บนหน้าจอ
                        image.src = url; 
                        stepSource.style.display = 'none';
                        stepCrop.style.display = 'block';
                        image.onload = initCropUI; // ตั้งค่า UI หลังจากภาพแสดงผล
                    };
                }
            }
            document.getElementById('cameraInput').value = '';
            document.getElementById('galleryInput').value = '';
        }

        document.getElementById('cameraInput').addEventListener('change', (e) => handleFileSelect(e.target.files));
        document.getElementById('galleryInput').addEventListener('change', (e) => handleFileSelect(e.target.files));
        document.getElementById('btnRetake').addEventListener('click', () => {
            image.src = "";
            stepCrop.style.display = 'none';
            stepSource.style.display = 'block';
        });

        // Desktop Drag & Drop Logic
        if (!isMobile) {
            dropZone.addEventListener('click', () => document.getElementById('galleryInput').click());
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
            dropZone.addEventListener('drop', (e) => {
                dropZone.classList.remove('dragover');
                handleFileSelect(e.dataTransfer.files);
            });
        }

        // Crop UI Logic
        function initCropUI() {
            points = { tl: {x:0.1, y:0.1}, tr: {x:0.9, y:0.1}, br: {x:0.9, y:0.9}, bl: {x:0.1, y:0.9} };
            updateOverlay();
        }
        function updateOverlay() {
            const rect = image.getBoundingClientRect();
            const cRect = cropContainer.getBoundingClientRect();
            const offX = rect.left - cRect.left;
            const offY = rect.top - cRect.top;
            const toPx = (pt) => ({ x: offX + pt.x * rect.width, y: offY + pt.y * rect.height });
            
            const pPx = { tl: toPx(points.tl), tr: toPx(points.tr), br: toPx(points.br), bl: toPx(points.bl) };
            for (let k in handles) { handles[k].style.left = pPx[k].x + 'px'; handles[k].style.top = pPx[k].y + 'px'; }
            cropPolygon.setAttribute('points', `${pPx.tl.x},${pPx.tl.y} ${pPx.tr.x},${pPx.tr.y} ${pPx.br.x},${pPx.br.y} ${pPx.bl.x},${pPx.bl.y}`);
        }

        // Dragging
        let activeHandle = null;
        const handleStart = (e) => { if (e.target.classList.contains('crop-handle')) { e.preventDefault(); activeHandle = e.target.id; } };
        const handleMove = (e) => {
            if (!activeHandle) return;
            e.preventDefault();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const rect = image.getBoundingClientRect();
            let x = (clientX - rect.left) / rect.width;
            let y = (clientY - rect.top) / rect.height;
            points[activeHandle] = { x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y)) };
            updateOverlay();
        };
        const handleEnd = () => { activeHandle = null; };

        cropContainer.addEventListener('mousedown', handleStart); cropContainer.addEventListener('touchstart', handleStart, {passive: false});
        window.addEventListener('mousemove', handleMove); window.addEventListener('touchmove', handleMove, {passive: false});
        window.addEventListener('mouseup', handleEnd); window.addEventListener('touchend', handleEnd);
        new ResizeObserver(updateOverlay).observe(image);

        // Submit & Process
        uploadForm.addEventListener('submit', function (e) {
            if (!uploadForm.checkValidity()) return; // ให้ Browser ตรวจสอบ required fields
            if (image.src && openCvReady) {
                e.preventDefault();
                loading.style.display = 'flex';
                
                // ใช้ setTimeout เพื่อให้ UI แสดง Loading ก่อนเริ่มประมวลผลหนัก
                setTimeout(() => {
                    let src = null, dst = null, rgb = null, lab = null, planes = null, rgb_planes = null, L = null, a = null, b = null, blue = null, L_orig = null, bg = null, result = null, M = null, srcTri = null, dstTri = null;
                    try {
                        // 1. อ่านภาพต้นฉบับ (Full Resolution) จาก Object ที่โหลดไว้
                        src = cv.imread(originalImage);
                        dst = new cv.Mat();
                        
                        // 2. ใช้ naturalWidth/Height เพื่อความแม่นยำในการคำนวณพิกัด
                        const w = originalImage.naturalWidth;
                        const h = originalImage.naturalHeight;

                        // 3. คำนวณพิกัดจาก % ไปเป็น Pixel บนภาพจริง
                        srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
                            points.tl.x * w, points.tl.y * h,
                            points.tr.x * w, points.tr.y * h,
                            points.br.x * w, points.br.y * h,
                            points.bl.x * w, points.bl.y * h
                        ]);

                        // คำนวณขนาดปลายทาง (Destination Size)
                        const wTop = Math.hypot((points.tr.x - points.tl.x) * w, (points.tr.y - points.tl.y) * h);
                        const wBot = Math.hypot((points.br.x - points.bl.x) * w, (points.br.y - points.bl.y) * h);
                        const hLeft = Math.hypot((points.bl.x - points.tl.x) * w, (points.bl.y - points.tl.y) * h);
                        const hRight = Math.hypot((points.br.x - points.tr.x) * w, (points.br.y - points.tr.y) * h);
                        
                        const maxW = Math.max(wTop, wBot);
                        const maxH = Math.max(hLeft, hRight);

                        dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
                            0, 0,
                            maxW, 0,
                            maxW, maxH,
                            0, maxH
                        ]);

                        // 4. ทำ Warp Perspective (ใช้ INTER_LANCZOS4 เพื่อความคมชัดสูงสุด)
                        M = cv.getPerspectiveTransform(srcTri, dstTri);
                        cv.warpPerspective(src, dst, M, new cv.Size(maxW, maxH), cv.INTER_LANCZOS4, cv.BORDER_CONSTANT, new cv.Scalar());

                        // 4.1 Adaptive Scaling (Resize if too large to prevent crash/noise)
                        let maxDim = Math.max(dst.cols, dst.rows);
                        if (maxDim > 2000) {
                            let scale = 2000 / maxDim;
                            let newSize = new cv.Size(Math.round(dst.cols * scale), Math.round(dst.rows * scale));
                            cv.resize(dst, dst, newSize, 0, 0, cv.INTER_AREA);
                        }

                        // 5. Convert to RGB (Work in Color Space)
                        rgb = new cv.Mat();
                        cv.cvtColor(dst, rgb, cv.COLOR_RGBA2RGB, 0);

                        // 6. Color Preservation (LAB Space)
                        lab = new cv.Mat();
                        cv.cvtColor(rgb, lab, cv.COLOR_RGB2Lab);
                        planes = new cv.MatVector();
                        cv.split(lab, planes);
                        L = planes.get(0);
                        a = planes.get(1);
                        b = planes.get(2);

                        // Keep original L for Detail Recovery
                        L_orig = L.clone();

                        // 7. Yellow & Shadow Removal (Blue Channel Mask)
                        // Extract Blue channel to help remove yellow stains (which are dark in Blue)
                        rgb_planes = new cv.MatVector();
                        cv.split(rgb, rgb_planes);
                        blue = rgb_planes.get(2);

                        // 8. Non-Destructive Background Flattening
                        bg = new cv.Mat();
                        // Use GaussianBlur (101) to estimate background from Blue channel
                        cv.GaussianBlur(blue, bg, new cv.Size(101, 101), 0);
                        // Normalize L channel using Blue background
                        cv.divide(L, bg, L, 255, -1);

                        // 9. Sharpening & Re-inking
                        L.convertTo(L, -1, 1.25, -35);

                        // 10. Final Detail Recovery (Blend 30% Original)
                        cv.addWeighted(L, 0.7, L_orig, 0.3, 0, L);

                        // 11. Merge & Convert back
                        planes.set(0, L); planes.set(1, a); planes.set(2, b);
                        cv.merge(planes, lab);
                        
                        result = new cv.Mat();
                        cv.cvtColor(lab, result, cv.COLOR_Lab2RGB);

                        // 8. ส่งออกผลลัพธ์เป็นไฟล์คุณภาพสูงสุด
                        let canvas = document.createElement('canvas');
                        cv.imshow(canvas, result);
                        
                        canvas.toBlob((blob) => {
                            const dt = new DataTransfer();
                            dt.items.add(new File([blob], "scanned_doc.png", { type: "image/png" }));
                            finalFile.files = dt.files;
                            
                            // Cleanup Memory
                            if(src) src.delete(); 
                            if(dst) dst.delete(); 
                            if(rgb) rgb.delete();
                            if(lab) lab.delete();
                            if(planes) planes.delete();
                            if(rgb_planes) rgb_planes.delete();
                            if(L) L.delete();
                            if(a) a.delete();
                            if(b) b.delete();
                            if(blue) blue.delete();
                            if(L_orig) L_orig.delete();
                            if(bg) bg.delete();
                            if(result) result.delete();
                            if(M) M.delete(); 
                            if(srcTri) srcTri.delete(); 
                            if(dstTri) dstTri.delete();
                            
                            uploadForm.submit();
                        }, 'image/png'); // ใช้ PNG เพื่อป้องกันการเกิด Artifacts

                    } catch (err) { 
                        console.error(err);
                        alert("เกิดข้อผิดพลาดในการประมวลผล: " + err); 
                        loading.style.display = 'none';
                        // Cleanup on error
                        if(src) src.delete(); 
                        if(dst) dst.delete(); 
                        if(rgb) rgb.delete();
                        if(lab) lab.delete();
                        if(planes) planes.delete();
                        if(rgb_planes) rgb_planes.delete();
                        if(L) L.delete();
                        if(a) a.delete();
                        if(b) b.delete();
                        if(blue) blue.delete();
                        if(L_orig) L_orig.delete();
                        if(bg) bg.delete();
                        if(result) result.delete();
                        if(M) M.delete(); 
                        if(srcTri) srcTri.delete(); 
                        if(dstTri) dstTri.delete();
                    }
                }, 100); // Delay เล็กน้อยเพื่อให้ UI อัปเดต
            } else if (!openCvReady) { alert("รอโหลดระบบประมวลผลภาพสักครู่..."); e.preventDefault(); }
        });
    </script>
</body>
</html>