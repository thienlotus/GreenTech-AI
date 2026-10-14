<?php
// Thiết lập trả về định dạng JSON để giao diện web xử lý mượt mà
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Nhận dữ liệu từ Form
    $khu_vuc = $_POST['khu_vuc'] ?? 'Chưa xác định';

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Vui lòng chọn ảnh mẫu vật.']);
        exit;
    }

    // 2. Gói ảnh lại và gửi sang Python AI endpoint
    $python_api_url = get_ai_detect_endpoint();
    $cfile = new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $_FILES['image']['name']);
    $data = ['image' => $cfile];

    $response = false;
    $http_code = 0;
    $curl_error = '';
    $maxAttempts = get_ai_detect_retry_attempts();
    $timeout = get_ai_detect_timeout_seconds();
    $connectTimeout = get_ai_detect_connect_timeout_seconds();
    $relaxSslVerify = should_relax_ai_ssl_verify();

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $python_api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Expect:']);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GreenTech-Process/1.0');

        if (stripos($python_api_url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$relaxSslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $relaxSslVerify ? 0 : 2);
        }

        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = (string)curl_error($ch);
        curl_close($ch);

        if ($response !== false && $http_code >= 200 && $http_code < 300) {
            break;
        }

        $retryableHttp = in_array($http_code, [408, 425, 429, 500, 502, 503, 504, 522, 524], true);
        $retryableNetwork = $response === false;
        if (($retryableHttp || $retryableNetwork) && $attempt < $maxAttempts) {
            $delayMs = min(1800, 600 * $attempt);
            usleep($delayMs * 1000);
        }
    }

    if ($response === false || $http_code < 200 || $http_code >= 300) {
        $detail = trim(($http_code > 0 ? ('HTTP ' . $http_code . ' ') : '') . $curl_error);
        echo json_encode(['success' => false, 'error' => 'AI Server đang ngủ hoặc mất kết nối.' . ($detail !== '' ? (' ' . $detail) : '')]);
        exit;
    }

    $result = json_decode($response, true);

    // 3. AI phân tích xong -> Lưu kết quả vào Database
    if ($result && $result['success']) {
        $db_host = '127.0.0.1';
        $db_user = 'root';
        $db_pass = ''; // Nhập mật khẩu database của bạn vào đây (nếu có)
        $db_name = 'nckh_giam_sat_dich_hai';

        try {
            // Kết nối MySQL bằng PDO
            $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Lưu thông tin tổng quan vào bảng lich_su_quet
            $stmt = $conn->prepare("INSERT INTO lich_su_quet (ten_file_goc, file_ket_qua, thoi_gian, phat_hien_benh, tong_so_luong, khu_vuc) VALUES (?, ?, NOW(), ?, ?, ?)");
            $phat_hien = $result['found_pest'] ? 1 : 0;
            $stmt->execute([
                $result['original_image'],
                $result['result_image'],
                $phat_hien,
                $result['total_insects'],
                $khu_vuc
            ]);
            
            $lich_su_id = $conn->lastInsertId();

            // Lưu chi tiết từng con sâu vào bảng chi_tiet_dich_hai
            if (!empty($result['pest_counts'])) {
                $stmt_detail = $conn->prepare("INSERT INTO chi_tiet_dich_hai (lich_su_id, ten_loai, so_luong) VALUES (?, ?, ?)");
                foreach ($result['pest_counts'] as $ten_loai => $so_luong) {
                    $stmt_detail->execute([$lich_su_id, $ten_loai, $so_luong]);
                }
            }

            // Trả toàn bộ dữ liệu về cho Web hiển thị
            $result['db_saved'] = true;
            $result['khu_vuc'] = $khu_vuc;
            echo json_encode($result);

        } catch(PDOException $e) {
            $result['db_saved'] = false;
            $result['db_error'] = $e->getMessage();
            echo json_encode($result);
        }
    } else {
         echo json_encode(['success' => false, 'error' => 'AI không thể nhận diện được bức ảnh này.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Truy cập không hợp lệ.']);
}
?>
