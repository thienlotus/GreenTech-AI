<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'pest_translate.php';

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

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?type=info&msg=Vui+l%C3%B2ng+%C4%91%C4%83ng+nh%E1%BA%ADp&tab=login');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = normalize_role_value((string)($_SESSION['role'] ?? 'khach'));
$currentUserName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng'));
$_SESSION['role'] = $currentRole;

if (!in_array($currentRole, ['ho_gia_dinh', 'nong_dan'], true)) {
    header('Location: index.php');
    exit;
}

$isHouseholdRole = $currentRole === 'ho_gia_dinh';
$isFarmerRole = $currentRole === 'nong_dan';
$dashboardLink = $isFarmerRole ? 'dashboard_nongdan.php' : 'dashboard_giadinh.php';
$roleLabel = $isFarmerRole ? 'Nông dân' : 'Hộ gia đình';

$householdAddress = '';
$scanScopeKey = 'HOGD_USER_' . $currentUserId;
$farmerVillage = '';

$userStmt = $conn->prepare('SELECT dia_chi_nha, khu_vuc_giam_sat FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();
    $householdAddress = trim((string)($userRow['dia_chi_nha'] ?? ''));
    $farmerVillage = trim((string)($userRow['khu_vuc_giam_sat'] ?? ''));
}

$expert_system = [
    'aphids' => [
        'symptom' => 'Lá quăn xoắn, chồi non kém phát triển, mặt lá có dịch ngọt và dễ xuất hiện nấm bồ hóng.',
        'treatment' => 'Phun nước áp lực nhẹ vào sáng sớm, dùng dầu neem hoặc xà phòng sinh học và thả bọ rùa nếu có thể.'
    ],
    'mites' => [
        'symptom' => 'Mặt dưới lá có chấm nhỏ li ti, lá ngả vàng rồi cháy sạm, cây suy yếu nhanh khi trời hanh khô.',
        'treatment' => 'Tăng độ ẩm khu vực trồng, cắt bỏ lá nặng, dùng chế phẩm sinh học đặc trị nhện đỏ theo liều khuyến cáo.'
    ],
    'snail' => [
        'symptom' => 'Lá non bị cắn nham nhở vào ban đêm, cây con dễ gãy hoặc mất ngọn.',
        'treatment' => 'Vệ sinh khu vực ẩm thấp, đặt bẫy sinh học, rải vôi bột vòng ngoài luống để hạn chế ốc sên xâm nhập.'
    ],
    'whitefly' => [
        'symptom' => 'Lá úa vàng, cây còi cọc, có thể lây bệnh virus làm giảm năng suất rau và cây cảnh.',
        'treatment' => 'Dùng bẫy dính màu vàng, luân phiên chế phẩm sinh học và cắt bỏ lá bị nhiễm nặng.'
    ],
    'thrips' => [
        'symptom' => 'Lá non bạc màu, quăn mép, hoa và trái non bị sần sùi hoặc biến dạng.',
        'treatment' => 'Tỉa tán cây cho thoáng, giữ ẩm ổn định và phun chế phẩm thảo mộc hoặc sinh học vào chiều mát.'
    ],
    'flea beetle' => [
        'symptom' => 'Lá bị thủng nhiều lỗ nhỏ như rây, cây non chậm lớn và giảm khả năng quang hợp.',
        'treatment' => 'Dùng lưới chắn côn trùng, dọn sạch cỏ dại, phun dịch tỏi ớt hoặc chế phẩm sinh học đúng liều.'
    ],
    'default' => [
        'symptom' => 'Cần theo dõi thêm biểu hiện trên lá, thân và tốc độ lây lan trong vài ngày tới.',
        'treatment' => 'Ưu tiên biện pháp sinh học, vệ sinh khu vực trồng, giữ mật độ cây hợp lý và theo dõi định kỳ.'
    ]
];

$result = null;
$stmt = null;
if ($isHouseholdRole) {
    $hasUserIdColumn = false;
    $columnCheck = $conn->query("SHOW COLUMNS FROM lich_su_quet LIKE 'user_id'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        $hasUserIdColumn = true;
    }

    if ($hasUserIdColumn) {
        $sql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS tong_so_luong
                FROM chi_tiet_dich_hai ct
                INNER JOIN lich_su_quet ls ON ct.lich_su_id = ls.id
                WHERE ls.user_id = ?
                GROUP BY ct.ten_loai_sau
                ORDER BY tong_so_luong DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    } else {
        $legacyScope = $householdAddress !== '' ? $householdAddress : $scanScopeKey;
        $sql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS tong_so_luong
                FROM chi_tiet_dich_hai ct
                INNER JOIN lich_su_quet ls ON ct.lich_su_id = ls.id
                WHERE ls.khu_vuc IN (?, ?)
                GROUP BY ct.ten_loai_sau
                ORDER BY tong_so_luong DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $scanScopeKey, $legacyScope);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    }
} elseif ($isFarmerRole && $farmerVillage !== '') {
    $sql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS tong_so_luong
            FROM chi_tiet_dich_hai ct
            INNER JOIN lich_su_quet ls ON ct.lich_su_id = ls.id
            WHERE ls.khu_vuc = ? OR ls.khu_vuc LIKE CONCAT(?, ' (%)')
            GROUP BY ct.ten_loai_sau
            ORDER BY tong_so_luong DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $farmerVillage, $farmerVillage);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

