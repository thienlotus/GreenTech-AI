<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'pest_translate.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?tab=login');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'ho_gia_dinh') {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$fullName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Hộ gia đình'));
$scanScopeKey = 'HOGD_USER_' . $userId;
$householdAddress = '';

$userStmt = $conn->prepare('SELECT dia_chi_nha FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();
    $householdAddress = trim((string)($userRow['dia_chi_nha'] ?? ''));
}

$hasUserIdColumn = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM lich_su_quet LIKE 'user_id'");
if ($columnCheck && $columnCheck->num_rows > 0) {
    $hasUserIdColumn = true;
}

$totalInsects = 0;
$totalScans = 0;
$latestScanTime = '-';
$pestRows = [];

if ($hasUserIdColumn) {
    $summarySql = "SELECT
                    COALESCE(SUM(ct.so_luong), 0) AS total_insects,
                    COUNT(DISTINCT ls.id) AS total_scans,
                    MAX(ls.ngay_quet) AS latest_scan
                FROM lich_su_quet ls
                LEFT JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                WHERE ls.user_id = ?";
    $summaryStmt = $conn->prepare($summarySql);
    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $userId);
        $summaryStmt->execute();
        $summaryRes = $summaryStmt->get_result();
        $summary = $summaryRes ? $summaryRes->fetch_assoc() : null;
        $summaryStmt->close();

        if ($summary) {
            $totalInsects = (int)($summary['total_insects'] ?? 0);
            $totalScans = (int)($summary['total_scans'] ?? 0);
            $latestScanTime = $summary['latest_scan'] ? date('d/m/Y H:i', strtotime((string)$summary['latest_scan'])) : '-';
        }
    }

    $pestSql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS tong_so_luong
                FROM chi_tiet_dich_hai ct
                INNER JOIN lich_su_quet ls ON ct.lich_su_id = ls.id
                WHERE ls.user_id = ?
                GROUP BY ct.ten_loai_sau
                ORDER BY tong_so_luong DESC";
    $pestStmt = $conn->prepare($pestSql);
    if ($pestStmt) {
        $pestStmt->bind_param('i', $userId);
        $pestStmt->execute();
        $pestResult = $pestStmt->get_result();
        while ($pestResult && ($row = $pestResult->fetch_assoc())) {
            $pestRows[] = [
                'name' => translate_pest_name_vi((string)$row['ten_loai_sau']),
                'count' => (int)$row['tong_so_luong']
            ];
        }
        $pestStmt->close();
    }
} else {
    $legacyScope = $householdAddress !== '' ? $householdAddress : $scanScopeKey;

    $summarySql = "SELECT
                    COALESCE(SUM(ct.so_luong), 0) AS total_insects,
                    COUNT(DISTINCT ls.id) AS total_scans,
                    MAX(ls.ngay_quet) AS latest_scan
                FROM lich_su_quet ls
                LEFT JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                WHERE ls.khu_vuc IN (?, ?)";
    $summaryStmt = $conn->prepare($summarySql);
    if ($summaryStmt) {
        $summaryStmt->bind_param('ss', $scanScopeKey, $legacyScope);
        $summaryStmt->execute();
        $summaryRes = $summaryStmt->get_result();
        $summary = $summaryRes ? $summaryRes->fetch_assoc() : null;
        $summaryStmt->close();

        if ($summary) {
            $totalInsects = (int)($summary['total_insects'] ?? 0);
            $totalScans = (int)($summary['total_scans'] ?? 0);
            $latestScanTime = $summary['latest_scan'] ? date('d/m/Y H:i', strtotime((string)$summary['latest_scan'])) : '-';
        }
    }

    $pestSql = "SELECT ct.ten_loai_sau, SUM(ct.so_luong) AS tong_so_luong
                FROM chi_tiet_dich_hai ct
                INNER JOIN lich_su_quet ls ON ct.lich_su_id = ls.id
                WHERE ls.khu_vuc IN (?, ?)
                GROUP BY ct.ten_loai_sau
                ORDER BY tong_so_luong DESC";
    $pestStmt = $conn->prepare($pestSql);
    if ($pestStmt) {
        $pestStmt->bind_param('ss', $scanScopeKey, $legacyScope);
        $pestStmt->execute();
        $pestResult = $pestStmt->get_result();
        while ($pestResult && ($row = $pestResult->fetch_assoc())) {
            $pestRows[] = [
                'name' => translate_pest_name_vi((string)$row['ten_loai_sau']),
                'count' => (int)$row['tong_so_luong']
            ];
        }
        $pestStmt->close();
    }
}

$topPestName = !empty($pestRows) ? (string)$pestRows[0]['name'] : 'Chưa có dữ liệu';
$topPestCount = !empty($pestRows) ? (int)$pestRows[0]['count'] : 0;
$navAvatarPath = trim((string)($_SESSION['avatar_path'] ?? ''));
$navAvatarUrl = $navAvatarPath !== ''
    ? $navAvatarPath
    : ('https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($fullName !== '' ? $fullName : 'hogiadinh'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Hộ gia đình | GreenTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background:
                radial-gradient(circle at 10% 15%, rgba(34, 197, 94, 0.16), transparent 35%),
                radial-gradient(circle at 88% 10%, rgba(14, 165, 233, 0.14), transparent 36%),
                linear-gradient(145deg, #f8fafc 0%, #ecfeff 45%, #f0fdf4 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(10px);
        }

        .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 520ms ease, transform 520ms ease;
        }

        .reveal.show {
            opacity: 1;
            transform: translateY(0);
        }

        .stagger-1 { transition-delay: 40ms; }
        .stagger-2 { transition-delay: 90ms; }
        .stagger-3 { transition-delay: 140ms; }
        .stagger-4 { transition-delay: 190ms; }
    </style>
