<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';
require_once 'pest_translate.php';
require_once 'ai_config.php';

function normalize_role_value(string $role): string
{
    $value = trim($role);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    $value = str_replace(['-', ' '], '_', $value);

    if (in_array($value, ['nong_dan', 'nongdan', 'nông_dân', 'nôngdân'], true)) {
        return 'nong_dan';
    }

    if (in_array($value, ['ho_gia_dinh', 'hogiadinh', 'hộ_gia_đình', 'hộ_gia_dinh'], true)) {
        return 'ho_gia_dinh';
    }

    if (in_array($value, ['khach', 'khách', 'khach_vang_lai', 'khách_vãng_lai'], true)) {
        return 'khach';
    }

    return $value;
}

$isLoggedIn = isset($_SESSION['user_id']);
$isGuestUser = !$isLoggedIn;
$currentUserName = '';
$currentRole = 'khach';
$currentRoleLabel = 'Khách vãng lai';
$dashboardLink = 'index.php';
$avatarPath = trim((string)($_SESSION['avatar_path'] ?? ''));
$avatarUrl = '';
$isHouseholdUser = false;
$householdUserId = 0;
$householdAddress = '';
$householdCoords = null;
$householdScanLabel = '';
$householdScanKey = '';

if ($isLoggedIn) {
    $currentUserName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng'));
    $role = normalize_role_value((string)($_SESSION['role'] ?? 'khach'));
    $currentRole = $role;
    $roleLabelMap = [
        'nong_dan' => 'Nông dân',
        'ho_gia_dinh' => 'Hộ gia đình',
        'khach' => 'Khách vãng lai'
    ];
    $currentRoleLabel = $roleLabelMap[$role] ?? 'Khách vãng lai';
    $_SESSION['role'] = $role;

    if ($role === 'nong_dan') {
        $dashboardLink = 'dashboard_nongdan.php';
    } elseif ($role === 'ho_gia_dinh') {
        $dashboardLink = 'dashboard_giadinh.php';
    }

    if ($role === 'ho_gia_dinh') {
        $isHouseholdUser = true;
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $householdUserId = $userId;
        if ($userId > 0) {
            $householdScanKey = 'HOGD_USER_' . $userId;
            $userStmt = $conn->prepare('SELECT dia_chi_nha FROM users WHERE id = ? LIMIT 1');
            if ($userStmt) {
                $userStmt->bind_param('i', $userId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userRow = $userResult ? $userResult->fetch_assoc() : null;
                $userStmt->close();

                $householdAddress = trim((string)($userRow['dia_chi_nha'] ?? ''));
                $householdScanLabel = $householdAddress !== '' ? $householdAddress : 'Vị trí hộ gia đình';

                if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $householdAddress, $matches)) {
                    $lat = (float)$matches[1];
                    $lng = (float)$matches[2];
                    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        $householdCoords = ['lat' => $lat, 'lng' => $lng];
                    }
                }
            }
        }
    }
}

if ($avatarPath !== '') {
    $avatarUrl = $avatarPath;
} else {
    $avatarUrl = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($currentUserName !== '' ? $currentUserName : 'guest');
}

// =========================================================================
// HỆ CHUYÊN GIA: TỪ ĐIỂN SUY LUẬN BỆNH & CÁCH CHỮA TỪ 19 LOÀI CÔN TRÙNG
// =========================================================================
$expert_system = [
    'aphids' => [
        'benh' => 'Bệnh khảm lá, lùn xoắn lá (Truyền virus). Nấm bồ hóng làm đen lá.',
        'chua' => 'Sử dụng thiên địch (bọ rùa). Phun chế phẩm sinh học nấm xanh/nấm trắng hoặc xà phòng sinh học.'
    ],
    'whitefly' => [
        'benh' => 'Bệnh khảm lá (Mosaic virus), héo rũ, thui chột quả non.',
        'chua' => 'Treo bẫy dính màu vàng. Phun tinh dầu Neem hoặc thuốc trừ sâu sinh học Bt.'
    ],
    'snail' => [
        'benh' => 'Đứt rễ, cụt mầm non. Vết cắn tạo điều kiện cho vi khuẩn thối nhũn xâm nhập.',
        'chua' => 'Rắc vôi bột quanh luống. Dùng bẫy bả chua ngọt hoặc rắc thuốc bả sên lúc chiều tối.'
    ],
    'mites' => [
        'benh' => 'Bệnh cháy lá, vàng đọt, rụng lá non hàng loạt do bị hút kiệt nhựa.',
        'chua' => 'Phun nước áp lực cao lên mặt dưới lá. Sử dụng thuốc đặc trị nhện đỏ hoặc dầu khoáng.'
    ],
    'thrips' => [
        'benh' => 'Bệnh xoăn đọt non, sần sùi và biến dạng quả (da lu lu).',
        'chua' => 'Tỉa cành tạo độ thông thoáng. Phun thuốc trừ bọ trĩ lưu dẫn vào sáng sớm hoặc chiều mát.'
    ],
    'rice leaf roller' => [
        'benh' => 'Bệnh bạc trắng lá, suy giảm quang hợp nghiêm trọng, lép hạt.',
        'chua' => 'Thả ong mắt đỏ (thiên địch). Phun thuốc vi sinh Bacillus thuringiensis (Bt) khi sâu non mới nở.'
    ],
    'rice leaf caterpillar' => [
        'benh' => 'Lá bị ăn khuyết, xơ xác, cây còi cọc không thể quang hợp.',
        'chua' => 'Dọn sạch cỏ bờ. Phun các loại thuốc trừ sâu thảo mộc hoặc chế phẩm sinh học.'
    ],
    'asiatic rice borer' => [
        'benh' => 'Hội chứng "Bông bạc" (lúa trổ trắng) hoặc héo rũ (Deadheart) chồi non.',
        'chua' => 'Nhổ bỏ và tiêu hủy cây bệnh. Phun thuốc nội hấp, lưu dẫn ngay khi bướm rộ.'
    ],
    'yellow rice borer' => [
        'benh' => 'Hội chứng "Bông bạc" (lúa trổ trắng) hoặc héo rũ (Deadheart) chồi non.',
        'chua' => 'Cày ải phơi ruộng diệt nhộng. Rải thuốc hạt lưu dẫn xuống gốc.'
    ],
    'flea beetle' => [
        'benh' => 'Lá thủng lỗ chỗ như rây bột. Sâu non cắn rễ gây thối rễ chết héo.',
        'chua' => 'Luân canh cây trồng. Xử lý đất bằng vôi bột trước khi trồng, phun thuốc nhóm cúc tổng hợp.'
    ],
    'cutworm' => [
        'benh' => 'Cắn đứt ngang thân cây con sát mặt đất, thối gốc do nấm xâm nhập.',
        'chua' => 'Cày bừa kỹ đất. Dùng cám rang trộn thuốc làm bả độc rắc quanh gốc.'
    ],
    'rice gall midge' => [
        'benh' => 'Bệnh "Cọng hành" (ống hành), chồi lúa biến dạng không thể trổ bông.',
        'chua' => 'Nhổ bỏ dảnh lúa bị cọng hành. Tránh gieo cấy quá dày, giảm bón phân đạm.'
    ],
    'paddy stem maggot' => [
        'benh' => 'Thối đọt non, lá non bị héo vàng và dễ dàng rút đứt ra.',
        'chua' => 'Vệ sinh đồng ruộng, diệt cỏ dại. Rải thuốc hột lưu dẫn xuống ruộng.'
    ]
];
// Gộp các loại côn trùng nhai cắn
$nhai_can = ['benh' => 'Lá bị cắn khuyết, đứt rễ. Dễ nhiễm nấm cơ hội qua vết thương hở.', 'chua' => 'Sử dụng bẫy đèn bắt bướm/côn trùng trưởng thành. Phun thuốc trừ sâu sinh học.'];
$expert_system['grasshopper'] = $nhai_can;
$expert_system['caterpillar'] = $nhai_can;
$expert_system['mole cricket'] = $nhai_can;
$expert_system['wireworm'] = $nhai_can;
$expert_system['unknown_bug'] = ['benh' => 'Cần theo dõi thêm diễn biến trên lá.', 'chua' => 'Tiếp tục giám sát mật độ. Áp dụng các biện pháp canh tác an toàn.'];


$result_data = null;
$error_message = '';

$captured_image_data = $_POST['captured_image'] ?? '';
$khu_vuc = $_POST['khu_vuc'] ?? 'Chưa xác định';
if ($isGuestUser) {
    $khu_vuc = 'Khách vãng lai (Demo)';
}
if ($isHouseholdUser && ($khu_vuc === 'Chưa xác định' || $khu_vuc === '')) {
    if ($householdScanKey !== '') {
        $khu_vuc = $householdScanKey;
    } elseif ($householdScanLabel !== '') {
        $khu_vuc = $householdScanLabel;
    }
}

// -------------------------------------------------------------
// 1. XỬ LÝ UPLOAD ẢNH & NHẬN DIỆN AI
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['image']) || !empty($captured_image_data))) {
    $file_tmp = '';
    $file_name = '';
    $file_type = 'image/jpeg';
    $is_temp_capture = false;

    if (!empty($captured_image_data)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $captured_image_data, $matches)) {
            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }

            $raw_data = preg_replace('/^data:image\/\w+;base64,/', '', $captured_image_data);
            $binary_data = base64_decode($raw_data, true);

            if ($binary_data !== false) {
                $temp_file = tempnam(sys_get_temp_dir(), 'cam_');
                if ($temp_file !== false && file_put_contents($temp_file, $binary_data) !== false) {
                    $file_tmp = $temp_file;
                    $file_name = 'camera_capture.' . $extension;
                    $file_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
                    $is_temp_capture = true;
                }
            }
        }
    } else {
        $file_tmp = $_FILES['image']['tmp_name'] ?? '';
        $file_name = $_FILES['image']['name'] ?? '';
        $file_type = $_FILES['image']['type'] ?? 'image/jpeg';
    }

    $is_valid_source = $is_temp_capture ? is_file($file_tmp) : is_uploaded_file($file_tmp);

    if (!empty($file_name) && $is_valid_source) {
        
        // Dùng endpoint cấu hình để dễ chuyển giữa local/prod và rollout model mới.
        $api_url = get_ai_detect_endpoint();
        
        $cfile = new CURLFile($file_tmp, $file_type, $file_name);
        $post_data = array('image' => $cfile);

        $response = false;
        $http_code = 0;
        $curl_error = '';

        $max_attempts = get_ai_detect_retry_attempts();
        $request_timeout = get_ai_detect_timeout_seconds();
        $connect_timeout = get_ai_detect_connect_timeout_seconds();
        $relax_ssl_verify = should_relax_ai_ssl_verify();

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $request_timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Expect:'
            ));
            curl_setopt($ch, CURLOPT_USERAGENT, 'GreenTech-Web/1.0');

            if (stripos($api_url, 'https://') === 0) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$relax_ssl_verify);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $relax_ssl_verify ? 0 : 2);
            }

            $response = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = (string)curl_error($ch);
            curl_close($ch);

            if ($response !== false && $http_code >= 200 && $http_code < 300) {
                break;
            }

            $is_retryable_http = in_array($http_code, array(408, 425, 429, 500, 502, 503, 504, 522, 524), true);
            $is_retryable_network = $response === false;
            if (($is_retryable_http || $is_retryable_network) && $attempt < $max_attempts) {
                $delay_ms = min(1800, 600 * $attempt);
                usleep($delay_ms * 1000);
            }
        }

        if ($response !== false && $http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);

            if (isset($data['success']) && $data['success'] === true) {
                $original_image = $data['original_image'] ?? time() . '_' . $file_name;
                
                // Giải mã Base64 lưu ảnh kết quả
                $result_image = '';
                if (isset($data['image_base64']) && $data['image_base64'] !== "") {
                    $result_image = 'result_' . $original_image;
                    $img_data = base64_decode($data['image_base64']);
                    file_put_contents(__DIR__ . '/results/' . $result_image, $img_data);
                } else {
                    $result_image = $data['result_image'] ?? '';
                }

                $total_insects = (int)($data['total_insects'] ?? 0);
                $pest_counts = is_array($data['pest_counts'] ?? null) ? $data['pest_counts'] : array();

                // Lưu dữ liệu quét vào DB
                $stmt = $conn->prepare('INSERT INTO lich_su_quet (hinh_anh_goc, hinh_anh_ket_qua, khu_vuc) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('sss', $original_image, $result_image, $khu_vuc);
                    $stmt->execute();
                    $lich_su_id = $stmt->insert_id;
                    $stmt->close();

                    foreach ($pest_counts as $ten_sau => $so_luong) {
                        $stmt2 = $conn->prepare('INSERT INTO chi_tiet_dich_hai (lich_su_id, ten_loai_sau, so_luong) VALUES (?, ?, ?)');
                        if ($stmt2) {
                            $count = (int)$so_luong;
                            $stmt2->bind_param('isi', $lich_su_id, $ten_sau, $count);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    }
                }

                $result_data = array(
                    'result_image' => $result_image,
                    'total_insects' => $total_insects,
                    'pest_counts' => $pest_counts,
                    'khu_vuc' => $isHouseholdUser ? ($householdScanLabel !== '' ? $householdScanLabel : 'Vị trí hộ gia đình') : $khu_vuc
                );
            } else {
                $error_message = 'Lỗi từ AI: ' . htmlspecialchars($data['error'] ?? 'Không rõ nguyên nhân', ENT_QUOTES, 'UTF-8');
            }
        } else {
            $details = array();
            if ($http_code > 0) {
                $details[] = 'HTTP ' . $http_code;
            }
            if ($curl_error !== '') {
                $details[] = $curl_error;
            }

            $detail_text = !empty($details) ? (' Chi tiết: ' . implode(' | ', $details)) : '';
            $error_message = 'Không thể kết nối đến AI. Vui lòng kiểm tra endpoint Hugging Face.' . htmlspecialchars($detail_text, ENT_QUOTES, 'UTF-8');
        }

        if ($is_temp_capture && is_file($file_tmp)) {
            unlink($file_tmp);
        }
    } else {
        $error_message = 'Vui lòng chọn ảnh hợp lệ hoặc chụp ảnh bằng camera trước khi phân tích.';
    }
}

