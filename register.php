<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิก</title>
    <style>
        /* ใช้ style เดียวกันกับหน้า login เพื่อความสม่ำเสมอ */
        body { font-family: Arial, sans-serif; }
        .login-container { width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="password"] { width: 90%; padding: 8px; margin-top: 5px; }
        .action-buttons { margin-top: 20px; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>สมัครสมาชิกใหม่</h2>
    <p>กรุณากรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งาน</p>
    
    <form action="process_register.php" method="POST">
        <label for="username">ชื่อผู้ใช้ (Username)</label>
        <input type="text" id="username" name="username" required> 

        <label for="password">รหัสผ่าน (Password)</label>
        <input type="password" id="password" name="password" required> 

        <label for="name">ชื่อ (Name)</label>
        <input type="text" id="name" name="name" required> 
        
        <label for="surname">นามสกุล (Surname)</label>
        <input type="text" id="surname" name="surname" required> 
        
        <div class="action-buttons">
            <button type="submit">ยืนยันการสมัครสมาชิก</button>
        </div>
    </form>

    <div class="register-link">
        <a href="index.php">กลับสู่หน้าเข้าสู่ระบบ</a>
    </div>
</div>

</body>
</html>