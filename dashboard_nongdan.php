<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'pest_translate.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?type=info&msg=Vui+l%C3%B2ng+%C4%91%C4%83ng+nh%E1%BA%ADp&tab=login');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'nong_dan') {
    header('Location: index.php');
    exit;
}

$fullName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Nông dân'));

$farmerVillage = '';
$totalScans = 0;
$totalInsects = 0;
$speciesCount = 0;
$lastScanAt = '';
$topPests = [];
$villageLabels = ['Thôn 1', 'Thôn 2', 'Thôn 3'];
$villageTotals = [0, 0, 0];

$expertSystem = [
    'aphids' => [
        'symptom' => 'Lá quăn xoắn, xuất hiện dịch ngọt trên bề mặt lá.',
        'treatment' => 'Dùng dầu neem hoặc xà phòng sinh học, kết hợp thiên địch bọ rùa.'
    ],
    'whitefly' => [
        'symptom' => 'Lá vàng, cây còi cọc và dễ lây nhiễm virus khảm.',
        'treatment' => 'Treo bẫy dính vàng, phun chế phẩm sinh học định kỳ.'
    ],
    'snail' => [
        'symptom' => 'Lá non bị cắn nham nhở vào ban đêm, cây con dễ mất ngọn.',
        'treatment' => 'Vệ sinh khu trồng, dùng bẫy sinh học hoặc rải vôi vòng ngoài luống.'
    ],
    'mites' => [
        'symptom' => 'Lá có chấm li ti, vàng và cháy sạm khi mật độ cao.',
        'treatment' => 'Giữ ẩm hợp lý, cắt lá nặng, dùng chế phẩm sinh học đặc trị nhện.'
    ],
    'thrips' => [
        'symptom' => 'Lá non bạc màu, quăn mép, trái non dễ biến dạng.',
        'treatment' => 'Tỉa tán thông thoáng và phun chế phẩm sinh học lúc chiều mát.'
    ],
    'flea beetle' => [
        'symptom' => 'Lá bị đục nhiều lỗ nhỏ, cây non chậm phát triển.',
        'treatment' => 'Dùng lưới chắn côn trùng, dọn cỏ dại và xử lý đất trước vụ.'
    ],
    'default' => [
        'symptom' => 'Cần theo dõi thêm triệu chứng trên lá, thân và rễ.',
        'treatment' => 'Ưu tiên biện pháp sinh học và giám sát định kỳ theo tuần.'
    ]
];

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    $userStmt = $conn->prepare('SELECT khu_vuc_giam_sat FROM users WHERE id = ? LIMIT 1');
    if ($userStmt) {
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult ? $userResult->fetch_assoc() : null;
        $userStmt->close();
        $farmerVillage = trim((string)($userRow['khu_vuc_giam_sat'] ?? ''));
    }
}

if ($farmerVillage !== '') {
    $summarySql = "SELECT COUNT(DISTINCT ls.id) AS total_scans,
                          COALESCE(SUM(ct.so_luong), 0) AS total_insects,
                          COUNT(DISTINCT ct.ten_loai_sau) AS species_count,
                          MAX(ls.ngay_quet) AS last_scan_at
                   FROM lich_su_quet ls
                   LEFT JOIN chi_tiet_dich_hai ct ON ct.lich_su_id = ls.id
                   WHERE ls.khu_vuc = ? OR ls.khu_vuc LIKE CONCAT(?, ' (%)')";
    $summaryStmt = $conn->prepare($summarySql);
    if ($summaryStmt) {
        $summaryStmt->bind_param('ss', $farmerVillage, $farmerVillage);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        $summaryRow = $summaryResult ? $summaryResult->fetch_assoc() : null;
        $summaryStmt->close();

        if ($summaryRow) {
            $totalScans = (int)($summaryRow['total_scans'] ?? 0);
            $totalInsects = (int)($summaryRow['total_insects'] ?? 0);
            $speciesCount = (int)($summaryRow['species_count'] ?? 0);
            $lastScanAt = trim((string)($summaryRow['last_scan_at'] ?? ''));
        }
    }

    $topSql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS total_qty
               FROM chi_tiet_dich_hai ct
               INNER JOIN lich_su_quet ls ON ls.id = ct.lich_su_id
               WHERE ls.khu_vuc = ? OR ls.khu_vuc LIKE CONCAT(?, ' (%)')
               GROUP BY ct.ten_loai_sau
               ORDER BY total_qty DESC
               LIMIT 5";
    $topStmt = $conn->prepare($topSql);
    if ($topStmt) {
        $topStmt->bind_param('ss', $farmerVillage, $farmerVillage);
        $topStmt->execute();
        $topResult = $topStmt->get_result();
        if ($topResult) {
            while ($row = $topResult->fetch_assoc()) {
                $rawKey = strtolower(trim((string)($row['ten_loai_sau'] ?? '')));
                $displayName = translate_pest_name_vi($rawKey);
                if ($displayName === '' || $displayName === $rawKey) {
                    $displayName = ucfirst($rawKey);
                }

                $expertInfo = $expertSystem[$rawKey] ?? $expertSystem['default'];

                $topPests[] = [
                    'name' => $displayName,
                    'qty' => (int)($row['total_qty'] ?? 0),
                    'symptom' => $expertInfo['symptom'],
                    'treatment' => $expertInfo['treatment']
                ];
            }
        }
        $topStmt->close();
    }
}