// -------------------------------------------------------------
// 2. LẤY DỮ LIỆU BẢN ĐỒ WEBGIS
// -------------------------------------------------------------
$gis_data = [];

if ($isHouseholdUser) {
    $scanKey = $householdScanKey !== '' ? $householdScanKey : 'HOGD_USER_0';
    $legacyLabel = $householdScanLabel !== '' ? $householdScanLabel : $scanKey;

    $stmtGis = $conn->prepare("SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so_luong
                              FROM lich_su_quet ls
                              JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                              WHERE ls.khu_vuc IN (?, ?)
                              GROUP BY ls.khu_vuc, ct.ten_loai_sau");
    $result_gis = null;

    if ($stmtGis) {
        $stmtGis->bind_param('ss', $scanKey, $legacyLabel);
        $stmtGis->execute();
        $result_gis = $stmtGis->get_result();
    }
} else {
    $sql_gis = "SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so_luong
                FROM lich_su_quet ls
                JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                WHERE ls.khu_vuc IN ('Thôn 1 (Vùng Lúa nước)', 'Thôn 2 (Vùng Cải xanh)', 'Thôn 3 (Vùng Cà chua)')
                GROUP BY ls.khu_vuc, ct.ten_loai_sau";
    $result_gis = $conn->query($sql_gis);
}

if ($result_gis && $result_gis->num_rows > 0) {
    while ($row = $result_gis->fetch_assoc()) {
        $kv = $isHouseholdUser ? ($householdScanLabel !== '' ? $householdScanLabel : 'Vị trí hộ gia đình') : $row['khu_vuc'];
        $loai_sau = $row['ten_loai_sau'];
        $sl = (int)$row['tong_so_luong'];

        if (!isset($gis_data[$kv])) {
            $gis_data[$kv] = ['total' => 0, 'details' => []];
        }
        $gis_data[$kv]['total'] += $sl;
        $gis_data[$kv]['details'][$loai_sau] = $sl;
    }
}

if (isset($stmtGis) && $stmtGis) {
    $stmtGis->close();
}

// Màu sắc cảnh báo WebGIS
$region_style = [];
foreach ($gis_data as $kv_name => $data) {
    $count = (int)$data['total'];
    if ($count >= 25) {
        $region_style[$kv_name] = ['fill' => '#ef4444', 'badge' => 'bg-red-500', 'text' => 'text-red-200', 'level' => 'BÁO ĐỘNG ĐỎ'];
    } elseif ($count >= 10) {
        $region_style[$kv_name] = ['fill' => '#f59e0b', 'badge' => 'bg-amber-500', 'text' => 'text-amber-200', 'level' => 'CẢNH BÁO VÀNG'];
    } else {
        $region_style[$kv_name] = ['fill' => '#10b981', 'badge' => 'bg-emerald-500', 'text' => 'text-emerald-200', 'level' => 'AN TOÀN'];
    }
}

$householdPestTotal = 0;
$householdPestLevel = 'AN TOÀN';
if ($isHouseholdUser) {
    foreach ($gis_data as $data) {
        $householdPestTotal += (int)($data['total'] ?? 0);
    }
    if ($householdPestTotal >= 30) {
        $householdPestLevel = 'BÁO ĐỘNG ĐỎ';
    } elseif ($householdPestTotal >= 10) {
        $householdPestLevel = 'CẢNH BÁO VÀNG';
    }
}

$webgisJsData = $isGuestUser ? [] : $gis_data;
$webgisJsStyles = $isGuestUser ? [] : $region_style;

// Lấy Thống kê tổng quan (Dashboard trên cùng)
$aiAnalysisCount = 0;
$aiCountQuery = $conn->query('SELECT COUNT(*) AS total FROM lich_su_quet');
if ($aiCountQuery) {
    $aiCountRow = $aiCountQuery->fetch_assoc();
    $aiAnalysisCount = (int)($aiCountRow['total'] ?? 0);
}

$recognizedSpeciesCount = 0;
$speciesCountQuery = $conn->query('SELECT COUNT(DISTINCT ten_loai_sau) AS total FROM chi_tiet_dich_hai');
if ($speciesCountQuery) {
    $speciesCountRow = $speciesCountQuery->fetch_assoc();
    $recognizedSpeciesCount = (int)($speciesCountRow['total'] ?? 0);
}

$monitoredRegionCount = 0;
$regionBaseSet = [];
$regionQuery = $conn->query('SELECT DISTINCT khu_vuc FROM lich_su_quet WHERE khu_vuc IS NOT NULL AND khu_vuc <> ""');
if ($regionQuery) {
    while ($regionRow = $regionQuery->fetch_assoc()) {
        $regionName = trim((string)($regionRow['khu_vuc'] ?? ''));
        if ($regionName === '') continue;
        if (stripos($regionName, 'HOGD_USER_') === 0 || stripos($regionName, 'Khách vãng lai') !== false) continue;

        if (preg_match('/^(Thôn\s*\d+)/u', $regionName, $matches)) {
            $regionBase = $matches[1];
        } else {
            $regionBase = $regionName;
        }
        $regionBaseSet[$regionBase] = true;
    }
}
$monitoredRegionCount = count($regionBaseSet);

$aiAnalysisDisplay = number_format($aiAnalysisCount);
$recognizedSpeciesDisplay = number_format($recognizedSpeciesCount);
$monitoredRegionDisplay = $monitoredRegionCount;
?>

<!DOCTYPE html>
<html lang="vi" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="greentech-favicon.svg">
    <title>GreenTech | Nhận diện côn trùng bằng AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: '#2E7D32',
                        brandBg: '#F8FAFC',
                        brandText: '#1E293B'
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('https://png.pngtree.com/thumb_back/fh260/background/20240911/pngtree-open-book-with-green-plant-sprouting-in-sunlight-bokeh-background-image_16143810.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: scroll;
            background-repeat: no-repeat;
        }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        @keyframes laserScan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        .animate-laser { animation: laserScan 2s ease-in-out infinite; }

        @keyframes ticker {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .animate-ticker {
            display: inline-block;
            white-space: nowrap;
            animation: ticker 25s linear infinite;
        }

        @keyframes headerSheen {
            0% { transform: translateX(-120%); opacity: 0; }
            20% { opacity: 0.45; }
            100% { transform: translateX(220%); opacity: 0; }
        }

        @keyframes headerPulse {
            0%, 100% { box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06); }
            50% { box-shadow: 0 10px 38px rgba(16, 185, 129, 0.14); }
        }

        .header-shell {
            animation: headerPulse 7s ease-in-out infinite;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.88) 0%, rgba(248, 250, 252, 0.83) 58%, rgba(236, 253, 245, 0.86) 100%);
        }

        .display-heading {
            font-family: 'Be Vietnam Pro', 'Inter', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .header-aura {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .header-aura::after {
            content: '';
            position: absolute;
            top: -40%;
            left: -35%;
            width: 28%;
            height: 200%;
            transform: skewX(-20deg);
            background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(16, 185, 129, 0.24), rgba(255, 255, 255, 0));
            animation: headerSheen 7.5s linear infinite;
        }

        .nav-link { position: relative; }
        .nav-link::after {
            content: ''; position: absolute; left: 0; bottom: -7px; height: 2px; width: 100%;
            border-radius: 999px; background: linear-gradient(90deg, #16a34a, #0ea5e9);
            transform: scaleX(0); transform-origin: left; transition: transform 220ms ease;
        }
        .nav-link:hover::after { transform: scaleX(1); }

        .leaflet-popup.guest-lock-popup .leaflet-popup-content-wrapper {
            background: rgba(255, 255, 255, 0.86); backdrop-filter: blur(8px);
            border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .leaflet-popup.guest-lock-popup .leaflet-popup-tip { background: rgba(255, 255, 255, 0.9); }

        @keyframes liveAlertFlash {
            0% { box-shadow: 0 0 0 rgba(239, 68, 68, 0); }
            50% { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.18); }
            100% { box-shadow: 0 0 0 rgba(239, 68, 68, 0); }
        }

        .live-alert-fresh {
            animation: liveAlertFlash 1.2s ease-out;
        }

        .live-status-idle {
            color: #334155;
            background: #e2e8f0;
        }

        .live-status-running {
            color: #065f46;
            background: #d1fae5;
        }

        .live-status-error {
            color: #991b1b;
            background: #fee2e2;
        }
    </style>
</head>
<body class="bg-brandBg/90 text-brandText antialiased flex flex-col min-h-screen">
    <nav class="header-shell fixed top-0 inset-x-0 z-50 backdrop-blur-xl border-b border-slate-200/50">
        <div class="header-aura"></div>
        <div class="relative max-w-[1400px] mx-auto px-6 h-16 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-brand"></iconify-icon>
                <span class="font-semibold tracking-tighter text-lg text-brand">GREENTECH</span>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="#home" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Trang chủ</a>
                <a href="#scanner" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Quét AI</a>
                <a href="#encyclopedia" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Cẩm nang</a>
                <a href="#webgis" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Bản đồ</a>
                <a href="thong_ke.php" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Thống kê</a>
                <?php if ($isLoggedIn) : ?>
                    <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Dashboard</a>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($isLoggedIn) : ?>
                    <div class="text-right hidden md:block">
                        <div class="text-xs font-medium text-slate-900"><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-[10px] text-slate-500">Vai trò: <?php echo htmlspecialchars($currentRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-slate-200 border-2 border-white shadow-sm overflow-hidden">
                        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" class="w-full h-full object-cover">
                    </div>
                    <a href="profile.php" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Hồ sơ</a>
                    <a href="logout.php" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Đăng xuất</a>
                <?php else : ?>
                    <a href="auth.php?tab=login" class="inline-flex items-center rounded-lg border border-brand/20 bg-white px-3 py-2 text-xs font-semibold text-brand hover:bg-green-50 transition-colors">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="mt-16 bg-red-50/90 backdrop-blur-sm border-b border-red-100 overflow-hidden relative h-8 flex items-center">
        <div class="absolute left-0 bg-red-50 z-10 px-4 h-full flex items-center shadow-[10px_0_15px_-5px_rgba(254,226,226,1)]">
            <span class="text-xs font-semibold text-red-600 tracking-tight flex items-center gap-1">
                <iconify-icon icon="solar:bell-bing-linear" width="14" class="animate-pulse"></iconify-icon> CẢNH BÁO:
            </span>
        </div>
        <div class="w-full pl-28">
            <div class="animate-ticker text-xs text-red-500">
                Theo dõi sâu bệnh trên đồng ruộng và quét hình ảnh thường xuyên để phát hiện sớm nguy cơ lây lan thông qua Hệ Chuyên Gia AI.
            </div>
        </div>
    </div>

    <main class="flex-1">
        <section id="home" class="pt-16 pb-24 px-6 relative max-w-[1200px] mx-auto min-h-[60vh] flex flex-col justify-center">
            <div class="text-center max-w-3xl mx-auto mb-16 bg-white/60 backdrop-blur-md p-8 rounded-3xl border border-white/50 shadow-lg">
                <h1 class="display-heading text-4xl md:text-5xl tracking-tight text-brandText leading-tight mb-6">Bảo Vệ Mùa Màng Bằng <br> Trí Tuệ Nhân Tạo</h1>
                <p class="text-sm md:text-base text-slate-700 mb-10 font-medium leading-relaxed">
                    Hệ thống chẩn đoán hình ảnh tức thì và giám sát côn trùng bằng dữ liệu số. Quyết định nhanh hơn, năng suất tốt hơn.
                </p>
                <a href="#scanner" class="inline-flex bg-brand hover:bg-green-700 text-white text-sm font-medium py-3 px-8 rounded-full shadow-[0_8px_20px_rgba(46,125,50,0.25)] transition-all items-center gap-2 mx-auto">
                    <iconify-icon icon="solar:scanner-linear" width="18"></iconify-icon> Bắt đầu Quét AI
                </a>
            </div>
        </section>

        <section id="global-stats" class="pb-8 px-6">
            <div class="max-w-[1200px] mx-auto">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-emerald-700">Lượt AI phân tích</p>
                        <p class="mt-2 text-2xl font-extrabold text-emerald-800"><?php echo htmlspecialchars($aiAnalysisDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-sky-700">Loài sâu hại nhận diện</p>
                        <p class="mt-2 text-2xl font-extrabold text-sky-800"><?php echo htmlspecialchars($recognizedSpeciesDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-amber-700">Vùng chuyên canh giám sát</p>
                        <p class="mt-2 text-2xl font-extrabold text-amber-800"><?php echo (int)$monitoredRegionDisplay; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section id="scanner" class="py-20 bg-white/90 backdrop-blur-md border-y border-slate-200/50">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="mb-12">
                    <h2 class="display-heading text-2xl tracking-tight text-brandText">Trạm Quét AI & Chẩn Đoán Bệnh</h2>
                    <p class="text-sm text-slate-500 mt-1 font-light">Tải ảnh mẫu vật để AI phát hiện côn trùng hại và phân tích nguy cơ.</p>
                </div>

                <?php if (!empty($error_message)) : ?>
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col lg:flex-row gap-8">
                    <form id="scanForm" action="" method="POST" enctype="multipart/form-data" class="w-full lg:w-[40%] flex flex-col gap-4">
                        <input id="capturedImageInput" type="hidden" name="captured_image" value="">

                        <label for="imageInput" id="dropzone" class="w-full aspect-square border-2 border-dashed border-slate-300 hover:border-brand rounded-2xl bg-white flex flex-col items-center justify-center cursor-pointer transition-colors relative overflow-hidden group">
                            <img id="previewImage" src="" alt="Xem trước ảnh" class="hidden absolute inset-0 w-full h-full object-cover">
                            <div id="dropzoneShade" class="hidden absolute inset-0 bg-black/25"></div>
                            <div class="text-slate-400 group-hover:text-brand transition-colors mb-3">
                                <iconify-icon icon="solar:gallery-add-linear" width="32"></iconify-icon>
                            </div>
                            <span class="text-sm font-medium text-slate-600 text-center px-4" id="fileLabel">Kéo thả hoặc bấm để chọn ảnh</span>
                            <input id="imageInput" type="file" name="image" accept="image/*" class="hidden">

                            <div id="scanOverlay" class="absolute inset-0 bg-white/90 backdrop-blur-sm hidden flex-col items-center justify-center z-10">
                                <div class="absolute left-0 w-full h-[1px] bg-brand shadow-[0_0_15px_#2E7D32] animate-laser"></div>
                                <div class="text-brand text-xs font-semibold tracking-widest uppercase flex flex-col items-center gap-2 z-20">
                                    <iconify-icon icon="solar:cpu-linear" width="24" class="animate-pulse"></iconify-icon> Đang phân tích...
                                </div>
                            </div>
                        </label>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <button id="openCameraBtn" type="button" class="bg-white border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-xl shadow-sm hover:bg-slate-50 flex items-center justify-center gap-2">
                                <iconify-icon icon="solar:camera-bold" width="16"></iconify-icon> Mở camera
                            </button>
                            <button id="openLiveMonitorBtn" type="button" class="bg-white border border-emerald-200 text-emerald-700 text-sm font-semibold py-2.5 rounded-xl shadow-sm hover:bg-emerald-50 flex items-center justify-center gap-2">
                                <iconify-icon icon="solar:video-frame-play-horizontal-bold" width="16"></iconify-icon> Giám sát trực tiếp
                            </button>
                            <a href="thong_ke.php" class="bg-white border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-xl shadow-sm hover:bg-slate-50 flex items-center justify-center gap-2">
                                <iconify-icon icon="solar:chart-square-linear" width="16"></iconify-icon> Xem thống kê
                            </a>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex-1 relative">
                                <?php if ($isHouseholdUser) : ?>
                                    <input type="hidden" name="khu_vuc" value="<?php echo htmlspecialchars($householdScanKey !== '' ? $householdScanKey : ($householdScanLabel !== '' ? $householdScanLabel : 'HOGD_USER_0'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="w-full rounded-xl border border-sky-200 bg-sky-50 py-2.5 px-4 text-sm text-sky-800 font-medium">
                                        📍 Vị trí hộ gia đình: <?php echo htmlspecialchars($householdScanLabel !== '' ? $householdScanLabel : 'Chưa cập nhật vị trí', ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php elseif ($isGuestUser) : ?>
                                    <div class="w-full rounded-xl border border-amber-200 bg-amber-50 py-2.5 px-4 text-sm text-amber-800 font-medium">
                                        👤 Chế độ khách: quét thử nghiệm (không gắn khu vực bản đồ).
                                    </div>
                                <?php else : ?>
                                    <select name="khu_vuc" required class="w-full appearance-none bg-white border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm text-slate-600 font-medium focus:outline-none focus:border-brand shadow-sm cursor-pointer">
                                        <option value="" disabled selected>📍 Chọn khu vực và loại cây trồng...</option>
                                        <option value="Thôn 1 (Vùng Lúa nước)">Thôn 1 (Vùng Lúa nước)</option>
                                        <option value="Thôn 2 (Vùng Cải xanh)">Thôn 2 (Vùng Cải xanh)</option>
                                        <option value="Thôn 3 (Vùng Cà chua)">Thôn 3 (Vùng Cà chua)</option>
                                    </select>
                                    <iconify-icon icon="solar:alt-arrow-down-linear" class="absolute right-3 top-3 text-slate-400 pointer-events-none" width="16"></iconify-icon>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button id="btnAnalyze" type="submit" class="w-full bg-brand hover:bg-green-700 text-white text-base font-semibold py-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 mt-2">
                            <iconify-icon icon="solar:magic-stick-3-linear" width="20"></iconify-icon> Phân tích dữ liệu
                        </button>
                    </form>

                    <div class="w-full lg:w-[60%] bg-white rounded-2xl border border-slate-200/60 p-6 flex flex-col relative overflow-hidden shadow-sm min-h-[420px]">
                        <?php if ($result_data === null) : ?>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-6 z-10 transition-opacity duration-300">
                                <div class="w-24 h-24 bg-slate-50 rounded-full shadow-sm border border-slate-100 flex items-center justify-center text-slate-300 mb-4">
                                    <iconify-icon icon="solar:documents-minimalistic-linear" width="32"></iconify-icon>
                                </div>
                                <h3 class="display-heading text-sm text-slate-900">Đang chờ dữ liệu...</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-xs">Tải lên hình ảnh có dấu hiệu bất thường để AI phát hiện côn trùng và chẩn đoán bệnh.</p>
                            </div>
                        <?php else : ?>
                            <div class="w-full h-full flex flex-col gap-4 overflow-y-auto pr-2">
                                <div class="bg-green-50 p-4 rounded-xl border border-green-100 shadow-sm md:col-span-2 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1">Kết quả chẩn đoán</div>
                                        <h4 class="display-heading text-lg tracking-tight text-green-700">Đã phát hiện <?php echo (int)$result_data['total_insects']; ?> vùng có côn trùng</h4>
                                        <p class="text-xs text-slate-600 mt-1">Khu vực: <strong><?php echo htmlspecialchars($result_data['khu_vuc'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                                    </div>
                                </div>

                                <div class="w-full h-56 bg-slate-900 rounded-xl overflow-hidden relative shadow-inner shrink-0">
                                    <img src="results/<?php echo htmlspecialchars($result_data['result_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Kết quả AI" class="w-full h-full object-contain bg-black/70">
                                </div>

                                <?php if (!empty($result_data['pest_counts'])) : ?>
                                    <div class="space-y-3 mt-2">
                                        <h3 class="display-heading text-sm text-slate-800 border-b border-slate-200 pb-2">Chi tiết Mật độ & Bệnh học</h3>
                                        
                                        <?php foreach ($result_data['pest_counts'] as $ten_sau => $so_luong) : 
                                            $key_ai = strtolower(trim($ten_sau));
                                            $chuyen_gia = $expert_system[$key_ai] ?? $expert_system['unknown_bug'];
                                            $ten_tieng_viet = translate_pest_name_vi($ten_sau);
                                        ?>
                                            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                                                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-red-500"></div>
                                                <div class="flex justify-between items-center mb-3 pl-3">
                                                    <span class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($ten_tieng_viet, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">Phát hiện: <?php echo (int)$so_luong; ?> cá thể</span>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 <?php echo $isGuestUser ? '' : 'md:grid-cols-2'; ?> gap-3 pl-3">
                                                    <?php if (!$isGuestUser) : ?>
                                                        <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
                                                            <div class="text-[10px] font-bold text-orange-600 uppercase mb-1 flex items-center gap-1">
                                                                <iconify-icon icon="solar:danger-triangle-bold"></iconify-icon> Nguy cơ bệnh hại
                                                            </div>
                                                            <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['benh']; ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                                        <div class="text-[10px] font-bold text-emerald-600 uppercase mb-1 flex items-center gap-1">
                                                            <iconify-icon icon="solar:shield-check-bold"></iconify-icon> Khuyến nghị xử lý
                                                        </div>
                                                        <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['chua']; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($isGuestUser) : ?>
                                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                                                🔒 Để xem nguy cơ bùng phát dịch tễ và phác đồ điều trị chuyên sâu, vui lòng <a href="auth.php" class="font-bold underline hover:text-amber-900">Đăng nhập / Đăng ký</a> tài khoản Nông dân.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col">
                                        <p class="text-xs text-slate-500">Môi trường an toàn, chưa phát hiện mối đe dọa từ AI.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="encyclopedia" class="py-20 bg-brandBg/80 backdrop-blur-sm">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="max-w-2xl mx-auto text-center mb-12">
                    <h2 class="display-heading text-2xl tracking-tight text-brandText mb-6">Cẩm Nang Tra Cứu</h2>
                    <p class="text-sm text-slate-600">Tổng hợp thông tin triệu chứng phổ biến để tham khảo nhanh trong quá trình theo dõi.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1590682680695-43b964a3ae17?q=80&w=400&auto=format&fit=crop" alt="Bệnh" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-red-500">Kỹ năng</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">"Đọc vị" sức khỏe cây qua lá</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Nhận biết sớm các triệu chứng khuyết lá, đốm vàng, xoăn ngọn để phát hiện sâu bệnh trước khi bùng phát trên diện rộng.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.pexels.com/photos/2255935/pexels-photo-2255935.jpeg?auto=compress&cs=tinysrgb&w=900" alt="Sâu lúa" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-orange-600">Sâu bệnh lúa</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Sâu cuốn lá nhỏ hại lúa</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Cơn ác mộng của ruộng lúa giai đoạn đẻ nhánh. Hướng dẫn cách tìm ổ bướm và nhận diện vệt bạc trắng trên lá.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1591857177580-dc82b9ac4e1e?q=80&w=400&auto=format&fit=crop" alt="Bọ nhảy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-orange-600">Sâu bệnh rau</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Bọ nhảy bắp cải: Nhỏ có võ</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Kẻ thù số 1 của vùng chuyên canh rau họ thập tự. Cách xử lý đất và luân canh cắt đứt vòng đời bọ nhảy hiệu quả.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.pexels.com/photos/772807/pexels-photo-772807.jpeg?auto=compress&cs=tinysrgb&w=900" alt="Sinh học" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-emerald-600">Sinh học</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Chế phẩm nấm diệt sâu an toàn</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Sử dụng nấm xanh (Metarhizium) và nấm trắng (Beauveria) thay thế thuốc hóa học, bảo vệ thiên địch và môi trường.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?q=80&w=400&auto=format&fit=crop" alt="AI Scan" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-sky-600">Hướng dẫn App</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Mẹo chụp ảnh để AI chuẩn 99%</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Hướng dẫn lấy nét, căn sáng và chọn góc chụp mẫu vật côn trùng giúp hệ thống GreenTech phân tích tốt nhất.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="webgis" class="w-full h-[80vh] relative overflow-hidden bg-slate-900 border-t border-slate-700">
            <div id="main-webgis-map" class="absolute inset-0 w-full h-full z-0"></div>

            <?php if ($isGuestUser) : ?>
                <div class="absolute inset-0 z-[1000] bg-slate-900/50 backdrop-blur-md flex flex-col items-center justify-center p-4">
                    <div class="w-full max-w-lg rounded-2xl border border-white/20 bg-slate-900/80 p-6 text-center shadow-2xl">
                        <div class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-400/20 text-amber-300 text-2xl">⚠️</div>
                        <h3 class="display-heading text-lg text-white">⚠️ Phát hiện khu vực có nguy cơ dịch hại!</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-200">Hệ thống AI đã quét được các vùng nguy cơ cao. Bản đồ dịch tễ thời gian thực là tính năng chuyên sâu. Vui lòng Đăng nhập hoặc Tạo tài khoản để xem tọa độ chi tiết.</p>
                        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                            <a href="auth.php?tab=login" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Đăng nhập</a>
                            <a href="auth.php?tab=register" class="rounded-lg border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">Đăng ký</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="absolute top-6 left-6 w-72 bg-slate-900/85 backdrop-blur-md border border-white/20 rounded-2xl shadow-2xl p-5 z-10 flex flex-col gap-4 pointer-events-auto">
                <div class="flex items-center gap-2">
                    <iconify-icon icon="solar:map-bold-duotone" width="20" class="text-brand"></iconify-icon>
                    <h3 class="display-heading text-sm text-white tracking-wide"><?php echo $isHouseholdUser ? 'Bản Đồ Vị Trí Nhà' : 'Trung Tâm Chỉ Huy'; ?></h3>
                </div>
                <?php if ($isHouseholdUser) : ?>
                    <p class="text-xs text-slate-300">Hệ thống đang hiển thị vị trí hộ gia đình để theo dõi cảnh báo xung quanh khu vực sinh sống.</p>
                <?php else : ?>
                    <p class="text-xs text-slate-300">Bản đồ mô phỏng ổ dịch tại khu vực ngoại thành Hà Nội với sự kết hợp phân tích của Hệ Chuyên Gia AI.</p>
                <?php endif; ?>
                <a href="thong_ke.php" class="inline-flex justify-center bg-brand text-white text-xs font-medium px-3 py-2 rounded-lg hover:bg-green-700 transition-colors">Mở bảng thống kê chi tiết</a>
            </div>

            <div class="absolute top-6 right-6 w-64 bg-slate-900/85 backdrop-blur-md border border-slate-600/60 rounded-2xl p-4 z-10 space-y-3 pointer-events-auto">
                <?php if ($isGuestUser) : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">🔒 Tổng quan mật độ</div>
                    <div class="rounded-xl border border-amber-300/30 bg-amber-400/10 p-3 text-xs text-amber-100">
                        Dữ liệu chi tiết đang bị khóa. Vui lòng đăng nhập để xem.
                    </div>
                <?php elseif ($isHouseholdUser) : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">Thông tin hộ gia đình</div>
                    <div class="text-xs text-slate-100 leading-relaxed">
                        <?php echo htmlspecialchars($householdAddress !== '' ? $householdAddress : 'Bạn chưa cập nhật địa chỉ hoặc tọa độ nhà.', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if ($householdCoords !== null) : ?>
                        <div class="text-[11px] text-slate-300">Tọa độ: <?php echo number_format((float)$householdCoords['lat'], 6); ?>, <?php echo number_format((float)$householdCoords['lng'], 6); ?></div>
                    <?php endif; ?>
                    <div class="rounded-lg border border-slate-600 bg-slate-800/60 p-2">
                        <div class="text-[11px] text-slate-300">Mật độ sâu hại hộ gia đình</div>
                        <div class="mt-1 text-sm font-semibold text-white"><?php echo (int)$householdPestTotal; ?> cá thể - <?php echo htmlspecialchars($householdPestLevel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php else : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">Tổng quan Mật độ</div>
                    <?php foreach ($gis_data as $kv_name => $data) : ?>
                        <div class="flex items-center justify-between text-xs text-slate-200">
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm <?php echo $region_style[$kv_name]['badge'] ?? 'bg-emerald-500'; ?>"></span>
                                <?php echo htmlspecialchars($kv_name, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <strong><?php echo (int)$data['total']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="cameraModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/70"></div>
        <div class="relative h-full w-full flex items-center justify-center p-4">
            <div class="w-full max-w-xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="display-heading text-sm text-slate-800">Chụp ảnh trực tiếp</h3>
                    <button id="closeCameraBtn" type="button" class="text-slate-500 hover:text-slate-700">
                        <iconify-icon icon="solar:close-circle-linear" width="22"></iconify-icon>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <div id="cameraFrame" class="w-full bg-slate-900 rounded-xl overflow-hidden relative" style="aspect-ratio: 4 / 3;">
                        <video id="cameraVideo" class="w-full h-full object-contain bg-black" autoplay playsinline></video>
                        <canvas id="cameraCanvas" class="hidden w-full h-full object-contain bg-black"></canvas>
                    </div>
                    <p class="text-xs text-slate-500">Trên điện thoại, vui lòng cho phép quyền camera để chụp ảnh mẫu vật.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <button id="captureBtn" type="button" class="bg-brand text-white text-sm py-2 rounded-lg hover:bg-green-700">Chụp ảnh</button>
                        <button id="retakeBtn" type="button" class="bg-slate-100 text-slate-700 text-sm py-2 rounded-lg hover:bg-slate-200">Chụp lại</button>
                        <button id="switchCameraBtn" type="button" class="bg-sky-600 text-white text-sm py-2 rounded-lg hover:bg-sky-700">Đổi camera</button>
                        <button id="usePhotoBtn" type="button" class="bg-emerald-600 text-white text-sm py-2 rounded-lg hover:bg-emerald-700">Dùng ảnh này</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="liveMonitorModal" class="fixed inset-0 z-[70] hidden">
        <div class="absolute inset-0 bg-slate-950/92"></div>
        <div class="relative h-full w-full overflow-y-auto">
            <div class="mx-auto flex min-h-full w-full max-w-6xl items-start justify-center p-0 sm:p-4">
                <div class="w-full min-h-screen overflow-hidden border border-slate-700 bg-slate-950 text-slate-100 shadow-2xl sm:min-h-0 sm:rounded-2xl">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-700 px-4 py-3 sm:px-5">
                        <div>
                            <h3 class="display-heading text-sm text-slate-100">Giám sát chuyển động sâu hại trực tiếp</h3>
                            <p class="mt-1 text-xs text-slate-300">Ưu tiên màn hình camera rõ ràng. Bảng phân tích chi tiết nằm ở phần thu gọn bên dưới.</p>
                        </div>
                        <button id="closeLiveMonitorBtn" type="button" class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-800">
                            Đóng
                        </button>
                    </div>

                    <div class="space-y-3 p-3 sm:p-4">
                        <section class="space-y-3">
                            <div id="liveVideoFrame" class="relative w-full overflow-hidden rounded-xl bg-black ring-1 ring-white/10" style="height: min(74svh, 82vh);">
                                <video id="liveVideo" class="h-full w-full object-cover" autoplay playsinline></video>
                                <canvas id="liveOverlayCanvas" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button id="startLiveMonitorBtn" type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Bắt đầu giám sát</button>
                                <button id="stopLiveMonitorBtn" type="button" class="rounded-lg bg-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300" disabled>Dừng giám sát</button>
                                <button id="exportLiveReportBtn" type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Xuất báo cáo</button>
                                <button id="viewHistoryBtn" type="button" class="rounded-lg bg-purple-600 px-3 py-2 text-sm font-semibold text-white hover:bg-purple-700">Xem lịch sử</button>
                                <button id="switchLiveCameraBtn" type="button" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Đổi camera</button>
                                <button id="toggleLiveFitBtn" type="button" class="rounded-lg border border-slate-500 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800">Khung đầy: Bật</button>
                                <button id="liveFullscreenBtn" type="button" class="rounded-lg border border-slate-500 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800">Toàn màn hình</button>
                                <button id="openCameraFromLiveBtn" type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-700 border border-slate-200 hover:bg-slate-50">Chụp ảnh phân tích</button>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                                <label class="text-xs text-slate-300">
                                    <span class="mb-1 block font-semibold text-slate-100">Chu kỳ phân tích realtime</span>
                                    <select id="liveIntervalSelect" class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                                        <option value="1000">Nhanh (1.0 giây)</option>
                                        <option value="1500" selected>Chuẩn (1.5 giây)</option>
                                        <option value="2200">Tiết kiệm (2.2 giây)</option>
                                    </select>
                                </label>
                                <div class="rounded-xl border border-slate-700 bg-slate-900/75 px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Trạng thái live</div>
                                    <div id="liveStatusPill" class="live-status-idle mt-1 inline-flex rounded-full px-3 py-1 text-[11px] font-bold">Sẵn sàng khởi động giám sát</div>
                                </div>
                            </div>
                        </section>

                        <details class="rounded-xl border border-slate-700 bg-slate-900/70 p-3 text-slate-100">
                            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wider text-slate-300">Mở bảng phân tích chi tiết</summary>
                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                <div class="rounded-xl border border-emerald-300/30 bg-emerald-400/10 p-3">
                                    <div class="text-[11px] uppercase tracking-wider text-emerald-200">Đánh giá lây lan realtime</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-sm text-slate-100">
                                        <div>
                                            <div class="text-[11px] text-slate-400">Mức ảnh hưởng</div>
                                            <div id="liveImpactLevel" class="font-bold text-emerald-300">Nhẹ</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Điểm ảnh hưởng</div>
                                            <div id="liveImpactScore" class="font-bold text-slate-100">0/100</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Rủi ro</div>
                                            <div id="liveRiskLevel" class="font-bold text-slate-100">Thấp</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Vận tốc TB</div>
                                            <div id="liveAvgSpeed" class="font-bold text-slate-100">0 px/s</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Đang hiện trên live</div>
                                            <div id="liveVisibleCount" class="font-bold text-slate-100">0 cá thể</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Hướng lan chính</div>
                                            <div id="liveDominantDirection" class="font-bold text-slate-100">Không rõ</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] text-slate-400">Mức lan rộng</div>
                                            <div id="liveSpreadLevel" class="font-bold text-slate-100">Ổn định</div>
                                        </div>
                                    </div>
                                    <p id="liveSummaryNote" class="mt-2 text-xs text-emerald-100">Chưa có dữ liệu theo dõi, hãy bấm Bắt đầu giám sát.</p>
                                </div>

                                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Loài đang xuất hiện</div>
                                    <div id="liveSpeciesStats" class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span class="rounded-full bg-slate-700 px-2 py-1 font-semibold text-slate-200">Chưa ghi nhận</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Cá thể đang theo dõi</div>
                                        <span class="text-[10px] text-slate-500">Realtime</span>
                                    </div>
                                    <div class="mt-2 max-h-44 overflow-auto rounded-lg border border-slate-700">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-slate-800 text-slate-300">
                                                <tr>
                                                    <th class="px-2 py-1 text-left">Loài</th>
                                                    <th class="px-2 py-1 text-left">Hướng di chuyển</th>
                                                    <th class="px-2 py-1 text-right">Tốc độ (px/s)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="liveTrackTableBody" class="divide-y divide-slate-800">
                                                <tr>
                                                    <td colspan="3" class="px-2 py-3 text-center text-slate-400">Chưa có dữ liệu</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Lịch sử đổi hướng lây lan</div>
                                    <ul id="liveSpreadEventList" class="mt-2 max-h-28 space-y-2 overflow-auto text-xs text-slate-300">
                                        <li class="rounded-lg bg-slate-800 px-2 py-1.5">Chưa ghi nhận đổi hướng di chuyển.</li>
                                    </ul>
                                </div>

                                <div class="rounded-xl border border-rose-300/40 bg-rose-500/10 p-3 lg:col-span-2">
                                    <div class="text-[11px] uppercase tracking-wider text-rose-200">Thông báo trực tiếp khi đang quay</div>
                                    <ul id="liveAlertList" class="mt-2 max-h-32 space-y-2 overflow-auto text-xs text-rose-100">
                                        <li class="rounded-lg border border-rose-200/40 bg-slate-900/70 px-2 py-1.5">Sẵn sàng nhận cảnh báo mới.</li>
                                    </ul>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white/90 backdrop-blur-md border-t border-slate-200 py-8 text-center">
        <p class="text-[11px] text-slate-400 font-medium tracking-wide">© 2026 GreenTech. Hệ thống nhận diện côn trùng bằng AI.</p>
    </footer>

    <script>
        const imageInput = document.getElementById('imageInput');
        const fileLabel = document.getElementById('fileLabel');
        const dropzone = document.getElementById('dropzone');
        const previewImage = document.getElementById('previewImage');
        const dropzoneShade = document.getElementById('dropzoneShade');
        const capturedImageInput = document.getElementById('capturedImageInput');
        const scanForm = document.getElementById('scanForm');
        const scanOverlay = document.getElementById('scanOverlay');
        const btnAnalyze = document.getElementById('btnAnalyze');

        const openCameraBtn = document.getElementById('openCameraBtn');
        const cameraModal = document.getElementById('cameraModal');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const switchCameraBtn = document.getElementById('switchCameraBtn');
        const usePhotoBtn = document.getElementById('usePhotoBtn');
        const cameraVideo = document.getElementById('cameraVideo');
        const cameraCanvas = document.getElementById('cameraCanvas');
        const cameraFrame = document.getElementById('cameraFrame');

        const openLiveMonitorBtn = document.getElementById('openLiveMonitorBtn');
        const liveMonitorModal = document.getElementById('liveMonitorModal');
        const closeLiveMonitorBtn = document.getElementById('closeLiveMonitorBtn');
        const liveVideoFrame = document.getElementById('liveVideoFrame');
        const liveVideo = document.getElementById('liveVideo');
        const liveOverlayCanvas = document.getElementById('liveOverlayCanvas');
        const startLiveMonitorBtn = document.getElementById('startLiveMonitorBtn');
        const stopLiveMonitorBtn = document.getElementById('stopLiveMonitorBtn');
        const exportLiveReportBtn = document.getElementById('exportLiveReportBtn');
        const viewHistoryBtn = document.getElementById('viewHistoryBtn');
        const switchLiveCameraBtn = document.getElementById('switchLiveCameraBtn');
        const toggleLiveFitBtn = document.getElementById('toggleLiveFitBtn');
        const liveFullscreenBtn = document.getElementById('liveFullscreenBtn');
        const openCameraFromLiveBtn = document.getElementById('openCameraFromLiveBtn');
        const liveIntervalSelect = document.getElementById('liveIntervalSelect');
        const liveStatusPill = document.getElementById('liveStatusPill');
        const liveImpactLevel = document.getElementById('liveImpactLevel');
        const liveImpactScore = document.getElementById('liveImpactScore');
        const liveRiskLevel = document.getElementById('liveRiskLevel');
        const liveAvgSpeed = document.getElementById('liveAvgSpeed');
        const liveVisibleCount = document.getElementById('liveVisibleCount');
        const liveDominantDirection = document.getElementById('liveDominantDirection');
        const liveSpreadLevel = document.getElementById('liveSpreadLevel');
        const liveSummaryNote = document.getElementById('liveSummaryNote');
        const liveSpeciesStats = document.getElementById('liveSpeciesStats');
        const liveTrackTableBody = document.getElementById('liveTrackTableBody');
        const liveSpreadEventList = document.getElementById('liveSpreadEventList');
        const liveAlertList = document.getElementById('liveAlertList');

        const liveCaptureCanvas = document.createElement('canvas');
        const liveApiEndpoint = 'process_live.php';
        const LIVE_MAX_FRAME_WIDTH = 640;
        const LIVE_JPEG_QUALITY = 0.55;
        const LIVE_REQUEST_TIMEOUT_MS = 15000;
        const LIVE_DYNAMIC_MIN_INTERVAL_MS = 900;
        const LIVE_DYNAMIC_MAX_INTERVAL_MS = 3500;
        const LIVE_ERROR_BACKOFF_MS = 700;
        const CAPTURE_JPEG_QUALITY = 0.85;

        const pestNameMap = {
            aphids: 'Rệp mềm',
            bocanhcung: 'Bọ cánh cứng',
            chauchau: 'Châu chấu',
            ocsen: 'Ốc sên',
            sauhai: 'Sâu hại'
        };

        let cameraStream = null;
        let capturedDataUrl = '';
        let currentFacingMode = 'environment';
        let isSwitchingCamera = false;
        let previewObjectUrl = null;

        let liveMonitorStream = null;
        let liveFacingMode = 'environment';
        let liveSwitchingCamera = false;
        let liveRequestInFlight = false;
        let liveMonitoringTimer = null;
        let liveRequestController = null;
        let liveIsRunning = false;
        let liveSessionId = '';
        let liveNextDelayMs = 1500;
        let liveConsecutiveTimeouts = 0;
        let liveAudioCtx = null;
        let liveVideoObjectFitMode = 'cover';
        let liveLastFrameSize = null;
        let liveLastDetections = [];
        let liveLastHeatmap = [];
        let liveMovementSummary = null;
        let liveSessionHistory = [];  // Luu lich su sessions

        const toDisplayPestName = (name) => {
            const normalized = String(name || '').trim().toLowerCase();
            return pestNameMap[normalized] || name || 'Côn trùng';
        };

        const escapeHtml = (value) => {
            const text = String(value ?? '');
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const getCameraFrameSize = () => {
            let width = cameraVideo.videoWidth || 0;
            let height = cameraVideo.videoHeight || 0;

            if ((!width || !height) && cameraStream) {
                const track = cameraStream.getVideoTracks()[0];
                const settings = track ? track.getSettings() : null;
                width = Number(settings && settings.width ? settings.width : 0);
                height = Number(settings && settings.height ? settings.height : 0);
            }

            return { width, height };
        };

        const updateCameraFrameRatio = () => {
            if (!cameraFrame) return;
            const size = getCameraFrameSize();
            if (size.width > 0 && size.height > 0) {
                cameraFrame.style.aspectRatio = `${size.width} / ${size.height}`;
            } else {
                cameraFrame.style.aspectRatio = '4 / 3';
            }
        };

        const setPreviewDataUrl = (dataUrl, label) => {
            previewImage.src = dataUrl;
            previewImage.classList.remove('hidden');
            dropzoneShade.classList.remove('hidden');
            fileLabel.textContent = label;
        };

        const setPreview = (file) => {
            if (!file || !file.type.startsWith('image/')) {
                if (previewObjectUrl) {
                    URL.revokeObjectURL(previewObjectUrl);
                    previewObjectUrl = null;
                }
                previewImage.src = '';
                previewImage.classList.add('hidden');
                dropzoneShade.classList.add('hidden');
                fileLabel.textContent = 'Kéo thả hoặc bấm để chọn ảnh';
                capturedImageInput.value = '';
                return;
            }

            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
            }
            previewObjectUrl = URL.createObjectURL(file);
            previewImage.src = previewObjectUrl;
            previewImage.classList.remove('hidden');
            dropzoneShade.classList.remove('hidden');
            fileLabel.textContent = file.name;
            capturedImageInput.value = '';
        };

        const stopCamera = () => {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }
        };

        const stopLiveCamera = () => {
            if (liveMonitorStream) {
                liveMonitorStream.getTracks().forEach((track) => track.stop());
                liveMonitorStream = null;
            }
            if (liveVideo) {
                liveVideo.srcObject = null;
            }
        };

        const startCamera = async (facingMode) => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Trình duyệt chưa hỗ trợ mở camera.');
                return false;
            }
            stopCamera();
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: facingMode } }, audio: false
                });
                cameraVideo.srcObject = cameraStream;
                cameraVideo.onloadedmetadata = () => {
                    updateCameraFrameRatio();
                };
                cameraVideo.classList.remove('hidden');
                cameraCanvas.classList.add('hidden');
                capturedDataUrl = '';
                currentFacingMode = facingMode;
                return true;
            } catch (error) {
                if (facingMode !== 'environment') return false;
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    cameraVideo.srcObject = cameraStream;
                    cameraVideo.onloadedmetadata = () => {
                        updateCameraFrameRatio();
                    };
                    cameraVideo.classList.remove('hidden');
                    cameraCanvas.classList.add('hidden');
                    capturedDataUrl = '';
                    return true;
                } catch (fallbackError) {
                    return false;
                }
            }
        };

        const startLiveCamera = async (facingMode) => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return false;
            }

            stopLiveCamera();
            try {
                liveMonitorStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: facingMode } },
                    audio: false
                });
                liveVideo.srcObject = liveMonitorStream;
                liveVideo.onloadedmetadata = () => {
                    applyLiveVideoFitMode();
                    resizeLiveOverlayCanvas();
                    drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);
                };
                liveFacingMode = facingMode;
                return true;
            } catch (error) {
                if (facingMode !== 'environment') {
                    return false;
                }
                try {
                    liveMonitorStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    liveVideo.srcObject = liveMonitorStream;
                    liveVideo.onloadedmetadata = () => {
                        applyLiveVideoFitMode();
                        resizeLiveOverlayCanvas();
                        drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);
                    };
                    return true;
                } catch (fallbackError) {
                    return false;
                }
            }
        };

        const updateLiveStatusPill = (text, state = 'idle') => {
            if (!liveStatusPill) return;
            liveStatusPill.textContent = text;
            liveStatusPill.classList.remove('live-status-idle', 'live-status-running', 'live-status-error');
            if (state === 'running') {
                liveStatusPill.classList.add('live-status-running');
            } else if (state === 'error') {
                liveStatusPill.classList.add('live-status-error');
            } else {
                liveStatusPill.classList.add('live-status-idle');
            }
        };

        const applyLiveVideoFitMode = () => {
            if (!liveVideo) return;
            const useCover = liveVideoObjectFitMode !== 'contain';
            liveVideo.classList.toggle('object-cover', useCover);
            liveVideo.classList.toggle('object-contain', !useCover);

            if (toggleLiveFitBtn) {
                toggleLiveFitBtn.textContent = useCover ? 'Khung đầy: Bật' : 'Khung đầy: Tắt';
            }
        };

        const toggleLiveVideoFitMode = () => {
            liveVideoObjectFitMode = liveVideoObjectFitMode === 'cover' ? 'contain' : 'cover';
            applyLiveVideoFitMode();
            drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);
        };

        const updateFullscreenButtonLabel = () => {
            if (!liveFullscreenBtn) return;
            const isFullscreen = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
            liveFullscreenBtn.textContent = isFullscreen ? 'Thoát toàn màn hình' : 'Toàn màn hình';
        };

        const toggleLiveFullscreen = async () => {
            const activeFullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
            if (activeFullscreenElement) {
                try {
                    if (document.exitFullscreen) {
                        await document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                } catch (error) {
                    appendLiveAlert('Không thể thoát toàn màn hình.', 'warn');
                }
                return;
            }

            const targetElement = liveVideoFrame || liveVideo;
            if (!targetElement) {
                return;
            }

            try {
                if (targetElement.requestFullscreen) {
                    await targetElement.requestFullscreen();
                } else if (targetElement.webkitRequestFullscreen) {
                    targetElement.webkitRequestFullscreen();
                } else if (liveVideo && typeof liveVideo.webkitEnterFullscreen === 'function') {
                    liveVideo.webkitEnterFullscreen();
                } else {
                    appendLiveAlert('Trình duyệt chưa hỗ trợ toàn màn hình.', 'warn');
                }
            } catch (error) {
                appendLiveAlert('Không thể chuyển sang toàn màn hình.', 'warn');
            }
        };

        const setLiveButtonsState = (isRunning) => {
            if (startLiveMonitorBtn) {
                startLiveMonitorBtn.disabled = isRunning;
                startLiveMonitorBtn.classList.toggle('opacity-60', isRunning);
                startLiveMonitorBtn.classList.toggle('cursor-not-allowed', isRunning);
            }
            if (stopLiveMonitorBtn) {
                stopLiveMonitorBtn.disabled = !isRunning;
                stopLiveMonitorBtn.classList.toggle('opacity-60', !isRunning);
                stopLiveMonitorBtn.classList.toggle('cursor-not-allowed', !isRunning);
            }
            if (liveIntervalSelect) {
                liveIntervalSelect.disabled = isRunning;
            }
        };

        const getSelectedLiveIntervalMs = () => {
            const intervalMs = Number.parseInt((liveIntervalSelect && liveIntervalSelect.value) || '1500', 10);
            if (!Number.isFinite(intervalMs)) {
                return 1500;
            }

            return Math.max(LIVE_DYNAMIC_MIN_INTERVAL_MS, Math.min(intervalMs, LIVE_DYNAMIC_MAX_INTERVAL_MS));
        };

        const scheduleNextLiveCycle = (requestedDelayMs) => {
            if (!liveIsRunning) {
                return;
            }

            const baseDelay = getSelectedLiveIntervalMs();
            const rawDelay = Number.isFinite(requestedDelayMs) ? Math.max(baseDelay, requestedDelayMs) : baseDelay;
            const safeDelay = Math.max(LIVE_DYNAMIC_MIN_INTERVAL_MS, Math.min(rawDelay, LIVE_DYNAMIC_MAX_INTERVAL_MS));

            if (liveMonitoringTimer) {
                clearTimeout(liveMonitoringTimer);
            }

            liveMonitoringTimer = setTimeout(() => {
                runLiveTrackingCycle();
            }, safeDelay);
        };

        const appendLiveAlert = (message, type = 'info') => {
            if (!liveAlertList) return;

            const toneClass = type === 'error'
                ? 'border-red-300/50 bg-red-500/20 text-red-100'
                : (type === 'warn' ? 'border-amber-300/50 bg-amber-500/20 text-amber-100' : 'border-rose-300/40 bg-slate-900/70 text-rose-100');

            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');

            const item = document.createElement('li');
            item.className = `live-alert-fresh rounded-lg border px-2 py-1.5 ${toneClass}`;
            item.textContent = `[${hh}:${mm}:${ss}] ${message}`;
            liveAlertList.prepend(item);

            while (liveAlertList.children.length > 14) {
                liveAlertList.removeChild(liveAlertList.lastChild);
            }
        };

        const tryPlayLiveBeep = () => {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!liveAudioCtx) {
                    liveAudioCtx = new AudioCtx();
                }
                const osc = liveAudioCtx.createOscillator();
                const gain = liveAudioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(920, liveAudioCtx.currentTime);
                gain.gain.setValueAtTime(0.0001, liveAudioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.12, liveAudioCtx.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, liveAudioCtx.currentTime + 0.16);
                osc.connect(gain);
                gain.connect(liveAudioCtx.destination);
                osc.start();
                osc.stop(liveAudioCtx.currentTime + 0.16);
            } catch (error) {
                // Không chặn luồng realtime nếu thiết bị không phát âm báo.
            }
        };

        const resizeLiveOverlayCanvas = () => {
            if (!liveOverlayCanvas || !liveVideo) return;
            const rect = liveVideo.getBoundingClientRect();
            const targetWidth = Math.max(1, Math.floor(rect.width));
            const targetHeight = Math.max(1, Math.floor(rect.height));
            if (liveOverlayCanvas.width !== targetWidth || liveOverlayCanvas.height !== targetHeight) {
                liveOverlayCanvas.width = targetWidth;
                liveOverlayCanvas.height = targetHeight;
            }
        };

        const drawLiveOverlay = (frameSize, detections, heatmapData) => {
            if (!liveOverlayCanvas) return;
            resizeLiveOverlayCanvas();

            const ctx = liveOverlayCanvas.getContext('2d');
            if (!ctx) return;

            ctx.clearRect(0, 0, liveOverlayCanvas.width, liveOverlayCanvas.height);

            if (!Array.isArray(detections) || detections.length === 0) {
                return;
            }

            const frameWidth = frameSize && Number(frameSize.width) > 0 ? Number(frameSize.width) : 1;
            const frameHeight = frameSize && Number(frameSize.height) > 0 ? Number(frameSize.height) : 1;
            const canvasWidth = liveOverlayCanvas.width;
            const canvasHeight = liveOverlayCanvas.height;
            const useContain = liveVideoObjectFitMode === 'contain';
            const scale = useContain
                ? Math.min(canvasWidth / frameWidth, canvasHeight / frameHeight)
                : Math.max(canvasWidth / frameWidth, canvasHeight / frameHeight);
            const renderWidth = frameWidth * scale;
            const renderHeight = frameHeight * scale;
            const offsetX = (canvasWidth - renderWidth) / 2;
            const offsetY = (canvasHeight - renderHeight) / 2;

            // VẼ HEAT MAP (trước bbox)
            if (heatmapData && Array.isArray(heatmapData) && heatmapData.length > 0) {
                heatmapData.forEach((point) => {
                    const hx = offsetX + Number(point.x) * scale;
                    const hy = offsetY + Number(point.y) * scale;
                    const intensity = Number(point.intensity || 50) / 100; // 0-1
                    const radius = 15 + intensity * 10; // 15-25 pixels
                    
                    // Tao gradient tua theo intensity (xanh -> do)
                    const gradient = ctx.createRadialGradient(hx, hy, 0, hx, hy, radius);
                    if (intensity > 0.7) {
                        gradient.addColorStop(0, 'rgba(239, 68, 68, 0.4)');  // Red
                        gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
                    } else if (intensity > 0.4) {
                        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.3)');  // Orange
                        gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');
                    } else {
                        gradient.addColorStop(0, 'rgba(34, 197, 94, 0.2)');   // Green
                        gradient.addColorStop(1, 'rgba(34, 197, 94, 0)');
                    }
                    
                    ctx.fillStyle = gradient;
                    ctx.beginPath();
                    ctx.arc(hx, hy, radius, 0, 2 * Math.PI);
                    ctx.fill();
                });
            }

            detections.forEach((det) => {
                const bbox = Array.isArray(det.bbox) ? det.bbox : [];
                if (bbox.length !== 4) return;

                const x = offsetX + Number(bbox[0]) * scale;
                const y = offsetY + Number(bbox[1]) * scale;
                const w = (Number(bbox[2]) - Number(bbox[0])) * scale;
                const h = (Number(bbox[3]) - Number(bbox[1])) * scale;

                if (x + w < 0 || y + h < 0 || x > canvasWidth || y > canvasHeight) {
                    return;
                }

                const moveLevel = String(det.movement_level || 'Đứng yên');
                const strokeColor = moveLevel === 'Di chuyển mạnh'
                    ? '#ef4444'
                    : (moveLevel === 'Di chuyển vừa' ? '#f59e0b' : '#10b981');

                ctx.strokeStyle = strokeColor;
                ctx.lineWidth = 2;
                ctx.strokeRect(x, y, w, h);
                
                // VẼ MŨI TEN HUONG DI CHUYEN (NEW)
                if (det.movement_vector && (det.movement_vector.dx !== 0 || det.movement_vector.dy !== 0)) {
                    const centerX = x + w / 2;
                    const centerY = y + h / 2;
                    const vectorDx = det.movement_vector.dx * scale;
                    const vectorDy = det.movement_vector.dy * scale;
                    
                    // Ve duong mui ten
                    ctx.strokeStyle = strokeColor;
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    ctx.moveTo(centerX, centerY);
                    ctx.lineTo(centerX + vectorDx, centerY + vectorDy);
                    ctx.stroke();
                    
                    // Ve dau mui ten (tam giac)
                    const arrowSize = 8;
                    const angle = Math.atan2(vectorDy, vectorDx);
                    ctx.fillStyle = strokeColor;
                    ctx.beginPath();
                    ctx.moveTo(centerX + vectorDx, centerY + vectorDy);
                    ctx.lineTo(centerX + vectorDx - arrowSize * Math.cos(angle - Math.PI / 6), centerY + vectorDy - arrowSize * Math.sin(angle - Math.PI / 6));
                    ctx.lineTo(centerX + vectorDx - arrowSize * Math.cos(angle + Math.PI / 6), centerY + vectorDy - arrowSize * Math.sin(angle + Math.PI / 6));
                    ctx.closePath();
                    ctx.fill();
                }

                const species = escapeHtml(det.class_name_vi || toDisplayPestName(det.class_name));
                const caption = `${species} #${det.track_id || '?'}`;
                ctx.fillStyle = 'rgba(15, 23, 42, 0.86)';
                const boxWidth = Math.min(Math.max(0, canvasWidth - x - 4), Math.max(120, caption.length * 6.2));
                ctx.fillRect(x + 2, Math.max(2, y - 20), boxWidth, 18);
                ctx.fillStyle = '#f8fafc';
                ctx.font = '11px Be Vietnam Pro, sans-serif';
                ctx.fillText(caption, x + 6, Math.max(14, y - 7));
            });
        };

        const resetLivePanels = () => {
            liveLastFrameSize = null;
            liveLastDetections = [];

            if (liveImpactLevel) liveImpactLevel.textContent = 'Nhẹ';
            if (liveImpactScore) liveImpactScore.textContent = '0/100';
            if (liveRiskLevel) liveRiskLevel.textContent = 'Thấp';
            if (liveAvgSpeed) liveAvgSpeed.textContent = '0 px/s';
            if (liveVisibleCount) liveVisibleCount.textContent = '0 cá thể';
            if (liveDominantDirection) liveDominantDirection.textContent = 'Không rõ';
            if (liveSpreadLevel) liveSpreadLevel.textContent = 'Ổn định';
            if (liveSummaryNote) liveSummaryNote.textContent = 'Chưa có dữ liệu theo dõi, hãy bấm Bắt đầu giám sát.';

            if (liveSpeciesStats) {
                liveSpeciesStats.innerHTML = '<span class="rounded-full bg-slate-700 px-2 py-1 font-semibold text-slate-200">Chưa ghi nhận</span>';
            }
            if (liveTrackTableBody) {
                liveTrackTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-3 text-center text-slate-400">Chưa có dữ liệu</td></tr>';
            }
            if (liveSpreadEventList) {
                liveSpreadEventList.innerHTML = '<li class="rounded-lg bg-slate-800 px-2 py-1.5">Chưa ghi nhận đổi hướng di chuyển.</li>';
            }
            if (liveAlertList) {
                liveAlertList.innerHTML = '<li class="rounded-lg border border-rose-200/40 bg-slate-900/70 px-2 py-1.5">Sẵn sàng nhận cảnh báo mới.</li>';
            }

            updateLiveStatusPill('Sẵn sàng khởi động giám sát', 'idle');
            drawLiveOverlay(null, [], []);
        };

        const captureLiveFrameData = () => {
            if (!liveVideo || !liveVideo.videoWidth || !liveVideo.videoHeight) {
                return '';
            }

            const sourceWidth = liveVideo.videoWidth;
            const sourceHeight = liveVideo.videoHeight;
            const targetWidth = Math.min(LIVE_MAX_FRAME_WIDTH, sourceWidth);
            const targetHeight = Math.round((targetWidth / sourceWidth) * sourceHeight);

            liveCaptureCanvas.width = targetWidth;
            liveCaptureCanvas.height = targetHeight;
            const ctx = liveCaptureCanvas.getContext('2d');
            if (!ctx) return '';

            ctx.drawImage(liveVideo, 0, 0, targetWidth, targetHeight);
            return liveCaptureCanvas.toDataURL('image/jpeg', LIVE_JPEG_QUALITY);
        };

        const renderLiveSpecies = (speciesCounts) => {
            if (!liveSpeciesStats) return;
            const entries = Object.entries(speciesCounts || {});
            if (entries.length === 0) {
                liveSpeciesStats.innerHTML = '<span class="rounded-full bg-slate-700 px-2 py-1 font-semibold text-slate-200">Chưa ghi nhận</span>';
                return;
            }

            liveSpeciesStats.innerHTML = entries.map(([name, qty]) => {
                const label = escapeHtml(toDisplayPestName(name));
                const count = Number(qty) || 0;
                return `<span class="rounded-full border border-emerald-300/40 bg-emerald-500/20 px-2 py-1 font-semibold text-emerald-100">${label}: ${count}</span>`;
            }).join('');
        };

        const renderLiveTracks = (tracks) => {
            if (!liveTrackTableBody) return;
            if (!Array.isArray(tracks) || tracks.length === 0) {
                liveTrackTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-3 text-center text-slate-400">Chưa có dữ liệu</td></tr>';
                return;
            }

            liveTrackTableBody.innerHTML = tracks.slice(0, 12).map((item) => {
                const species = escapeHtml(item.class_name_vi || toDisplayPestName(item.class_name));
                const direction = escapeHtml(item.direction || 'Đứng yên');
                const speed = Number(item.speed_px_s || 0).toFixed(1);
                const speedTone = Number(item.speed_px_s || 0) >= 45 ? 'text-red-300' : (Number(item.speed_px_s || 0) >= 15 ? 'text-amber-300' : 'text-emerald-300');
                return `<tr>
                    <td class="px-2 py-1.5 font-semibold text-slate-100">${species} #${Number(item.track_id) || '?'}</td>
                    <td class="px-2 py-1.5 text-slate-300">${direction}</td>
                    <td class="px-2 py-1.5 text-right font-bold ${speedTone}">${speed}</td>
                </tr>`;
            }).join('');
        };

        const renderSpreadEvents = (events) => {
            if (!liveSpreadEventList) return;
            if (!Array.isArray(events) || events.length === 0) {
                liveSpreadEventList.innerHTML = '<li class="rounded-lg bg-slate-800 px-2 py-1.5">Chưa ghi nhận đổi hướng di chuyển.</li>';
                return;
            }

            liveSpreadEventList.innerHTML = events.slice(0, 8).map((event) => {
                const species = escapeHtml(event.class_name_vi || toDisplayPestName(event.class_name));
                const fromDirection = escapeHtml(event.from_direction || 'Đứng yên');
                const toDirection = escapeHtml(event.to_direction || 'Đứng yên');
                const duration = Number(event.duration_seconds || 0).toFixed(1);
                return `<li class="rounded-lg border border-slate-700 bg-slate-800 px-2 py-1.5">
                    ${species} #${Number(event.track_id) || '?'}: ${fromDirection} → ${toDirection} sau ${duration}s
                </li>`;
            }).join('');
        };

        const renderLiveSummary = (summary) => {
            const score = Number(summary.impact_score || 0);
            const avgSpeed = Number(summary.avg_speed_px_s || 0);
            const totalVisible = Number(summary.total_visible || 0);
            const dominantDirection = String(summary.dominant_direction || 'Không rõ');
            const spreadLevel = String(summary.spread_level || 'Ổn định');

            if (liveImpactLevel) {
                liveImpactLevel.textContent = summary.impact_level || 'Nhẹ';
                liveImpactLevel.className = `font-bold ${score >= 70 ? 'text-red-300' : (score >= 35 ? 'text-amber-300' : 'text-emerald-300')}`;
            }
            if (liveImpactScore) {
                liveImpactScore.textContent = `${score}/100`;
            }
            if (liveRiskLevel) {
                liveRiskLevel.textContent = summary.risk_level || 'Thấp';
                liveRiskLevel.className = `font-bold ${score >= 70 ? 'text-red-300' : (score >= 35 ? 'text-amber-300' : 'text-slate-100')}`;
            }
            if (liveAvgSpeed) {
                liveAvgSpeed.textContent = `${avgSpeed.toFixed(1)} px/s`;
            }
            if (liveVisibleCount) {
                liveVisibleCount.textContent = `${totalVisible} cá thể`;
            }
            if (liveDominantDirection) {
                liveDominantDirection.textContent = dominantDirection;
            }
            if (liveSpreadLevel) {
                liveSpreadLevel.textContent = spreadLevel;
                liveSpreadLevel.className = `font-bold ${spreadLevel === 'Lây lan nhanh' ? 'text-red-300' : (spreadLevel === 'Đang lan rộng' ? 'text-amber-300' : 'text-slate-100')}`;
            }
            if (liveSummaryNote) {
                liveSummaryNote.textContent = summary.summary_note || 'Đang chờ thêm dữ liệu để đánh giá chuyển động.';
            }
        };

        const renderLiveResponse = (data) => {
            const summary = data.movement_summary || {};
            renderLiveSummary(summary);
            renderLiveSpecies(data.species_counts || {});
            renderLiveTracks(data.tracks || []);
            renderSpreadEvents(data.spread_events || []);
            liveLastFrameSize = data.frame_size || null;
            liveLastDetections = Array.isArray(data.detections) ? data.detections : [];
            liveLastHeatmap = Array.isArray(data.centroid_heatmap) ? data.centroid_heatmap : [];
            liveMovementSummary = summary;
            
            // Luu vao history
            liveSessionHistory.push({
                timestamp: Date.now(),
                alert_level: summary.alert_level || 'green',
                total_visible: summary.total_visible || 0,
                impact_score: summary.impact_score || 0,
            });
            
            drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);

            const notifications = Array.isArray(data.notifications) ? data.notifications : [];
            notifications.forEach((text) => {
                appendLiveAlert(text, 'warn');
            });
            if (notifications.length > 0) {
                tryPlayLiveBeep();
                updateLiveStatusPill(`Nhận ${notifications.length} cảnh báo mới`, 'running');
            } else {
                updateLiveStatusPill('Đang giám sát realtime...', 'running');
            }
        };

        const runLiveTrackingCycle = async () => {
            if (!liveIsRunning || liveRequestInFlight) {
                return;
            }

            const frameData = captureLiveFrameData();
            if (!frameData) {
                updateLiveStatusPill('Camera chưa sẵn sàng để phân tích', 'idle');
                scheduleNextLiveCycle(getSelectedLiveIntervalMs());
                return;
            }

            liveRequestInFlight = true;
            liveRequestController = new AbortController();
            const startedAtMs = performance.now();
            let requestTimeoutHandle = null;
            let nextDelayMs = getSelectedLiveIntervalMs();
            try {
                const payload = {
                    frame_data: frameData,
                    session_id: liveSessionId,
                    timestamp_ms: Date.now()
                };

                requestTimeoutHandle = setTimeout(() => {
                    if (liveRequestController) {
                        liveRequestController.abort();
                    }
                }, LIVE_REQUEST_TIMEOUT_MS);

                const response = await fetch(liveApiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    signal: liveRequestController.signal
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Không thể phân tích dữ liệu realtime.');
                }

                if (data.session_id) {
                    liveSessionId = String(data.session_id);
                }

                liveConsecutiveTimeouts = 0;
                const responseMs = Math.max(1, performance.now() - startedAtMs);
                nextDelayMs = Math.min(
                    LIVE_DYNAMIC_MAX_INTERVAL_MS,
                    Math.max(getSelectedLiveIntervalMs(), Math.round(responseMs + 220))
                );
                renderLiveResponse(data);
            } catch (error) {
                const isTimeout = error && error.name === 'AbortError';
                if (isTimeout) {
                    liveConsecutiveTimeouts += 1;
                    nextDelayMs = Math.min(
                        LIVE_DYNAMIC_MAX_INTERVAL_MS,
                        getSelectedLiveIntervalMs() + (liveConsecutiveTimeouts * LIVE_ERROR_BACKOFF_MS)
                    );
                } else {
                    liveConsecutiveTimeouts = Math.min(liveConsecutiveTimeouts + 1, 3);
                    nextDelayMs = Math.min(
                        LIVE_DYNAMIC_MAX_INTERVAL_MS,
                        getSelectedLiveIntervalMs() + LIVE_ERROR_BACKOFF_MS
                    );
                }

                const message = isTimeout
                    ? 'AI phản hồi chậm, hệ thống sẽ thử lại ở khung tiếp theo.'
                    : (error && error.message ? error.message : 'Lỗi không xác định trong chế độ realtime.');
                updateLiveStatusPill(message, 'error');
                appendLiveAlert(message, 'error');
            } finally {
                if (requestTimeoutHandle) {
                    clearTimeout(requestTimeoutHandle);
                }
                liveRequestController = null;
                liveRequestInFlight = false;
                liveNextDelayMs = nextDelayMs;
                scheduleNextLiveCycle(liveNextDelayMs);
            }
        };

        const stopLiveMonitoring = () => {
            liveIsRunning = false;
            if (liveMonitoringTimer) {
                clearTimeout(liveMonitoringTimer);
                liveMonitoringTimer = null;
            }
            if (liveRequestController) {
                liveRequestController.abort();
                liveRequestController = null;
            }
            liveRequestInFlight = false;
            liveConsecutiveTimeouts = 0;
            setLiveButtonsState(false);
            updateLiveStatusPill('Đã dừng giám sát trực tiếp', 'idle');
        };

        const startLiveMonitoring = async () => {
            if (liveIsRunning) {
                return;
            }

            if (!liveMonitorStream) {
                const started = await startLiveCamera(liveFacingMode);
                if (!started) {
                    alert('Không thể mở camera để giám sát realtime.');
                    return;
                }
            }

            liveSessionId = `live_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
            liveIsRunning = true;
            liveConsecutiveTimeouts = 0;
            liveNextDelayMs = getSelectedLiveIntervalMs();
            setLiveButtonsState(true);
            updateLiveStatusPill('Đang giám sát realtime...', 'running');
            appendLiveAlert('Bắt đầu giám sát trực tiếp.', 'info');

            runLiveTrackingCycle();
        };

        const exportLiveReportAsCSV = () => {
            const timestamp = new Date().toLocaleString('vi-VN');
            const header = 'Thời gian,Cấp độ cảnh báo,Số lượng cảm nhận,Điểm ảnh hưởng,Loài chủ yếu\n';
            let csvContent = header;
            
            // Group history by alert level and extract relevant data
            liveSessionHistory.forEach(entry => {
                const alertLevel = entry.alert_level || 'unknown';
                const visible = entry.total_visible || 0;
                const score = Math.round(entry.impact_score || 0);
                const timestamp = new Date(entry.timestamp).toLocaleTimeString('vi-VN');
                
                csvContent += `"${timestamp}","${alertLevel}",${visible},${score},""\n`;
            });
            
            // Add footer summary
            if (liveMovementSummary) {
                csvContent += `\n"Tóm tắt phiên làm việc",,,,\n`;
                csvContent += `"Điểm ảnh hưởng tối đa",${Math.round(liveMovementSummary.max_impact_score || 0)},,,""\n`;
                csvContent += `"Số bản ghi",${liveSessionHistory.length},,,""\n`;
            }
            
            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', `live_report_${Date.now()}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        const showLiveHistoryPanel = () => {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
            
            let historyHTML = `
                <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold">Lịch sử giám sát</h2>
                        <button class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                    </div>
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left p-2 font-semibold">Thời gian</th>
                                <th class="text-center p-2 font-semibold">Cấp độ cảnh báo</th>
                                <th class="text-right p-2 font-semibold">Số lượng</th>
                                <th class="text-right p-2 font-semibold">Điểm ảnh hưởng</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Generate history rows
            liveSessionHistory.forEach((entry, idx) => {
                const time = new Date(entry.timestamp).toLocaleTimeString('vi-VN');
                const alert = entry.alert_level || 'unknown';
                const levelColor = {
                    'green': 'bg-green-100 text-green-900',
                    'yellow': 'bg-yellow-100 text-yellow-900',
                    'orange': 'bg-orange-100 text-orange-900',
                    'red': 'bg-red-100 text-red-900'
                }[alert] || 'bg-gray-100 text-gray-900';
                
                historyHTML += `
                    <tr class="${idx % 2 === 0 ? 'bg-gray-50' : ''}">
                        <td class="p-2 border-b">${time}</td>
                        <td class="p-2 border-b text-center"><span class="px-3 py-1 rounded ${levelColor}">${alert}</span></td>
                        <td class="p-2 border-b text-right">${entry.total_visible}</td>
                        <td class="p-2 border-b text-right">${Math.round(entry.impact_score)}</td>
                    </tr>
                `;
            });
            
            historyHTML += `
                        </tbody>
                    </table>
                    <div class="mt-4 text-sm text-gray-600">
                        <p>Tổng số bản ghi: <strong>${liveSessionHistory.length}</strong></p>
                        ${liveMovementSummary ? `<p>Điểm ảnh hưởng tối đa: <strong>${Math.round(liveMovementSummary.max_impact_score || 0)}</strong></p>` : ''}
                    </div>
                </div>
            `;
            
            modal.innerHTML = historyHTML;
            document.body.appendChild(modal);
            
            // Close button handler
            const closeBtn = modal.querySelector('button');
            closeBtn.addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            });
        };

        const openLiveMonitor = async () => {
            closeCamera();
            resetLivePanels();

            const started = await startLiveCamera(liveFacingMode);
            if (!started) {
                alert('Không mở được camera. Hãy cấp quyền camera và truy cập bằng HTTPS.');
                return;
            }
            liveMonitorModal.classList.remove('hidden');
            applyLiveVideoFitMode();
            updateFullscreenButtonLabel();
            resizeLiveOverlayCanvas();
            drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);
        };

        const closeLiveMonitor = () => {
            stopLiveMonitoring();

            const fullscreenTarget = document.fullscreenElement || document.webkitFullscreenElement;
            if (fullscreenTarget && (fullscreenTarget === liveVideoFrame || fullscreenTarget === liveVideo)) {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }

            liveMonitorModal.classList.add('hidden');
            stopLiveCamera();
            if (liveOverlayCanvas) {
                const ctx = liveOverlayCanvas.getContext('2d');
                if (ctx) {
                    ctx.clearRect(0, 0, liveOverlayCanvas.width, liveOverlayCanvas.height);
                }
            }
        };

        const openCamera = async () => {
            closeLiveMonitor();
            const started = await startCamera(currentFacingMode);
            if (!started) {
                alert('Không mở được camera. Hãy cấp quyền camera và truy cập bằng HTTPS.');
                return;
            }
            cameraModal.classList.remove('hidden');
        };

        const closeCamera = () => {
            cameraModal.classList.add('hidden');
            stopCamera();
        };

        if (imageInput) {
            imageInput.addEventListener('change', () => {
                const hasFile = imageInput.files && imageInput.files[0];
                setPreview(hasFile ? imageInput.files[0] : null);
            });
        }

        ['dragenter', 'dragover'].forEach((eventName) => {
            if (!dropzone) return;
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('border-brand', 'ring-2', 'ring-green-100');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            if (!dropzone) return;
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('ring-2', 'ring-green-100');
            });
        });

        if (dropzone) {
            dropzone.addEventListener('drop', (event) => {
                const files = event.dataTransfer.files;
                if (!files || !files.length || !imageInput) return;
                imageInput.files = files;
                setPreview(files[0]);
            });
        }

        if (openCameraBtn) {
            openCameraBtn.addEventListener('click', openCamera);
        }
        if (closeCameraBtn) {
            closeCameraBtn.addEventListener('click', closeCamera);
        }

        if (captureBtn) {
            captureBtn.addEventListener('click', () => {
                if (!cameraStream) return;
                const size = getCameraFrameSize();
                const width = size.width;
                const height = size.height;
                if (!width || !height) {
                    alert('Camera đang khởi tạo, vui lòng chờ 1-2 giây rồi chụp lại.');
                    return;
                }
                cameraCanvas.width = width;
                cameraCanvas.height = height;
                const ctx = cameraCanvas.getContext('2d');
                ctx.drawImage(cameraVideo, 0, 0, width, height);
                capturedDataUrl = cameraCanvas.toDataURL('image/jpeg', CAPTURE_JPEG_QUALITY);
                cameraVideo.classList.add('hidden');
                cameraCanvas.classList.remove('hidden');
            });
        }

        if (retakeBtn) {
            retakeBtn.addEventListener('click', () => {
                capturedDataUrl = '';
                cameraCanvas.classList.add('hidden');
                cameraVideo.classList.remove('hidden');
            });
        }

        if (switchCameraBtn) {
            switchCameraBtn.addEventListener('click', async () => {
                if (isSwitchingCamera) return;
                isSwitchingCamera = true;
                const nextFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                const started = await startCamera(nextFacingMode);
                if (!started) {
                    alert('Thiết bị không hỗ trợ đổi camera hoặc camera còn lại không khả dụng.');
                }
                isSwitchingCamera = false;
            });
        }

        if (usePhotoBtn) {
            usePhotoBtn.addEventListener('click', () => {
                if (!capturedDataUrl) {
                    alert('Bạn chưa chụp ảnh.');
                    return;
                }
                capturedImageInput.value = capturedDataUrl;
                if (imageInput) {
                    imageInput.value = '';
                }
                setPreviewDataUrl(capturedDataUrl, 'Ảnh chụp từ camera');
                closeCamera();
            });
        }

        if (openLiveMonitorBtn) {
            openLiveMonitorBtn.addEventListener('click', openLiveMonitor);
        }
        if (closeLiveMonitorBtn) {
            closeLiveMonitorBtn.addEventListener('click', closeLiveMonitor);
        }
        if (startLiveMonitorBtn) {
            startLiveMonitorBtn.addEventListener('click', startLiveMonitoring);
        }
        if (stopLiveMonitorBtn) {
            stopLiveMonitorBtn.addEventListener('click', stopLiveMonitoring);
        }
        if (exportLiveReportBtn) {
            exportLiveReportBtn.addEventListener('click', () => {
                if (liveSessionHistory.length === 0) {
                    alert('Chưa có dữ liệu để xuất báo cáo. Hãy bắt đầu giám sát trước.');
                    return;
                }
                exportLiveReportAsCSV();
            });
        }
        if (viewHistoryBtn) {
            viewHistoryBtn.addEventListener('click', () => {
                showLiveHistoryPanel();
            });
        }
        if (switchLiveCameraBtn) {
            switchLiveCameraBtn.addEventListener('click', async () => {
                if (liveSwitchingCamera) return;
                liveSwitchingCamera = true;
                const nextFacingMode = liveFacingMode === 'environment' ? 'user' : 'environment';
                const started = await startLiveCamera(nextFacingMode);
                if (!started) {
                    alert('Không thể đổi camera ở chế độ live.');
                }
                liveSwitchingCamera = false;
            });
        }
        if (toggleLiveFitBtn) {
            toggleLiveFitBtn.addEventListener('click', toggleLiveVideoFitMode);
        }
        if (liveFullscreenBtn) {
            liveFullscreenBtn.addEventListener('click', toggleLiveFullscreen);
        }
        if (openCameraFromLiveBtn) {
            openCameraFromLiveBtn.addEventListener('click', async () => {
                closeLiveMonitor();
                await openCamera();
            });
        }
        document.addEventListener('fullscreenchange', updateFullscreenButtonLabel);
        document.addEventListener('webkitfullscreenchange', updateFullscreenButtonLabel);

        window.addEventListener('resize', () => {
            resizeLiveOverlayCanvas();
            drawLiveOverlay(liveLastFrameSize, liveLastDetections, liveLastHeatmap);
        });

        window.addEventListener('beforeunload', () => {
            stopCamera();
            stopLiveMonitoring();
            stopLiveCamera();
            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
                previewObjectUrl = null;
            }
        });

        if (scanForm) {
            scanForm.addEventListener('submit', () => {
                scanOverlay.classList.remove('hidden');
                scanOverlay.classList.add('flex');
                btnAnalyze.innerHTML = '<iconify-icon icon="solar:spinner-linear" width="20" class="animate-spin"></iconify-icon> Đang phân tích...';
                btnAnalyze.classList.add('opacity-70', 'cursor-not-allowed');
            });
        }
    </script>

   <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- BỘ GIÁP CHỐNG LỖI JSON_ENCODE TỪ DATABASE ---
            const isHouseholdUser = <?php echo $isHouseholdUser ? 'true' : 'false'; ?>;
            const isGuestUser = <?php echo $isGuestUser ? 'true' : 'false'; ?>;
            const gisData = <?php $enc = json_encode($webgisJsData, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '{}'; ?>;
            const regionStyles = <?php $enc = json_encode($webgisJsStyles, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '{}'; ?>;
            const householdCoords = <?php $enc = json_encode($householdCoords); echo $enc ? $enc : 'null'; ?>;
            const householdAddress = <?php $enc = json_encode($householdAddress, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '""'; ?>;
            const householdPestTotal = <?php echo (int)$householdPestTotal; ?>;

            const regionCoordinates = {
                'Thôn 1 (Vùng Lúa nước)': [20.760, 105.775], 
                'Thôn 2 (Vùng Cải xanh)': [20.710, 105.760], 
                'Thôn 3 (Vùng Cà chua)': [20.715, 105.810]   
            };

            // ==========================================
            // 1. CHẾ ĐỘ KHÁCH VÃNG LAI
            // ==========================================
            if (isGuestUser) {
                const guestCenter = [20.734, 105.770];
                const map = L.map('main-webgis-map', {
                    center: guestCenter, zoom: 13, minZoom: 12, maxZoom: 18, zoomControl: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

                const teaserPoints = [[20.7365, 105.7672], [20.7289, 105.7766], [20.7412, 105.7820]];
                teaserPoints.forEach((point) => {
                    const fakeIcon = L.divIcon({
                        className: 'teaser-risk-marker',
                        html: '<div class="relative flex items-center justify-center"><span class="absolute inline-flex h-7 w-7 rounded-full bg-red-500 opacity-70 animate-ping"></span><span class="relative inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white text-xs font-bold">?</span></div>',
                        iconSize: [28, 28], iconAnchor: [14, 14]
                    });
                    L.marker(point, { icon: fakeIcon }).addTo(map);
                });
                return;
            }

            // ==========================================
            // 2. CHẾ ĐỘ HỘ GIA ĐÌNH
            // ==========================================
            if (isHouseholdUser) {
                const defaultCenter = [21.028511, 105.804817];
                const map = L.map('main-webgis-map', {
                    center: defaultCenter, zoom: 13, minZoom: 5, maxZoom: 19, zoomControl: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                
                setTimeout(() => { map.invalidateSize(); }, 200);

                const focusHousehold = (lat, lng, label) => {
                    const validLat = Number(lat);
                    const validLng = Number(lng);
                    if (isNaN(validLat) || isNaN(validLng)) return;

                    const latLng = [validLat, validLng];
                    map.setView(latLng, 16);
                    const activeLevel = householdPestTotal >= 30 ? 'BÁO ĐỘNG ĐỎ' : (householdPestTotal >= 10 ? 'CẢNH BÁO VÀNG' : 'AN TOÀN');
                    const activeColor = householdPestTotal >= 30 ? '#ef4444' : (householdPestTotal >= 10 ? '#f59e0b' : '#10b981');

                    L.marker(latLng).addTo(map).bindPopup(`
                        <div style="font-family: Inter, sans-serif; min-width: 180px;">
                            <strong style="font-size: 14px; color: #0f172a;">Vị trí hộ gia đình</strong>
                            <div style="margin-top: 6px; font-size: 12px; color: #475569;">${label || 'Đã đồng bộ từ hồ sơ tài khoản'}</div>
                            <div style="margin-top: 6px; font-size: 12px; color: #0369a1;">Tọa độ: ${validLat.toFixed(6)}, ${validLng.toFixed(6)}</div>
                            <div style="margin-top: 6px; font-size: 12px; color: #0f172a;">Mật độ: <strong>${householdPestTotal}</strong> - ${activeLevel}</div>
                        </div>
                    `).openPopup();

                    L.circle(latLng, {
                        radius: 420, color: activeColor, fillColor: activeColor, fillOpacity: 0.16, opacity: 0.9, weight: 3
                    }).addTo(map);
                };

                if (householdCoords && householdCoords.lat && householdCoords.lng) {
                    focusHousehold(householdCoords.lat, householdCoords.lng, householdAddress);
                } else if (householdAddress && householdAddress.trim() !== '') {
                    fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(householdAddress)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.length > 0) {
                                focusHousehold(data[0].lat, data[0].lon, householdAddress);
                            } else {
                                L.popup().setLatLng(defaultCenter).setContent('Không tìm thấy tọa độ từ địa chỉ đã lưu.').openOn(map);
                            }
                        }).catch(() => console.log('Không thể tải bản đồ Nominatim'));
                } else {
                    L.popup().setLatLng(defaultCenter).setContent('Bạn chưa cập nhật vị trí nhà trong hồ sơ.').openOn(map);
                }
                return; 
            }

            // ==========================================
            // 3. CHẾ ĐỘ NÔNG DÂN
            // ==========================================
            const unghoaBounds = L.latLngBounds(L.latLng(20.650, 105.680), L.latLng(20.820, 105.850));
            const map = L.map('main-webgis-map', {
                center: [20.734, 105.770], zoom: 13, minZoom: 12, maxZoom: 18,
                maxBounds: unghoaBounds, maxBoundsViscosity: 1.0, zoomControl: false
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            L.rectangle(unghoaBounds, { color: "#1d4ed8", weight: 4, fill: false, dashArray: '12, 12', opacity: 0.9 }).addTo(map);

            for (const [kv_name, data] of Object.entries(gisData)) {
                if (regionCoordinates[kv_name]) {
                    const coords = regionCoordinates[kv_name];
                    const total = parseInt(data.total);
                    const style = regionStyles[kv_name] || { fill: '#10b981', level: 'AN TOÀN' };

                    let circle = L.circleMarker(coords, {
                        radius: total > 0 ? Math.min(Math.max(total * 1.5, 12), 35) : 8, 
                        fillColor: style.fill, color: style.fill, weight: 2, opacity: 0.8, fillOpacity: 0.6
                    }).addTo(map);
                    L.circleMarker(coords, { radius: 3, fillColor: '#ffffff', color: 'transparent', fillOpacity: 1 }).addTo(map);

                    circle.bindPopup(`
                        <div style="font-family: Inter, sans-serif; text-align: center; min-width: 130px;">
                            <strong style="color: #1e293b; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; display: block; margin-bottom: 10px;">${kv_name}</strong>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="color: #64748b; font-size: 12px;">Mức độ:</span>
                                <span style="color: ${style.fill}; font-size: 12px; font-weight: bold;">${style.level}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #64748b; font-size: 12px;">Phát hiện:</span>
                                <strong style="color: #0f172a; font-size: 18px;">${total}</strong>
                            </div>
                        </div>
                    `);
                }
            }
        });
    </script>
</body>
</html>
