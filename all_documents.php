<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบ Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = (isset($_SESSION['status']) && $_SESSION['status'] == 2);
$fullname = $_SESSION['name'] . (isset($_SESSION['surname']) ? ' ' . $_SESSION['surname'] : '');

// --- ส่วนจัดการการอัปเดตสถานะ (สำหรับ Admin) ---
if ($is_admin) {
    // 1. อัปเดตสถานะ
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
        $doc_id = intval($_POST['doc_id']);
        $new_status = intval($_POST['status_id']);
        
        $stmt = $conn->prepare("UPDATE document SET status_id = ? WHERE doc_id = ?");
        $stmt->bind_param("ii", $new_status, $doc_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: all_documents.php?msg=updated");
        exit;
    }

    // 2. ลบเอกสาร
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        
        // ดึง path ไฟล์เพื่อลบไฟล์จริง
        $stmt = $conn->prepare("SELECT file_path FROM docfile WHERE doc_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (file_exists($row['file_path'])) unlink($row['file_path']);
        }
        $stmt->close();

        // ลบข้อมูลในฐานข้อมูล
        $conn->query("DELETE FROM docfile WHERE doc_id = $delete_id");
        $conn->query("DELETE FROM document WHERE doc_id = $delete_id");
        
        header("Location: all_documents.php?msg=deleted");
        exit;
    }
}

// --- ส่วนการค้นหาและกรองข้อมูล ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
$filter_status = isset($_GET['status_id']) ? intval($_GET['status_id']) : 0;

// สร้าง Query
$sql = "SELECT d.doc_id, d.doc_name, d.doc_scandate, d.status_id, t.type_name, df.file_path 
        FROM document d 
        LEFT JOIN type t ON d.type_id = t.type_id 
        LEFT JOIN docfile df ON d.doc_id = df.doc_id 
        WHERE 1=1";

$params = [];
$types = "";

// เงื่อนไขการค้นหา
if (!empty($search)) {
    $sql .= " AND d.doc_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($filter_type > 0) {
    $sql .= " AND d.type_id = ?";
    $params[] = $filter_type;
    $types .= "i";
}
if ($filter_status > 0) {
    $sql .= " AND d.status_id = ?";
    $params[] = $filter_status;
    $types .= "i";
}

