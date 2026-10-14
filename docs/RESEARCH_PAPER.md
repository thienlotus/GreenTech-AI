# Tóm Tắt Đề Tài Nghiên Cứu Khoa Học Sinh Viên (NCKH)

> **Tên đề tài:** Nghiên cứu và Xây dựng Hệ thống Giám sát, Nhận diện và Cảnh báo Sớm Dịch hại Cây trồng Dựa trên Thị giác Máy tính và Trí tuệ Nhân tạo  
> **Sinh viên thực hiện:** Lê Vũ Thiên (`@thienlotus`)  
> **Lĩnh vực:** Thị giác máy tính (Computer Vision), Nông nghiệp công nghệ cao (Smart Agriculture)

---

## 1. Đặt Vấn Đề (Problem Statement)
Trong sản xuất nông nghiệp tại Việt Nam, sâu bệnh là nguyên nhân gây sụt giảm từ 20% đến 40% sản lượng cây trồng mỗi năm. Phương pháp giám sát truyền thống dựa hoàn toàn vào việc nông dân kiểm tra trực quan bằng mắt thường, dẫn đến:
1. Phát hiện muộn khi dịch bệnh đã bùng phát trên diện rộng.
2. Lạm dụng thuốc bảo vệ thực vật hóa học do không ước lượng được chính xác mật độ gây hại.
3. Thiếu dữ liệu lịch sử để dự báo chu kỳ xuất hiện của các loài sinh vật gây hại.

Đề tài đặt mục tiêu xây dựng một giải pháp **tự động hóa toàn diện**, kết hợp mô hình học sâu (Deep Learning) với hệ sinh thái cảnh báo thời gian thực giúp can thiệp từ giai đoạn sớm nhất.

---

## 2. Dữ Liệu & Quy Trình Huấn Luyện (Dataset & Training)

* **Tập dữ liệu:** Hơn 8.500 hình ảnh thực địa thu thập từ các vùng nông nghiệp và các tập dữ liệu mở chuẩn quốc tế (IP102, PlantVillage).
* **5 lớp đối tượng chính:**
  1. Rệp mềm (Aphids)
  2. Sâu ăn lá / Sâu hại (Caterpillars / Larvae)
  3. Bọ cánh cứng hại hoa màu (Beetles)
  4. Châu chấu hại lúa (Grasshoppers)
  5. Ốc sên (Snails)
* **Kỹ thuật tiền xử lý & Data Augmentation:** Mosaic augmentation, Random Affine, HSV Color Jittering để tăng khả năng chống chịu điều kiện ánh sáng tự nhiên thay đổi (nắng gắt, bóng râm, mưa phùn).
* **Lựa chọn mô hình:** **YOLOv11 Medium** được lựa chọn vì tối ưu hóa vượt bậc giữa độ chính xác nhận diện (mAP@0.5) và tốc độ xử lý thời gian thực trên các thiết bị biên (Edge Devices).

---

## 3. Kết Quả Thực Nghiệm (Experimental Benchmarks)

| Chỉ số đánh giá | Giá trị đạt được | Ghi chú |
| :--- | :--- | :--- |
| **mAP@0.5 (Mean Average Precision)** | **88.6%** | Độ chính xác nhận diện trung bình trên tập kiểm thử |
| **Precision (Độ chuẩn xác)** | **91.2%** | Tỷ lệ cảnh báo đúng trên tổng số cảnh báo phát ra |
| **Recall (Độ nhạy)** | **86.4%** | Khả năng phát hiện không bỏ sót cá thể gây hại |
| **Inference Latency (Độ trễ suy luận)** | **36ms / frame** | Đạt chuẩn Real-time (>25 FPS) trên GPU tầm trung |

---

## 4. Giá Trị Thực Tiễn & Hướng Phát Triển
* **Phân quyền người dùng:** Thiết kế 2 giao diện riêng biệt cho **Nông dân chuyên canh** (theo dõi diện tích lớn, thống kê sản lượng) và **Vườn gia đình** (hướng dẫn canh tác hữu cơ, giao diện trực quan).
* **Tích hợp Cloud Inference:** Đóng gói Docker và triển khai thành công mô hình AI lên nền tảng Hugging Face Spaces phục vụ kiểm thử cộng đồng.
