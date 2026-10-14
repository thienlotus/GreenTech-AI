<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_connect.php';

function auth_redirect(string $type, string $message, string $tab = 'login'): void
{
    $allowedTypes = ['success', 'error', 'info'];
    $safeType = in_array($type, $allowedTypes, true) ? $type : 'info';
    $safeTab = in_array($tab, ['login', 'register'], true) ? $tab : 'login';

    $query = http_build_query([
        'tab' => $safeTab,
        'type' => $safeType,
        'msg' => $message,
    ]);

    header('Location: auth.php?' . $query);
    exit;
}

function normalize_role(string $role): string
{
    $value = trim($role);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(['-', ' '], '_', $value);

    if (in_array($value, ['nong_dan', 'nongdan', 'nông_dân', 'nôngdân'], true)) {
        return 'nong_dan';
    }

    if (in_array($value, ['ho_gia_dinh', 'hogiadinh', 'hộ_gia_đình', 'hộ_gia_dinh'], true)) {
        return 'ho_gia_dinh';
    }

    return 'khach';
}

function current_user_columns(mysqli $conn): array
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM users');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $name = (string)($row['Field'] ?? '');
            if ($name !== '') {
                $columns[$name] = true;
            }
        }
    }
    return $columns;
}

function resolve_password_column(array $columns): string
{
    if (isset($columns['password_hash'])) {
        return 'password_hash';
    }

    if (isset($columns['password'])) {
        return 'password';
    }

    return '';
}

function redirect_after_login(string $role): void
{
    if ($role === 'nong_dan') {
        header('Location: dashboard_nongdan.php');
        exit;
    }

    if ($role === 'ho_gia_dinh') {
        header('Location: dashboard_giadinh.php');
        exit;
    }

    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_redirect('error', 'Truy cập không hợp lệ.', 'login');
}

$action = trim((string)($_POST['action'] ?? ''));

$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    auth_redirect('error', 'Thiếu bảng users trong database. Vui lòng kiểm tra lại dữ liệu SQL.', 'register');
}

$columns = current_user_columns($conn);
$passwordColumn = resolve_password_column($columns);

if ($passwordColumn === '' || !isset($columns['username']) || !isset($columns['role'])) {
    auth_redirect('error', 'Cấu trúc bảng users chưa đúng (thiếu cột đăng nhập bắt buộc).', 'register');
}

