<?php
// 1. เริ่ม Session (ต้องอยู่บรรทัดแรกเสมอ)
session_start();
// 2. เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

$error = ''; // ตัวแปรสำหรับเก็บข้อความแสดงข้อผิดพลาด

// ตรวจสอบว่ามีการส่งข้อมูลฟอร์ม Login มาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 3. ป้องกัน SQL Injection และเตรียมคำสั่ง Query (Prepared Statements)
    // ค้นหาผู้ใช้จาก username
    // *** แก้ไขที่นี่: เพิ่มคอลัมน์ 'status' เข้ามาใน SELECT ***
    $stmt = $conn->prepare("SELECT user_id, username, pass, name, surname, status FROM user WHERE username = ?");
    $stmt->bind_param("s", $username); // 's' คือ string (VARCHAR)
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
         $user = $result->fetch_assoc();
        // 4. ตรวจสอบรหัสผ่านที่ถูก Hash
        if (password_verify($password, $user['pass'])) {
            // รหัสผ่านถูกต้อง!
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name']; // เก็บชื่อเพื่อแสดงผลใน dashboard
            $_SESSION['surname'] = $user['surname']; //
                
            // *** แก้ไขที่นี่: เก็บค่าสถานะ (status) ปัจจุบันที่ดึงมาจากฐานข้อมูล ***
            $_SESSION['status'] = $user['status']; // เก็บสถานะล่าสุด 
            // เปลี่ยนเส้นทางไปหน้า Dashboard
            header("location: dashboard.php");
            exit;
        } else {
            // รหัสผ่านไม่ถูกต้อง
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        // ไม่พบชื่อผู้ใช้
        $error = "ไม่พบชื่อผู้ใช้";
    }
}

// ตรวจสอบว่าผู้ใช้ Login แล้วหรือไม่ (เผื่อมีคนพิมพ์ URL ตรงมา)
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - DocScan System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .card-header {
            background-color: #fff;
            border-bottom: none;
            padding-top: 30px;
            text-align: center;
        }
        .card-header h3 {
            color: #333;
            font-weight: bold;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #764ba2;
        }
        .btn-login {
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            color: white;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-signature me-2"></i>DocScan</h3>
                        <p class="text-muted small mb-0">ระบบสแกนและจัดเก็บเอกสาร</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger text-center" role="alert">
                                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label class="form-label text-muted">ชื่อผู้ใช้</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-secondary"></i></span>
                                    <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Username" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted">รหัสผ่าน</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-secondary"></i></span>
                                    <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Password" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login btn-lg">เข้าสู่ระบบ</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3 bg-light border-0">
                        <div class="small">
                            ยังไม่มีบัญชีใช่ไหม? <a href="register.php" class="text-decoration-none fw-bold" style="color: #764ba2;">สมัครสมาชิกที่นี่</a>
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