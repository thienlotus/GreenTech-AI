<?php
$servername = "localhost:3307";
$username = "root";
// Vì bạn vừa reset lại XAMPP từ thư mục backup, mật khẩu mặc định sẽ là rỗng
$password = ""; 
$dbname = "nckh_giam_sat_dich_hai";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối Database thất bại: " . $conn->connect_error);
}

// Bắt buộc set utf8 để lưu tên các loại sâu bệnh bằng tiếng Việt có dấu không bị lỗi font
$conn->set_charset("utf8mb4");
?>