$chartLabels = [];
$chartValues = [];
$reportItems = [];
$totalInsects = 0;

$palette = [
    '#22c55e', '#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
];

if ($result && $result->num_rows > 0) {
    $colorIndex = 0;
    while ($row = $result->fetch_assoc()) {
        $pestKey = strtolower(trim((string)($row['ten_loai_sau'] ?? '')));
        $quantity = (int)($row['tong_so_luong'] ?? 0);
        $totalInsects += $quantity;

        $info = $expert_system[$pestKey] ?? $expert_system['default'];
        $displayName = translate_pest_name_vi($pestKey);
        if ($displayName === $pestKey || trim($displayName) === '') {
            $displayName = ucfirst($pestKey);
        }

        $color = $palette[$colorIndex % count($palette)];
        $colorIndex++;

        $chartLabels[] = $displayName;
        $chartValues[] = $quantity;

        $reportItems[] = [
            'name' => $displayName,
            'quantity' => $quantity,
            'symptom' => $info['symptom'],
            'treatment' => $info['treatment'],
            'color' => $color
        ];
    }
}

if (isset($stmt) && $stmt) {
    $stmt->close();
}

$scanCount = count($reportItems);
$navAvatarPath = trim((string)($_SESSION['avatar_path'] ?? ''));
$navAvatarUrl = $navAvatarPath !== ''
    ? $navAvatarPath
    : ('https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($currentUserName !== '' ? $currentUserName : 'user'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenTech | Thống kê dịch hại</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background:
                radial-gradient(circle at 8% 12%, rgba(34, 197, 94, 0.20) 0%, transparent 36%),
                radial-gradient(circle at 90% 8%, rgba(14, 165, 233, 0.17) 0%, transparent 35%),
                linear-gradient(145deg, #f8fafc 0%, #ecfeff 45%, #f0fdf4 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <nav class="fixed top-0 inset-x-0 z-50 border-b border-slate-200/60 bg-white/85 backdrop-blur-xl">
        <div class="mx-auto flex h-16 w-full max-w-[1400px] items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-emerald-700"></iconify-icon>
                <span class="text-lg font-semibold tracking-tighter text-emerald-700">GREENTECH</span>
            </div>

            <div class="hidden items-center gap-7 md:flex">
                <a href="index.php#home" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Trang chủ</a>
                <a href="index.php#scanner" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Quét AI</a>
                <a href="index.php#encyclopedia" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Cẩm nang</a>
                <a href="index.php#webgis" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Bản đồ</a>
                <a href="thong_ke.php" class="text-sm font-semibold text-emerald-700">Thống kê</a>
                <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="text-sm font-semibold text-emerald-700">Dashboard</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="hidden items-center gap-2 sm:gap-3 md:flex">
                    <div class="text-right">
                        <div class="text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-[11px] text-slate-500">Vai trò: <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="h-9 w-9 overflow-hidden rounded-full border-2 border-white bg-slate-200 shadow-sm">
                        <img src="<?php echo htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" class="h-full w-full object-cover">
                    </div>
                    <a href="profile.php" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Hồ sơ</a>
                    <a href="logout.php" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Đăng xuất</a>
                </div>

                <button id="mobileMenuToggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm md:hidden" aria-label="Mở menu" aria-controls="mobileMenuPanel" aria-expanded="false">
                    <iconify-icon icon="solar:hamburger-menu-linear" width="20"></iconify-icon>
                </button>
            </div>
        </div>

        <div id="mobileMenuPanel" class="hidden border-t border-slate-200/70 bg-white/95 px-4 pb-4 pt-3 backdrop-blur-xl md:hidden">
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="h-10 w-10 overflow-hidden rounded-full border-2 border-white bg-slate-200 shadow-sm">
                    <img src="<?php echo htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" class="h-full w-full object-cover">
                </div>
                <div>
                    <div class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-[11px] text-slate-500">Vai trò: <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="index.php#home" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Trang chủ</a>
                <a href="index.php#scanner" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Quét AI</a>
                <a href="index.php#encyclopedia" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Cẩm nang</a>
                <a href="index.php#webgis" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Bản đồ</a>
                <a href="thong_ke.php" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Thống kê</a>
                <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Dashboard</a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="profile.php" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center font-semibold text-slate-700 hover:bg-slate-50">Hồ sơ</a>
                <a href="logout.php" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center font-semibold text-rose-700 hover:bg-rose-100">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <main class="mx-auto w-full max-w-7xl px-4 pb-8 pt-24 sm:px-6">
        <section class="glass rounded-3xl border border-white/70 p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                        <?php echo $isFarmerRole ? 'Báo cáo dịch hại theo thôn giám sát' : 'Báo cáo thống kê sâu hại tại vườn nhà'; ?>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        <?php echo $isFarmerRole
                            ? 'Dữ liệu được tổng hợp theo thôn bạn phụ trách, giúp so sánh nhanh mật độ sâu hại và xác định ưu tiên xử lý.'
                            : 'Dữ liệu dưới đây chỉ lấy theo tài khoản hộ gia đình hiện tại. Hệ thống tổng hợp số lượng từng loài côn trùng đã quét và gợi ý hướng xử lý sinh học an toàn.'; ?>
                    </p>
                    <?php if ($isHouseholdRole && $householdAddress !== ''): ?>
                        <p class="mt-2 text-xs text-sky-700">Vị trí đã đồng bộ: <?php echo htmlspecialchars($householdAddress, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($isFarmerRole): ?>
                        <p class="mt-2 text-xs text-sky-700">Thôn giám sát: <?php echo htmlspecialchars($farmerVillage !== '' ? $farmerVillage : 'Chưa thiết lập trong hồ sơ', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-3 md:w-[320px]">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500">Tổng cá thể</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo (int)$totalInsects; ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500">Loài đã ghi nhận</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo (int)$scanCount; ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[44%_56%]">
            <div class="glass rounded-3xl border border-white/70 p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-800">Cơ cấu côn trùng theo tỷ lệ</h2>
                <p class="mt-1 text-xs text-slate-500">
                    <?php echo $isFarmerRole
                        ? 'Biểu đồ Doughnut thể hiện tỷ trọng từng loài sâu hại trong thôn bạn phụ trách.'
                        : 'Biểu đồ Doughnut thể hiện tỷ trọng từng loài trong tổng số cá thể đã quét.'; ?>
                </p>
                <div class="mt-5 h-[300px]">
                    <canvas id="householdChart"></canvas>
                </div>
            </div>

            <div class="glass rounded-3xl border border-white/70 p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-800">Danh sách cảnh báo và khuyến nghị</h2>
                <p class="mt-1 text-xs text-slate-500">Mỗi thẻ gồm triệu chứng điển hình và cách xử lý sinh học tương ứng.</p>

                <?php if (empty($reportItems)): ?>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-6 text-center">
                        <p class="text-sm font-semibold text-slate-700">
                            <?php echo $isFarmerRole ? 'Chưa có dữ liệu quét cho thôn giám sát hiện tại.' : 'Chưa có dữ liệu quét cho hộ gia đình này.'; ?>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Hãy quay lại trang chủ và quét AI để hệ thống bắt đầu thống kê.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <?php foreach ($reportItems as $item): ?>
                            <article class="rounded-2xl border border-white/80 bg-white/75 p-4 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-extrabold text-slate-800"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold text-white" style="background-color: <?php echo htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?>;">
                                        <?php echo (int)$item['quantity']; ?> cá thể
                                    </span>
                                </div>

                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Triệu chứng gây hại</div>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-700"><?php echo htmlspecialchars($item['symptom'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>

                                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Cách xử lý an toàn/sinh học</div>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-700"><?php echo htmlspecialchars($item['treatment'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        const labels = <?php echo json_encode($chartLabels); ?>;
        const values = <?php echo json_encode($chartValues); ?>;
        const colors = <?php echo json_encode(array_map(static function ($item) {
            return $item['color'];
        }, $reportItems)); ?>;

        const chartCanvas = document.getElementById('householdChart');
        if (chartCanvas && labels.length > 0) {
            const ctx = chartCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                color: '#334155',
                                font: {
                                    size: 12,
                                    family: 'Be Vietnam Pro'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = context.dataset.data.reduce((sum, v) => sum + v, 0);
                                    const val = context.raw;
                                    const percent = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                                    return ` ${context.label}: ${val} cá thể (${percent}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenuPanel = document.getElementById('mobileMenuPanel');

        if (mobileMenuToggle && mobileMenuPanel) {
            const menuIcon = mobileMenuToggle.querySelector('iconify-icon');

            const setMobileMenuState = (isOpen) => {
                mobileMenuPanel.classList.toggle('hidden', !isOpen);
                mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (menuIcon) {
                    menuIcon.setAttribute('icon', isOpen ? 'solar:close-circle-linear' : 'solar:hamburger-menu-linear');
                }
            };

            mobileMenuToggle.addEventListener('click', () => {
                const isOpen = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
                setMobileMenuState(!isOpen);
            });

            mobileMenuPanel.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => setMobileMenuState(false));
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Node)) {
                    return;
                }
                if (!mobileMenuPanel.contains(target) && !mobileMenuToggle.contains(target)) {
                    setMobileMenuState(false);
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    setMobileMenuState(false);
                }
            });
        }
    </script>
</body>
</html>
