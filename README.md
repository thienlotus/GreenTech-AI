# 🌿 GreenTech - Hệ Thống Giám Sát Dịch Hại Nông Nghiệp Thông Minh (AI-Powered)

<p align="center">
  <img src="https://img.shields.io/badge/Python-3.9+-blue.svg" alt="Python Version" />
  <img src="https://img.shields.io/badge/PHP-8.0+-purple.svg" alt="PHP Version" />
  <img src="https://img.shields.io/badge/YOLO-v11%20Medium-green.svg" alt="YOLOv11" />
  <img src="https://img.shields.io/badge/Database-MySQL-orange.svg" alt="MySQL" />
  <img src="https://img.shields.io/badge/Container-Docker-2496ED.svg" alt="Docker" />
  <img src="https://img.shields.io/badge/Status-Active-brightgreen.svg" alt="Status" />
</p>

---

## 📌 Giới Thiệu Dự Án

**GreenTech** là nền tảng chuyển đổi số trong nông nghiệp, ứng dụng thị giác máy tính (**Computer Vision**) và **Trí tuệ Nhân tạo (YOLOv11)** nhằm tự động phát hiện, giám sát và cảnh báo mật độ sâu bệnh hại trên cây trồng theo thời gian thực.

Dự án phục vụ nghiên cứu khoa học (NCKH) và định hướng ứng dụng thực tế cho hộ gia đình và nông hộ quy mô vừa và nhỏ, giúp tối ưu hóa việc sử dụng thuốc bảo vệ thực vật, bảo vệ mùa màng và nâng cao năng suất.

---

## 🚀 Các Tính Năng Nổi Bật

1. **Nhận Diện Sâu Bệnh Tự Động (AI Detection):**
   * Mô hình **YOLOv11 Medium Optimized** nhận diện chính xác 5 loài sinh vật/dịch hại phổ biến:
     * 🟢 **Rệp mềm** (*Aphids*)
     * 🟡 **Bọ cánh cứng** (*Bocanhcung*)
     * 🦗 **Châu chấu** (*Chauchau*)
     * 🐌 **Ốc sên** (*Ocsen*)
     * 🐛 **Sâu hại** (*Sauhai*)
2. **Hệ Thống Cảnh Báo 4 Cấp Độ (Traffic Light Alert):**
   * 🟢 **Xanh (Bình thường):** Mật độ an toàn, không phát hiện nguy cơ.
   * 🟡 **Vàng (Cảnh báo nhẹ):** Xuất hiện từ 3 cá thể.
   * 🟠 **Cam (Cảnh báo trung bình):** Xuất hiện từ 5 cá thể, có xu hướng lan rộng.
   * 🔴 **Đỏ (Nguy hiểm):** Mật độ cao (8+ cá thể), cảnh báo khẩn cấp cần can thiệp.
3. **Phân Hệ Dashboard Quản Lý:**
   * **Dashboard Nông Dân:** Thống kê lịch sử dịch bệnh theo lô thửa, khuyến nghị xử lý thuốc BVTV theo tiêu chuẩn.
   * **Dashboard Gia Đình:** Giao diện tối giản, trực quan cho quy mô vườn nhà / ban công sân thượng.
4. **Kiến Trúc Tách Rời (Decoupled Architecture):**
   * **AI Microservice:** Python (Flask + Ultralytics YOLO) đóng gói Docker và deploy trên Cloud (Hugging Face Spaces).
   * **Web Application:** PHP, MySQL và Bootstrap responsive cho trải nghiệm mượt mà.

---

## 🛠️ Kiến Trúc Hệ Thống & Công Nghệ

```
[ Camera / Ảnh chụp ]
        │
        ▼
[ PHP Web Dashboard ] ──(REST API)──► [ Python Flask AI Server (YOLOv11) ]
        │                                           │
        ▼                                           ▼
[ MySQL Database ]                           [ Bounding Boxes & Alerts ]
```

* **Ngôn ngữ & Framework:**
  * **AI & Processing:** Python, Flask, Flask-CORS, OpenCV, NumPy, Ultralytics YOLOv11.
  * **Backend Web:** PHP 8.x, MySQL.
  * **Containerization:** Docker.
* **API Endpoints:**
  * `POST /detect`: Upload ảnh và nhận diện dịch hại kèm bounding box.
  * `POST /live_track`: Nhận diện & theo dõi quỹ đạo thời gian thực từ camera stream.

---

## 📦 Hướng Dẫn Cài Đặt & Chạy Thử (Local)

### 1. Cài đặt Cơ sở Dữ liệu & Web Dashboard (PHP)
1. Cài đặt **XAMPP / Laragon** (hỗ trợ PHP 8.0+ và MySQL).
2. Tạo database mới trong phpMyAdmin tên là: `nckh_giam_sat_dich_hai`.
3. Import file `nckh_giam_sat_dich_hai.sql` vào database vừa tạo.
4. Cấu hình cổng kết nối database tại file `db_connect.php`:
   ```php
   $servername = "localhost:3307"; // hoặc localhost:3306
   $username   = "root";
   $password   = "";
   $dbname     = "nckh_giam_sat_dich_hai";
   ```
5. Đặt source code vào thư mục `htdocs` (XAMPP) hoặc `www` (Laragon) và mở trình duyệt tại: `http://localhost/GreenTech/index.php`.

### 2. Cài đặt AI Backend (Python)
1. Di chuyển vào thư mục dự án và tạo môi trường ảo:
   ```bash
   python -m venv venv
   source venv/bin/activate  # Trên Linux/macOS
   # Hoặc trên Windows:
   venv\Scripts\activate
   ```
2. Cài đặt các thư viện cần thiết:
   ```bash
   pip install -r requirements.txt
   ```
3. Đặt file trọng số mô hình `best.pt` vào cùng thư mục với `api_model.py`.
4. Khởi động AI API Server:
   ```bash
   python api_model.py
   ```
   Server AI sẽ khởi chạy tại cổng `http://localhost:7860`.

### 3. Chạy qua Docker (Tùy chọn)
```bash
docker build -t greentech-ai:latest .
docker run -p 7860:7860 greentech-ai:latest
```

---

## 👨‍💻 Tác Giả & Đóng Góp

* **Lê Vũ Thiên (thienlotus)** - *Lead Developer & AI System Architect*
  * GitHub: [@thienlotus](https://github.com/thienlotus)
  * Vai trò: Thiết kế kiến trúc hệ thống, huấn luyện mô hình YOLOv11, xây dựng REST API và tích hợp Web Dashboard.

---

## 📄 Bản Quyền (License)

Dự án được phát triển phục vụ mục đích nghiên cứu học thuật và phi thương mại.
Mọi hình thức tái sử dụng vui lòng ghi rõ nguồn tác giả.
