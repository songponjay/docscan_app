Document Scanning & Management System (PHP/MySQL)
ระบบบริหารจัดการและจัดเก็บเอกสารดิจิทัล พัฒนาด้วยภาษา PHP เพื่อช่วยลดขั้นตอนการทำงานและเพิ่มความสะดวกรวดเร็วในการสืบค้นข้อมูลภายในองค์กร

📂 Database Integration (การจัดการฐานข้อมูล)
โปรเจกต์นี้เน้นการจัดการข้อมูลอย่างเป็นระบบ โดยมีจุดเด่นคือ:

Relational Database Design: ออกแบบฐานข้อมูล MySQL เพื่อรองรับการจัดเก็บ metadata ของเอกสาร (เช่น ประเภท, วันที่, รหัสอ้างอิง)

File Path Storage: ใช้เทคนิคการเก็บไฟล์จริงใน Server และเก็บเฉพาะ Path ลงใน Database เพื่อประสิทธิภาพในการโหลดข้อมูล

SQL Implementation: การใช้คำสั่ง SQL สำหรับการเพิ่ม (Insert), ค้นหา (Search), และแสดงผลข้อมูลเอกสารอย่างแม่นยำ

🚀 Key Features (ฟีเจอร์หลัก)
Digital Scanning: ระบบบันทึกข้อมูลเอกสารจากการสแกนเข้าสู่ระบบ

Advanced Search: ระบบค้นหาเอกสารแบบละเอียด (Filter) ตามเงื่อนไขต่างๆ

Document Management: ระบบจัดการสิทธิ์การเข้าถึงและการแสดงผลไฟล์ดิจิทัล

🛠️ Tech Stack
Language: PHP (Core/PDO)

Database: MySQL / MariaDB

Frontend: HTML, CSS, JavaScript (Bootstrap)


ระบบจัดการเอกสารสแกนพร้อมระบบตั้งชื่อไฟล์อัตโนมัติ

## วิธีการติดตั้ง (Installation)

1. **ฐานข้อมูล**:
   - นำไฟล์ SQL ในโฟลเดอร์ที่เตรียมไว้ไป Import เข้าที่ MySQL (phpMyAdmin)
   - ตรวจสอบให้แน่ใจว่าได้สร้างฐานข้อมูลชื่อ `doc_scan_db`

2. **การตั้งค่าฐานข้อมูล**:
   - คัดลอกไฟล์ `db_connect.example.php` และเปลี่ยนชื่อเป็น `db_connect.php`
   - แก้ไข Username และ Password ให้ตรงกับค่าในเครื่องของคุณ

3. **โฟลเดอร์เก็บไฟล์**:
   - สร้างโฟลเดอร์ชื่อ `uploads/` ไว้ในระดับเดียวกับไฟล์ index
   - ตรวจสอบ Permission ให้สามารถเขียนไฟล์ลงไปได้ (Write Access)

4. **การใช้งาน**:
   - เข้าใช้งานผ่านเว็บเบราว์เซอร์ (เช่น `localhost/docscan_app`)
   - User: `admin` / Password: `123` (ตามที่ตั้งไว้ใน SQL)

## หมายเหตุ
ไฟล์ `db_connect.php` และโฟลเดอร์ `uploads/` จะไม่ถูกนำขึ้น GitHub เพื่อความปลอดภัย
