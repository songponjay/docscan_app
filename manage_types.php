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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการประเภทเอกสาร</title>
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
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card main-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-tags me-2 text-primary"></i>จัดการประเภทเอกสาร</h4>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก</a>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-light border shadow-sm mb-4" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Add Form -->
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fas fa-plus-circle me-2"></i>เพิ่มประเภทใหม่</h5>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-tag text-muted"></i></span>
                                            <input type="text" class="form-control" id="new_type_name" name="new_type_name" placeholder="ระบุชื่อประเภทเอกสาร..." required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> บันทึก</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Table -->
                        <h5 class="mb-3"><i class="fas fa-list me-2"></i>รายการประเภทเอกสาร</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 10%;">ID</th>
                                        <th>ชื่อประเภทเอกสาร</th>
                                        <th style="width: 20%;" class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($types)): ?>
                                        <?php foreach ($types as $type): ?>
                                        <tr>
                                            <td><?php echo $type['type_id']; ?></td>
                                            
                                            <?php if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['type_id']) && $_GET['type_id'] == $type['type_id']): ?>
                                                <!-- Edit Mode -->
                                                <form method="post" action="manage_types.php">
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control" name="edited_type_name" value="<?php echo htmlspecialchars($type['type_name']); ?>" required>
                                                            <input type="hidden" name="edit_type_id" value="<?php echo $type['type_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="submit" class="btn btn-sm btn-success me-1" title="บันทึก"><i class="fas fa-check"></i></button>
                                                        <a href="manage_types.php" class="btn btn-sm btn-secondary" title="ยกเลิก"><i class="fas fa-times"></i></a>
                                                    </td>
                                                </form>
                                            <?php else: ?>
                                                <!-- View Mode -->
                                                <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                                                <td class="text-center">
                                                    <a href="manage_types.php?action=edit&type_id=<?php echo $type['type_id']; ?>" class="btn btn-sm btn-warning text-white me-1" title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_types.php?action=delete&type_id=<?php echo $type['type_id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบประเภท ID: <?php echo $type['type_id']; ?>?');"
                                                       title="ลบ">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">ยังไม่มีข้อมูลประเภทเอกสาร</td>
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