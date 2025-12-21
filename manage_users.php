<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ Admin (ต้อง Login และมี status = 2)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}
if (!isset($_SESSION['status']) || $_SESSION['status'] != 2) {
    header("location: dashboard.php");
    exit;
}

$message = '';

// --- จัดการการส่งฟอร์ม (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. เพิ่มผู้ใช้ใหม่
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $name = trim($_POST['name']);
        $surname = trim($_POST['surname']);
        $status = intval($_POST['status']);

        // ตรวจสอบ Username ซ้ำ
        $check = $conn->prepare("SELECT user_id FROM user WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ Username นี้มีผู้ใช้งานแล้ว <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO user (username, pass, name, surname, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $username, $hashed_password, $name, $surname, $status);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success alert-dismissible fade show'>✅ เพิ่มผู้ใช้สำเร็จ <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ เกิดข้อผิดพลาด: " . $stmt->error . " <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
            $stmt->close();
        }
        $check->close();
    }

    // 2. แก้ไขข้อมูลผู้ใช้
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $user_id = intval($_POST['user_id']);
        $name = trim($_POST['name']);
        $surname = trim($_POST['surname']);
        $status = intval($_POST['status']);

        $stmt = $conn->prepare("UPDATE user SET name = ?, surname = ?, status = ? WHERE user_id = ?");
        $stmt->bind_param("ssii", $name, $surname, $status, $user_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show'>✅ แก้ไขข้อมูลสำเร็จ <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ แก้ไขข้อมูลล้มเหลว <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }

    // 3. รีเซ็ตรหัสผ่าน
    if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE user SET pass = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show'>✅ รีเซ็ตรหัสผ่านสำเร็จ <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ รีเซ็ตรหัสผ่านล้มเหลว <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }
}

// --- จัดการการลบ (GET) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    // ป้องกันการลบตัวเอง
    if ($delete_id == $_SESSION['user_id']) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ ไม่สามารถลบบัญชีของตนเองได้ <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show'>✅ ลบผู้ใช้สำเร็จ <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>❌ ลบผู้ใช้ล้มเหลว (อาจมีข้อมูลเอกสารที่เกี่ยวข้อง) <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
        $stmt->close();
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = $conn->query("SELECT * FROM user ORDER BY user_id ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ระบบ - DocScan</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
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
        .btn-icon {
            width: 32px; height: 32px; padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card main-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog me-2 text-primary"></i>จัดการผู้ใช้ระบบ</h4>
                        <div>
                            <button class="btn btn-success btn-sm rounded-pill me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus me-1"></i> เพิ่มผู้ใช้ใหม่
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill shadow-sm">
                                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $message; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>Username</th>
                                        <th>ระดับสิทธิ์</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <?php if($user['status'] == 2): ?>
                                                <span class="badge bg-primary rounded-pill shadow-sm"><i class="fas fa-user-shield me-1"></i>Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill shadow-sm"><i class="fas fa-user me-1"></i>User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-primary btn-sm btn-icon me-1 shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                    data-surname="<?php echo htmlspecialchars($user['surname']); ?>"
                                                    data-status="<?php echo $user['status']; ?>"
                                                    title="แก้ไข">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm btn-icon me-1 text-white shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    title="รีเซ็ตรหัสผ่าน">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="manage_users.php?action=delete&id=<?php echo $user['user_id']; ?>" 
                                               class="btn btn-danger btn-sm btn-icon shadow-sm"
                                               onclick="return confirm('ยืนยันการลบผู้ใช้ <?php echo htmlspecialchars($user['username']); ?>?');"
                                               title="ลบ">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: เพิ่มผู้ใช้ใหม่ -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                        <div class="row mb-3">
                            <div class="col"><label class="form-label">ชื่อ</label><input type="text" name="name" class="form-control" required></div>
                            <div class="col"><label class="form-label">นามสกุล</label><input type="text" name="surname" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">ระดับสิทธิ์</label><select name="status" class="form-select"><option value="1">User (ผู้ใช้ทั่วไป)</option><option value="2">Admin (ผู้ดูแลระบบ)</option></select></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-success">บันทึก</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: แก้ไขผู้ใช้ -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row mb-3">
                            <div class="col"><label class="form-label">ชื่อ</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                            <div class="col"><label class="form-label">นามสกุล</label><input type="text" name="surname" id="edit_surname" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">ระดับสิทธิ์</label><select name="status" id="edit_status" class="form-select"><option value="1">User (ผู้ใช้ทั่วไป)</option><option value="2">Admin (ผู้ดูแลระบบ)</option></select></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: รีเซ็ตรหัสผ่าน -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-key me-2"></i>รีเซ็ตรหัสผ่าน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <p>กำลังรีเซ็ตรหัสผ่านสำหรับผู้ใช้: <strong id="reset_username_display"></strong></p>
                        <div class="mb-3"><label class="form-label">รหัสผ่านใหม่</label><input type="password" name="new_password" class="form-control" required placeholder="กรอกรหัสผ่านใหม่"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-warning text-white">ยืนยันรีเซ็ต</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script สำหรับส่งข้อมูลเข้า Modal แก้ไข
        const editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('edit_user_id').value = button.getAttribute('data-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_surname').value = button.getAttribute('data-surname');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });

        // Script สำหรับส่งข้อมูลเข้า Modal รีเซ็ตรหัสผ่าน
        const resetPasswordModal = document.getElementById('resetPasswordModal');
        resetPasswordModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('reset_user_id').value = button.getAttribute('data-id');
            document.getElementById('reset_username_display').textContent = button.getAttribute('data-username');
        });
    </script>
</body>
</html>