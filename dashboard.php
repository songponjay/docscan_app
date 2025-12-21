<?php
// เริ่ม Session ทุกครั้งที่ต้องการใช้ตัวแปร Session
session_start();
require_once 'db_connect.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // ถ้ายังไม่ล็อกอิน ให้เปลี่ยนเส้นทางกลับไปหน้า Login
    header("location: index.php");
    exit;
}

// ข้อมูลผู้ใช้ที่ล็อกอินอยู่
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : $username;
$surname = isset($_SESSION['surname']) ? $_SESSION['surname'] : '';
$fullname = trim($name . ' ' . $surname);

// *** ดึงสถานะผู้ใช้มาใช้ในการตรวจสอบ ***
// สมมติว่า Admin คือ status = 2
// **ต้องแน่ใจว่าได้เก็บ $_SESSION['status'] เมื่อ Login สำเร็จ**
$is_admin = (isset($_SESSION['status']) && $_SESSION['status'] == 2);

// --- ดึงข้อมูลสรุป (Summary Cards) ---
// 1. จำนวนเอกสารทั้งหมดของผู้ใช้
$sql_total = "SELECT COUNT(*) as count FROM document WHERE user_id = ?";
$stmt = $conn->prepare($sql_total);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_total = $stmt->get_result();
$total_docs = $res_total->fetch_assoc()['count'];
$stmt->close();

// 2. รายการที่รอตรวจสอบ (สมมติ status_id = 1 คือ รอตรวจสอบ)
$sql_pending = "SELECT COUNT(*) as count FROM document WHERE user_id = ? AND status_id = 1";
$stmt = $conn->prepare($sql_pending);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_pending = $stmt->get_result();
$pending_docs = $res_pending->fetch_assoc()['count'];
$stmt->close();

// 3. จำนวนประเภทเอกสารทั้งหมด (System-wide)
$sql_types = "SELECT COUNT(*) as count FROM type";
$res_types = $conn->query($sql_types);
$total_types = $res_types->fetch_assoc()['count'];

// --- ดึงรายการเอกสารล่าสุด 5 รายการ ---
$sql_recent = "SELECT doc_name, doc_scandate FROM document WHERE user_id = ? ORDER BY doc_scandate DESC LIMIT 5";
$stmt = $conn->prepare($sql_recent);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_docs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบสแกนเอกสาร</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: #fff;
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
        .card-summary {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .card-summary:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .table-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar d-flex flex-column flex-shrink-0 p-3" style="width: 280px;">
            <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <i class="fas fa-file-signature fa-2x me-2"></i>
                <span class="fs-4 fw-bold">DocScan</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active" aria-current="page">
                        <i class="fas fa-home"></i> หน้าแรก
                    </a>
                </li>
                <li>
                    <a href="smart_upload.php" class="nav-link">
                        <i class="fas fa-file-upload"></i> สแกนเอกสารใหม่
                    </a>
                </li>
                <li>
                    <a href="view_documents.php" class="nav-link">
                        <i class="fas fa-folder-open"></i> รายการเอกสารทั้งหมด
                    </a>
                </li>
                
                <?php if ($is_admin): ?>
                <hr class="text-secondary">
                <div class="text-uppercase small text-secondary fw-bold mb-2 px-2">ผู้ดูแลระบบ</div>
                <li>
                    <a href="manage_types.php" class="nav-link">
                        <i class="fas fa-tags"></i> จัดการประเภท
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="nav-link">
                        <i class="fas fa-users"></i> จัดการผู้ใช้
                    </a>
                </li>
                <li>
                    <a href="manage_status.php" class="nav-link">
                        <i class="fas fa-tasks"></i> จัดการสถานะ
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1 bg-light">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4 py-3">
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

            <!-- Dashboard Content -->
            <div class="container-fluid p-4">
                <h3 class="mb-4 fw-bold text-dark">ภาพรวมระบบ</h3>
                
                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card card-summary h-100 bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="icon-box bg-primary me-3">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">จำนวนเอกสารทั้งหมด</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($total_docs); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-summary h-100 bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="icon-box bg-warning me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">รายการที่รอตรวจสอบ</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($pending_docs); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-summary h-100 bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="icon-box bg-success me-3">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">ประเภทเอกสาร</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($total_types); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Table -->
                <div class="card table-card bg-white">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>รายการเอกสารล่าสุด 5 รายการ</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">ชื่อเอกสาร</th>
                                        <th>วันที่สแกน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_docs->num_rows > 0): ?>
                                        <?php while($row = $recent_docs->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4 fw-medium"><?php echo htmlspecialchars($row['doc_name']); ?></td>
                                            <td class="text-muted">
                                                <i class="far fa-calendar-alt me-1"></i> 
                                                <?php echo date("d/m/Y H:i", strtotime($row['doc_scandate'])); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted">ยังไม่มีประวัติการอัปโหลดเอกสาร</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
$stmt->close();
$conn->close(); 
?>