for ($i = 0; $i < count($villageLabels); $i++) {
    $label = $villageLabels[$i];
    $villageStmt = $conn->prepare(
        "SELECT COALESCE(SUM(ct.so_luong), 0) AS total_qty
         FROM chi_tiet_dich_hai ct
         INNER JOIN lich_su_quet ls ON ls.id = ct.lich_su_id
         WHERE ls.khu_vuc = ? OR ls.khu_vuc LIKE CONCAT(?, ' (%)')"
    );
    if ($villageStmt) {
        $villageStmt->bind_param('ss', $label, $label);
        $villageStmt->execute();
        $villageResult = $villageStmt->get_result();
        $villageRow = $villageResult ? $villageResult->fetch_assoc() : null;
        $villageStmt->close();
        $villageTotals[$i] = (int)($villageRow['total_qty'] ?? 0);
    }
}

$formattedLastScan = 'Chưa có dữ liệu';
if ($lastScanAt !== '') {
    $timestamp = strtotime($lastScanAt);
    if ($timestamp !== false) {
        $formattedLastScan = date('d/m/Y H:i', $timestamp);
    }
}

$navAvatarPath = trim((string)($_SESSION['avatar_path'] ?? ''));
$navAvatarUrl = $navAvatarPath !== ''
    ? $navAvatarPath
    : ('https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($fullName !== '' ? $fullName : 'nongdan'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nông dân | GreenTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-lime-50 text-slate-800">
    <header class="fixed top-0 inset-x-0 z-50 border-b border-slate-200/60 bg-white/85 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-[1400px] items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-emerald-700"></iconify-icon>
                <span class="text-lg font-semibold tracking-tighter text-emerald-700">GREENTECH</span>
            </div>

            <nav class="hidden items-center gap-7 md:flex">
                <a href="index.php#home" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Trang chủ</a>
                <a href="index.php#scanner" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Quét AI</a>
                <a href="index.php#encyclopedia" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Cẩm nang</a>
                <a href="index.php#webgis" class="text-sm font-medium text-slate-500 hover:text-emerald-700">Bản đồ</a>
                <a href="thong_ke.php" class="text-sm font-semibold text-emerald-700">Thống kê</a>
                <a href="dashboard_nongdan.php" class="text-sm font-semibold text-emerald-700">Dashboard</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="hidden items-center gap-2 sm:gap-3 md:flex">
                    <div class="text-right">
                        <div class="text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-[10px] text-slate-500">Vai trò: Nông dân</div>
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
                    <div class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-[11px] text-slate-500">Vai trò: Nông dân</div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="index.php#home" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Trang chủ</a>
                <a href="index.php#scanner" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Quét AI</a>
                <a href="index.php#encyclopedia" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Cẩm nang</a>
                <a href="index.php#webgis" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Bản đồ</a>
                <a href="thong_ke.php" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Thống kê</a>
                <a href="dashboard_nongdan.php" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Dashboard</a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="profile.php" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center font-semibold text-slate-700 hover:bg-slate-50">Hồ sơ</a>
                <a href="logout.php" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center font-semibold text-rose-700 hover:bg-rose-100">Đăng xuất</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-8 pt-24 sm:px-6">
        <section class="rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Xin chào</p>
            <h2 class="mt-1 text-2xl font-extrabold text-emerald-800"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="mt-3 text-sm text-slate-600">Tài khoản của bạn đã được tạo thành công và chuyển đúng vào khu vực dành cho Nông dân.</p>
            <p class="mt-2 text-xs font-semibold text-emerald-700">
                Khu vực giám sát:
                <?php echo htmlspecialchars($farmerVillage !== '' ? $farmerVillage : 'Chưa thiết lập', ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </section>

        <section class="mt-6 rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Tổng quan dịch hại cho nông dân</h3>
                    <p class="mt-1 text-sm text-slate-600">Dashboard tập trung theo dõi nhanh để bạn ra quyết định trong ngày.</p>
                </div>
                <a href="index.php#scanner" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Quét thêm dữ liệu</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Số lượt quét</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo $totalScans; ?></p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Tổng cá thể</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo $totalInsects; ?></p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Loài ghi nhận</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo $speciesCount; ?></p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Lần quét gần nhất</p>
                    <p class="mt-1 text-sm font-bold text-slate-900"><?php echo htmlspecialchars($formattedLastScan, ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-sm font-bold text-slate-800">Biểu đồ cột mật độ sâu hại theo thôn 1, 2, 3</h4>
                <p class="mt-1 text-xs text-slate-500">So sánh tổng số cá thể ghi nhận để phát hiện khu vực cần ưu tiên xử lý.</p>
                <div class="mt-4 h-[280px]">
                    <canvas id="villageBarChart"></canvas>
                </div>
            </div>

            <?php if ($farmerVillage === ''): ?>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Bạn chưa chọn thôn giám sát trong hồ sơ, nên hệ thống chưa thể tổng hợp thống kê.
                </div>
            <?php elseif (empty($topPests)): ?>
                <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                    Chưa có dữ liệu sâu hại cho khu vực <?php echo htmlspecialchars($farmerVillage, ENT_QUOTES, 'UTF-8'); ?>.
                </div>
            <?php else: ?>
                <div class="mt-5">
                    <h4 class="text-sm font-bold text-slate-800">Thông tin loại sâu hại nổi bật</h4>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($topPests as $item): ?>
                            <article class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h5 class="text-sm font-extrabold text-slate-900"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700"><?php echo (int)$item['qty']; ?></span>
                                </div>
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Triệu chứng</p>
                                    <p class="mt-1 text-xs text-slate-700"><?php echo htmlspecialchars($item['symptom'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Khuyến nghị</p>
                                    <p class="mt-1 text-xs text-slate-700"><?php echo htmlspecialchars($item['treatment'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="index.php#scanner" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                <h3 class="font-bold text-slate-800">Quét AI ngay</h3>
                <p class="mt-2 text-sm text-slate-600">Tải ảnh và phân tích côn trùng tức thì.</p>
            </a>
            <a href="index.php#webgis" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                <h3 class="font-bold text-slate-800">Xem bản đồ dịch hại</h3>
                <p class="mt-2 text-sm text-slate-600">Theo dõi cảnh báo theo khu vực trồng trọt.</p>
            </a>
            <a href="profile.php" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                <h3 class="font-bold text-slate-800">Cập nhật hồ sơ</h3>
                <p class="mt-2 text-sm text-slate-600">Điều chỉnh thông tin thôn giám sát và ảnh đại diện.</p>
            </a>
        </section>
    </main>

    <script>
        const villageLabels = <?php echo json_encode($villageLabels); ?>;
        const villageTotals = <?php echo json_encode($villageTotals); ?>;

        const barCanvas = document.getElementById('villageBarChart');
        if (barCanvas) {
            const ctx = barCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: villageLabels,
                    datasets: [{
                        label: 'Tổng cá thể sâu hại',
                        data: villageTotals,
                        borderRadius: 8,
                        backgroundColor: ['#16a34a', '#0ea5e9', '#f59e0b'],
                        borderColor: ['#15803d', '#0284c7', '#d97706'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => ` ${context.raw} cá thể`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#475569'
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.25)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#334155',
                                font: {
                                    weight: 700
                                }
                            },
                            grid: {
                                display: false
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