if ($action === 'register') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = normalize_role((string)($_POST['role'] ?? 'khach'));
    $khuVuc = trim((string)($_POST['khu_vuc'] ?? ''));
    $diaChi = trim((string)($_POST['dia_chi'] ?? ''));
    $houseLat = trim((string)($_POST['house_lat'] ?? ''));
    $houseLng = trim((string)($_POST['house_lng'] ?? ''));

    if ($fullName === '' || $username === '' || $password === '') {
        auth_redirect('error', 'Vui lòng nhập đầy đủ họ tên, tên đăng nhập và mật khẩu.', 'register');
    }

    if (strlen($username) < 4) {
        auth_redirect('error', 'Tên đăng nhập phải có ít nhất 4 ký tự.', 'register');
    }

    if (strlen($password) < 6) {
        auth_redirect('error', 'Mật khẩu phải có ít nhất 6 ký tự.', 'register');
    }

    if ($role === 'nong_dan' && $khuVuc === '') {
        auth_redirect('error', 'Vui lòng chọn khu vực giám sát.', 'register');
    }

    if ($role === 'ho_gia_dinh' && $diaChi === '' && ($houseLat === '' || $houseLng === '')) {
        auth_redirect('error', 'Hộ gia đình cần nhập địa chỉ hoặc chọn tọa độ trên bản đồ.', 'register');
    }

    if ($diaChi === '' && $houseLat !== '' && $houseLng !== '') {
        $diaChi = $houseLat . ', ' . $houseLng;
    }

    $checkStmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$checkStmt) {
        auth_redirect('error', 'Không thể kiểm tra tên đăng nhập. Vui lòng thử lại.', 'register');
    }

    $checkStmt->bind_param('s', $username);
    $checkStmt->execute();
    $existing = $checkStmt->get_result();
    $exists = $existing && $existing->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        auth_redirect('error', 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.', 'register');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertColumns = [];
    $values = [];
    $types = '';

    if (isset($columns['full_name'])) {
        $insertColumns[] = 'full_name';
        $values[] = $fullName;
        $types .= 's';
    }

    if (isset($columns['username'])) {
        $insertColumns[] = 'username';
        $values[] = $username;
        $types .= 's';
    }

    if (isset($columns[$passwordColumn])) {
        $insertColumns[] = $passwordColumn;
        $values[] = $passwordHash;
        $types .= 's';
    }

    if (isset($columns['role'])) {
        $insertColumns[] = 'role';
        $values[] = $role;
        $types .= 's';
    }

    if (isset($columns['khu_vuc_giam_sat'])) {
        $insertColumns[] = 'khu_vuc_giam_sat';
        $values[] = $role === 'nong_dan' ? $khuVuc : '';
        $types .= 's';
    }

    if (isset($columns['dia_chi_nha'])) {
        $insertColumns[] = 'dia_chi_nha';
        $values[] = $role === 'ho_gia_dinh' ? $diaChi : '';
        $types .= 's';
    }

    if (empty($insertColumns)) {
        auth_redirect('error', 'Bảng users không có cột phù hợp để lưu dữ liệu.', 'register');
    }

    $columnSql = implode(', ', $insertColumns);
    $placeholderSql = implode(', ', array_fill(0, count($insertColumns), '?'));
    $insertSql = 'INSERT INTO users (' . $columnSql . ') VALUES (' . $placeholderSql . ')';

    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        auth_redirect('error', 'Không thể tạo tài khoản lúc này. Vui lòng thử lại.', 'register');
    }

    $bindParams = [];
    $bindParams[] = &$types;
    foreach ($values as $idx => $value) {
        $bindParams[] = &$values[$idx];
    }

    call_user_func_array([$insertStmt, 'bind_param'], $bindParams);
    $saved = $insertStmt->execute();
    $insertStmt->close();

    if (!$saved) {
        auth_redirect('error', 'Đăng ký thất bại. Vui lòng kiểm tra dữ liệu và thử lại.', 'register');
    }

    auth_redirect('success', 'Đăng ký thành công. Vui lòng đăng nhập.', 'login');
}

if ($action === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        auth_redirect('error', 'Vui lòng nhập tên đăng nhập và mật khẩu.', 'login');
    }

    $selectParts = ['id', 'username', 'role'];
    if (isset($columns['full_name'])) {
        $selectParts[] = 'full_name';
    }
    if (isset($columns['avatar_path'])) {
        $selectParts[] = 'avatar_path';
    }
    $selectParts[] = $passwordColumn . ' AS auth_password';

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM users WHERE username = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        auth_redirect('error', 'Không thể đăng nhập lúc này. Vui lòng thử lại.', 'login');
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        auth_redirect('error', 'Sai tên đăng nhập hoặc mật khẩu.', 'login');
    }

    $storedPassword = (string)($user['auth_password'] ?? '');
    $isValidPassword = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

    if (!$isValidPassword) {
        auth_redirect('error', 'Sai tên đăng nhập hoặc mật khẩu.', 'login');
    }

    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['full_name'] = trim((string)($user['full_name'] ?? ''));
    $_SESSION['username'] = trim((string)($user['username'] ?? $username));
    $_SESSION['role'] = normalize_role((string)($user['role'] ?? 'khach'));
    $_SESSION['avatar_path'] = trim((string)($user['avatar_path'] ?? ''));

    redirect_after_login((string)$_SESSION['role']);
}

auth_redirect('error', 'Hành động không hợp lệ.', 'login');