$sql .= " ORDER BY d.doc_scandate DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ดึงข้อมูลประเภทเอกสารสำหรับ Dropdown Filter
$type_result = $conn->query("SELECT * FROM type ORDER BY type_name");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการเอกสารทั้งหมด - DocScan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            background: #343a40 !important; /* บังคับใช้สีเข้ม */
            color: #fff;
        }
        @media (min-width: 992px) {
            .sidebar { min-height: 100vh; width: 280px; flex-shrink: 0; }
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        .table-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .icon-box {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: white;
        }
    </style>
</head>
<body>
    <!-- Mobile Top Navbar (แสดงเฉพาะบนมือถือ/Tablet) -->
    <nav class="navbar navbar-dark bg-dark d-lg-none p-3">
        <div class="container-fluid">
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand fw-bold" href="#"><i class="fas fa-file-signature me-2"></i>DocScan</a>
        </div>
    </nav>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar offcanvas-lg offcanvas-start d-flex flex-column p-3 text-white" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title" id="sidebarMenuLabel">DocScan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <a href="#" class="d-none d-lg-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <i class="fas fa-file-signature fa-2x me-2"></i>
                <span class="fs-4 fw-bold">DocScan</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> หน้าแรก</a>
                </li>
                <li>
                    <a href="smart_upload.php" class="nav-link"><i class="fas fa-file-upload"></i> สแกนเอกสารใหม่</a>
                </li>
                <li>
                    <a href="all_documents.php" class="nav-link active"><i class="fas fa-folder-open"></i> รายการเอกสารทั้งหมด</a>
                </li>
                <?php if ($is_admin): ?>
                <hr class="text-secondary">
                <div class="text-uppercase small text-secondary fw-bold mb-2 px-2">ผู้ดูแลระบบ</div>
                <li>
                    <a href="manage_types.php" class="nav-link"><i class="fas fa-tags"></i> จัดการประเภท</a>
                </li>
                <li>
                    <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
                </li>
                <?php endif; ?>
            </ul>
            <!-- Mobile User Info & Logout (แสดงเฉพาะใน Sidebar บนมือถือ) -->
            <div class="mt-auto d-lg-none pt-3 border-top border-secondary">
                <div class="d-flex align-items-center mb-3 px-2">
                    <div class="icon-box bg-secondary me-3"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($fullname); ?></div>
                        <small class="text-white-50"><?php echo $is_admin ? 'Admin' : 'User'; ?></small>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-danger w-100"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1 bg-light w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4 py-3 d-none d-lg-block">
                <div class="container-fluid">
                    <span class="navbar-text ms-auto d-flex align-items-center">
                        <div class="me-3 text-end">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($fullname); ?></div>
                            <div class="small text-muted"><?php echo $is_admin ? 'ผู้ดูแลระบบ (Admin)' : 'ผู้ใช้งานทั่วไป'; ?></div>
                        </div>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </span>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <h3 class="mb-4 fw-bold text-dark">รายการเอกสารทั้งหมด</h3>

                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        if ($_GET['msg'] == 'updated') echo "อัปเดตสถานะเรียบร้อยแล้ว";
                        if ($_GET['msg'] == 'deleted') echo "ลบเอกสารเรียบร้อยแล้ว";
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Search & Filter -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="ค้นหาชื่อเอกสาร..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="type_id">
                                    <option value="0">ทุกประเภทเอกสาร</option>
                                    <?php while($t = $type_result->fetch_assoc()): ?>
                                        <option value="<?php echo $t['type_id']; ?>" <?php echo ($filter_type == $t['type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['type_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status_id">
                                    <option value="0">ทุกสถานะ</option>
                                    <option value="1" <?php echo ($filter_status == 1) ? 'selected' : ''; ?>>ยังไม่ดำเนินการ</option>
                                    <option value="2" <?php echo ($filter_status == 2) ? 'selected' : ''; ?>>ดำเนินการแล้ว</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> ค้นหา</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card table-card bg-white">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Doc ID</th>
                                        <th>ชื่อเอกสาร</th>
                                        <th>ประเภท</th>
                                        <th>วันที่สแกน</th>
                                        <th>สถานะ</th>
                                        <th>ดูภาพ</th>
                                        <?php if ($is_admin): ?><th>จัดการ</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">#<?php echo $row['doc_id']; ?></td>
                                            <td class="fw-medium"><?php echo htmlspecialchars($row['doc_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['type_name']); ?></span></td>
                                            <td class="text-muted"><?php echo date("d/m/Y H:i", strtotime($row['doc_scandate'])); ?></td>
                                            <td>
                                                <?php if ($row['status_id'] == 1): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill">ยังไม่ดำเนินการ</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success rounded-pill">ดำเนินการแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#imageModal" data-bs-src="<?php echo htmlspecialchars($row['file_path']); ?>">
                                                    <i class="fas fa-eye"></i> ดูภาพ
                                                </button>
                                            </td>
                                            <?php if ($is_admin): ?>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#statusModal" data-bs-id="<?php echo $row['doc_id']; ?>" data-bs-status="<?php echo $row['status_id']; ?>" title="เปลี่ยนสถานะ">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <a href="all_documents.php?delete_id=<?php echo $row['doc_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบเอกสารนี้?');" title="ลบเอกสาร">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="<?php echo $is_admin ? '7' : '6'; ?>" class="text-center py-5 text-muted">ไม่พบข้อมูลเอกสาร</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">ตัวอย่างเอกสาร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0" id="modalBody" style="height: 75vh; background-color: #212529; overflow: hidden;"></div>
            </div>
        </div>
    </div>

    <!-- Status Manage Modal (Admin Only) -->
    <?php if ($is_admin): ?>
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header"><h5 class="modal-title">จัดการสถานะเอกสาร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="doc_id" id="statusDocId">
                        <input type="hidden" name="update_status" value="1">
                        <div class="mb-3">
                            <label class="form-label">สถานะ:</label>
                            <select class="form-select" name="status_id" id="statusSelect">
                                <option value="1">ยังไม่ดำเนินการ</option>
                                <option value="2">ดำเนินการแล้ว</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js"></script>
        <script>
        let viewer;
        const imageModal = document.getElementById('imageModal');
        const modalBody = document.getElementById('modalBody');

        imageModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const src = button.getAttribute('data-bs-src');
            
            // 1. ล้างข้อมูลเก่าออกให้หมดก่อน
            if (viewer) {
                viewer.destroy();
                viewer = null;
            }
            modalBody.innerHTML = ''; 
            
            // 2. สร้าง Image Element เพียงอันเดียว
            const img = document.createElement('img');
            img.src = src;
            img.style.display = 'none'; // ซ่อนรูปต้นฉบับไว้ เพราะ Viewer จะสร้างหน้าจอของมันเอง
            img.id = 'previewImage';
            modalBody.appendChild(img);
        });

        imageModal.addEventListener('shown.bs.modal', () => {
            const img = document.getElementById('previewImage');
            // 3. เรียกใช้ Viewer แบบเต็มพิกัด (Inline) เพื่อให้ซูมและหมุนได้ในหน้าจอเดียว
            viewer = new Viewer(img, {
                inline: true, // ทำให้แสดงใน ModalBody ทันที
                button: true, // ปุ่มปิด (ในตัว Viewer)
                navbar: false,
                title: false,
                toolbar: {
                    zoomIn: 4,
                    zoomOut: 4,
                    oneToOne: 4,
                    reset: 4,
                    prev: 0,
                    play: 0,
                    next: 0,
                    rotateLeft: 4,
                    rotateRight: 4,
                    flipHorizontal: 4,
                    flipVertical: 4,
                },
                // ตั้งค่าให้ซูมได้แรงๆ สำหรับเอกสารตัวเล็ก
                zoomRatio: 0.5,
                minZoomRatio: 0.1,
                maxZoomRatio: 10, 
            });
        });

        imageModal.addEventListener('hidden.bs.modal', () => {
            // 4. ทำลาย Viewer เมื่อปิด Modal เพื่อคืนค่าหน่วยความจำ
            if (viewer) {
                viewer.destroy();
                viewer = null;
            }
            modalBody.innerHTML = '';
        });

        // ส่วนจัดการสถานะ (Admin)
        <?php if ($is_admin): ?>
        const statusModal = document.getElementById('statusModal');
        statusModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('statusDocId').value = button.getAttribute('data-bs-id');
            document.getElementById('statusSelect').value = button.getAttribute('data-bs-status');
        });
        <?php endif; ?>
    </script>
</body>
</html>