</head>
<body class="min-h-screen text-slate-800">
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
                <a href="dashboard_giadinh.php" class="text-sm font-semibold text-emerald-700">Dashboard</a>
            </nav>

            <div class="flex items-center gap-2">
                <div class="hidden items-center gap-2 sm:gap-3 md:flex">
                    <div class="text-right">
                        <div class="text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-[11px] text-slate-500">Vai trò: Hộ gia đình</div>
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
                    <div class="text-[11px] text-slate-500">Vai trò: Hộ gia đình</div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="index.php#home" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Trang chủ</a>
                <a href="index.php#scanner" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Quét AI</a>
                <a href="index.php#encyclopedia" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Cẩm nang</a>
                <a href="index.php#webgis" class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Bản đồ</a>
                <a href="thong_ke.php" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Thống kê</a>
                <a href="dashboard_giadinh.php" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Dashboard</a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <a href="profile.php" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center font-semibold text-slate-700 hover:bg-slate-50">Hồ sơ</a>
                <a href="logout.php" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center font-semibold text-rose-700 hover:bg-rose-100">Đăng xuất</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 pb-8 pt-24 sm:px-6">
        <section id="tong-quan" class="glass reveal rounded-3xl border border-white/80 p-6 shadow-sm sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Dashboard Hộ gia đình</p>
                    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl">Theo dõi sâu hại trong vườn nhà một cách trực quan và dễ hiểu</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">Mỗi lần bạn quét AI, hệ thống sẽ tự tổng hợp số liệu riêng cho hộ gia đình hiện tại. Từ đó bạn dễ nhận biết loài nào đang tăng nhanh để xử lý sớm bằng phương pháp an toàn.</p>
                    <?php if ($householdAddress !== ''): ?>
                        <p class="mt-3 text-xs font-semibold text-sky-700">Vị trí đồng bộ: <?php echo htmlspecialchars($householdAddress, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500">Tổng cá thể</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo (int)$totalInsects; ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500">Lượt quét</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900"><?php echo (int)$totalScans; ?></div>
                    </div>
                    <div class="col-span-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-amber-700">Loài nổi bật</div>
                        <div class="mt-1 text-base font-bold text-amber-800"><?php echo htmlspecialchars($topPestName, ENT_QUOTES, 'UTF-8'); ?><?php if ($topPestCount > 0): ?> - <?php echo (int)$topPestCount; ?> cá thể<?php endif; ?></div>
                        <div class="mt-1 text-xs text-amber-700">Lần quét gần nhất: <?php echo htmlspecialchars($latestScanTime, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="thong-ke" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[62%_38%]">
            <article class="glass reveal stagger-1 rounded-3xl border border-white/80 p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-extrabold text-slate-900">Bảng thông tin sâu hại</h2>
                    <a href="thong_ke.php" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Xem báo cáo chi tiết</a>
                </div>
                <p class="mt-1 text-xs text-slate-500">Bảng được sắp theo số lượng giảm dần, giúp bạn ưu tiên xử lý đúng loài gây hại nhiều nhất.</p>

                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                    <div class="max-h-[420px] overflow-y-auto">
                        <table class="min-w-full divide-y divide-slate-200 bg-white/90 text-sm">
                            <thead class="sticky top-0 bg-slate-50/95 backdrop-blur">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">#</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Loài côn trùng</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">Số lượng</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">Mức độ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($pestRows)): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Chưa có dữ liệu quét AI cho hộ gia đình này.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pestRows as $idx => $row): ?>
                                        <?php
                                            $rowLevel = 'An toàn';
                                            $rowBadge = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                            if ($row['count'] >= 30) {
                                                $rowLevel = 'Báo động đỏ';
                                                $rowBadge = 'bg-red-100 text-red-700 border-red-200';
                                            } elseif ($row['count'] >= 10) {
                                                $rowLevel = 'Cảnh báo vàng';
                                                $rowBadge = 'bg-amber-100 text-amber-700 border-amber-200';
                                            }
                                        ?>
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
                                            <td class="px-4 py-3 font-semibold text-slate-800"><?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-right font-extrabold text-slate-900"><?php echo (int)$row['count']; ?></td>
                                            <td class="px-4 py-3 text-right">
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold <?php echo $rowBadge; ?>">
                                                    <?php echo $rowLevel; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <aside id="hanh-dong" class="reveal stagger-2 space-y-4">
                <a href="index.php#scanner" class="glass block rounded-2xl border border-white/80 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                    <h3 class="text-sm font-extrabold text-slate-900">Quét AI ngay</h3>
                    <p class="mt-1 text-xs text-slate-600">Tải ảnh mới để cập nhật mật độ sâu hại theo thời gian thực.</p>
                </a>
                <a href="index.php#webgis" class="glass block rounded-2xl border border-white/80 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                    <h3 class="text-sm font-extrabold text-slate-900">Bản đồ mật độ quanh nhà</h3>
                    <p class="mt-1 text-xs text-slate-600">Theo dõi vòng cảnh báo màu theo tổng mật độ hiện tại.</p>
                </a>
                <a href="thong_ke.php" class="glass block rounded-2xl border border-white/80 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow">
                    <h3 class="text-sm font-extrabold text-slate-900">Báo cáo chi tiết</h3>
                    <p class="mt-1 text-xs text-slate-600">Xem đầy đủ triệu chứng và khuyến nghị xử lý sinh học.</p>
                </a>
            </aside>
        </section>
    </main>

    <script>
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                }
            });
        }, {
            threshold: 0.12
        });

        document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

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
