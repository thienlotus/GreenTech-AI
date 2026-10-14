<?php
session_start();

$flashType = $_GET['type'] ?? '';
$flashMsg = $_GET['msg'] ?? '';

$allowedTypes = ['success', 'error', 'info'];
if (!in_array($flashType, $allowedTypes, true)) {
    $flashType = '';
}

$flashClassMap = [
    'success' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'error' => 'bg-rose-100 text-rose-700 border-rose-200',
    'info' => 'bg-sky-100 text-sky-700 border-sky-200'
];

$activeTab = $_GET['tab'] ?? 'register';
if (!in_array($activeTab, ['register', 'login'], true)) {
    $activeTab = 'register';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký / Đăng nhập | Hệ thống giám sát dịch hại</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-deep: #0f3d3e;
            --brand-mid: #186a6b;
            --brand-soft: #f6fffb;
            --accent-warm: #f59e0b;
        }

        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(245, 158, 11, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 25%, rgba(22, 163, 74, 0.14) 0%, transparent 36%),
                linear-gradient(135deg, #f8fafc 0%, #ecfeff 45%, #f0fdf4 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(8px);
        }

        .reveal {
            animation: fadeSlide 0.45s ease both;
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #house-map {
            height: 240px;
            border-radius: 0.75rem;
            border: 1px solid #7dd3fc;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-10 sm:px-8">
        <div class="grid w-full overflow-hidden rounded-3xl border border-white/60 shadow-2xl shadow-teal-900/10 lg:grid-cols-2">
            <section class="relative hidden overflow-hidden bg-gradient-to-br from-teal-900 via-teal-700 to-emerald-600 p-10 text-white lg:block">
                <div class="absolute -top-10 -right-8 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="absolute bottom-8 left-8 h-28 w-28 rounded-full bg-amber-300/20"></div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.28em] text-emerald-100">NCKH GreenTech</p>
                        <h1 class="mt-4 text-4xl font-extrabold leading-tight">Quản lý tài khoản thông minh cho hệ thống giám sát dịch hại</h1>
                        <p class="mt-5 max-w-md text-emerald-50/90">Tạo tài khoản theo đúng vai trò để nhận giao diện và tính năng phù hợp với từng nhóm người dùng.</p>
                    </div>
                    <ul class="space-y-3 text-sm text-emerald-50/95">
                        <li class="rounded-xl border border-white/20 bg-white/10 px-4 py-3">Nông dân: Quản lý khu vực giám sát theo Thôn.</li>
                        <li class="rounded-xl border border-white/20 bg-white/10 px-4 py-3">Hộ gia đình: Định danh vị trí nhà cụ thể để nhận cảnh báo gần khu vực sống.</li>
                    </ul>
                </div>
            </section>

            <section class="glass p-5 sm:p-8 lg:p-10">
                <div class="mx-auto w-full max-w-md">
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-teal-900">Tài khoản người dùng</h2>
                        <a href="index.php" class="text-sm font-medium text-teal-700 hover:text-teal-900">Về trang chủ</a>
                    </div>

                    <?php if ($flashMsg !== '' && $flashType !== ''): ?>
                        <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?php echo htmlspecialchars($flashClassMap[$flashType], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-6 grid grid-cols-2 rounded-xl bg-slate-100 p-1">
                        <button id="tab-register" type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition">Đăng ký</button>
                        <button id="tab-login" type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition">Đăng nhập</button>
                    </div>

                    <form id="register-form" action="process_auth.php" method="POST" class="space-y-4 reveal">
                        <input type="hidden" name="action" value="register">

                        <div>
                            <label for="full_name" class="mb-1 block text-sm font-semibold text-slate-700">Họ tên</label>
                            <input id="full_name" name="full_name" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-200" placeholder="Ví dụ: Nguyễn Văn A">
                        </div>

                        <div>
                            <label for="username" class="mb-1 block text-sm font-semibold text-slate-700">Tên đăng nhập</label>
                            <input id="username" name="username" type="text" minlength="4" maxlength="50" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-200" placeholder="Ít nhất 4 ký tự">
                        </div>

                        <div>
                            <label for="password" class="mb-1 block text-sm font-semibold text-slate-700">Mật khẩu</label>
                            <input id="password" name="password" type="password" minlength="6" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-200" placeholder="Tối thiểu 6 ký tự">
                        </div>

                        <fieldset class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <legend class="px-1 text-sm font-semibold text-slate-700">Vai trò</legend>
                            <div class="mt-2 space-y-2">
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-white">
                                    <input class="role-radio h-4 w-4 accent-teal-600" type="radio" name="role" value="nong_dan" checked>
                                    <span class="text-sm">Nông dân</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-white">
                                    <input class="role-radio h-4 w-4 accent-teal-600" type="radio" name="role" value="ho_gia_dinh">
                                    <span class="text-sm">Hộ gia đình</span>
                                </label>
                            </div>
                        </fieldset>

                        <div id="nong-dan-fields" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <label for="khu_vuc" class="mb-1 block text-sm font-semibold text-emerald-800">Khu vực giám sát</label>
                            <select id="khu_vuc" name="khu_vuc" class="w-full rounded-xl border border-emerald-300 bg-white px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                <option value="Thôn 1">Thôn 1</option>
                                <option value="Thôn 2">Thôn 2</option>
                                <option value="Thôn 3">Thôn 3</option>
                            </select>
                        </div>

                        <div id="ho-gia-dinh-fields" class="hidden rounded-xl border border-sky-200 bg-sky-50 p-4">
                            <label for="dia_chi" class="mb-1 block text-sm font-semibold text-sky-800">Địa chỉ nhà / Tọa độ</label>
                            <input id="dia_chi" name="dia_chi" type="text" class="w-full rounded-xl border border-sky-300 bg-white px-4 py-3 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" placeholder="Ví dụ: Thôn 2, gần trường tiểu học hoặc 10.1234, 106.1234">
                            <input id="house_lat" name="house_lat" type="hidden" value="">
                            <input id="house_lng" name="house_lng" type="hidden" value="">

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p id="coordinate-preview" class="text-xs font-medium text-sky-700">Chưa chọn tọa độ trên bản đồ.</p>
                                <button id="btn-detect-location" type="button" class="rounded-lg border border-sky-300 bg-white px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100">
                                    Lấy vị trí hiện tại
                                </button>
                            </div>

                            <div class="mt-3">
                                <div id="house-map"></div>
                            </div>

                            <p class="mt-2 text-xs text-sky-700">Cách dùng: chạm hoặc click trên bản đồ để ghim điểm như app đặt xe. Bạn có thể kéo ghim để chỉnh chính xác.</p>
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-teal-700 to-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-teal-700/20 transition hover:scale-[1.01] hover:from-teal-800 hover:to-emerald-700">Tạo tài khoản</button>
                    </form>

                    <form id="login-form" action="process_auth.php" method="POST" class="hidden space-y-4 reveal">
                        <input type="hidden" name="action" value="login">

                        <div>
                            <label for="login_username" class="mb-1 block text-sm font-semibold text-slate-700">Tên đăng nhập</label>
                            <input id="login_username" name="username" type="text" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                        </div>

                        <div>
                            <label for="login_password" class="mb-1 block text-sm font-semibold text-slate-700">Mật khẩu</label>
                            <input id="login_password" name="password" type="password" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Đăng nhập</button>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const registerForm = document.getElementById('register-form');
        const loginForm = document.getElementById('login-form');
        const tabRegister = document.getElementById('tab-register');
        const tabLogin = document.getElementById('tab-login');

        const roleRadios = document.querySelectorAll('.role-radio');
        const nongDanFields = document.getElementById('nong-dan-fields');
        const hoGiaDinhFields = document.getElementById('ho-gia-dinh-fields');
        const khuVucSelect = document.getElementById('khu_vuc');
        const diaChiInput = document.getElementById('dia_chi');
        const houseLatInput = document.getElementById('house_lat');
        const houseLngInput = document.getElementById('house_lng');
        const coordinatePreview = document.getElementById('coordinate-preview');
        const detectLocationBtn = document.getElementById('btn-detect-location');

        let houseMap;
        let houseMarker;
        let mapInitialized = false;

        function updateCoordinatePreview(lat, lng) {
            const latText = Number(lat).toFixed(6);
            const lngText = Number(lng).toFixed(6);
            houseLatInput.value = latText;
            houseLngInput.value = lngText;
            coordinatePreview.textContent = `Đã chọn: ${latText}, ${lngText}`;
        }

        function setHouseLocation(lat, lng, focusMap = true) {
            if (!mapInitialized) {
                return;
            }

            const target = [lat, lng];
            houseMarker.setLatLng(target);
            if (focusMap) {
                houseMap.setView(target, 17);
            }
            updateCoordinatePreview(lat, lng);
        }

        async function reverseGeocode(lat, lng) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`, {
                    headers: {
                        Accept: 'application/json'
                    }
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (data && data.display_name && diaChiInput.value.trim() === '') {
                    diaChiInput.value = data.display_name;
                }
            } catch (error) {
                // Keep silent to avoid blocking registration when reverse geocode fails.
            }
        }

        function initializeHouseMap() {
            if (mapInitialized) {
                setTimeout(() => {
                    houseMap.invalidateSize();
                }, 120);
                return;
            }

            const defaultLat = 21.028511;
            const defaultLng = 105.804817;

            houseMap = L.map('house-map', {
                zoomControl: true
            }).setView([defaultLat, defaultLng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(houseMap);

            houseMarker = L.marker([defaultLat, defaultLng], {
                draggable: true
            }).addTo(houseMap);

            updateCoordinatePreview(defaultLat, defaultLng);

            houseMap.on('click', (event) => {
                const { lat, lng } = event.latlng;
                setHouseLocation(lat, lng, false);
                reverseGeocode(lat, lng);
            });

            houseMarker.on('dragend', () => {
                const markerPos = houseMarker.getLatLng();
                setHouseLocation(markerPos.lat, markerPos.lng, false);
                reverseGeocode(markerPos.lat, markerPos.lng);
            });

            mapInitialized = true;
        }

        function setActiveTab(tab) {
            const isRegister = tab === 'register';

            registerForm.classList.toggle('hidden', !isRegister);
            loginForm.classList.toggle('hidden', isRegister);

            tabRegister.className = `rounded-lg px-4 py-2 text-sm font-semibold transition ${isRegister ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`;
            tabLogin.className = `rounded-lg px-4 py-2 text-sm font-semibold transition ${!isRegister ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`;
        }

        function updateRoleUI() {
            let selectedRole = 'nong_dan';
            roleRadios.forEach((radio) => {
                if (radio.checked) {
                    selectedRole = radio.value;
                }
            });

            if (selectedRole === 'nong_dan') {
                nongDanFields.classList.remove('hidden');
                hoGiaDinhFields.classList.add('hidden');
                khuVucSelect.required = true;
                diaChiInput.required = false;
                diaChiInput.value = '';
                return;
            }

            if (selectedRole === 'ho_gia_dinh') {
                nongDanFields.classList.add('hidden');
                hoGiaDinhFields.classList.remove('hidden');
                khuVucSelect.required = false;
                diaChiInput.required = false;
                initializeHouseMap();
                return;
            }

            nongDanFields.classList.add('hidden');
            hoGiaDinhFields.classList.add('hidden');
            khuVucSelect.required = false;
            diaChiInput.required = false;
            diaChiInput.value = '';
            houseLatInput.value = '';
            houseLngInput.value = '';
            coordinatePreview.textContent = 'Chưa chọn tọa độ trên bản đồ.';
        }

        roleRadios.forEach((radio) => {
            radio.addEventListener('change', updateRoleUI);
        });

        tabRegister.addEventListener('click', () => setActiveTab('register'));
        tabLogin.addEventListener('click', () => setActiveTab('login'));

        detectLocationBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                coordinatePreview.textContent = 'Trình duyệt không hỗ trợ định vị GPS.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    initializeHouseMap();
                    setHouseLocation(lat, lng);
                    reverseGeocode(lat, lng);
                },
                () => {
                    coordinatePreview.textContent = 'Không thể lấy vị trí hiện tại, vui lòng chọn trên bản đồ.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });

        registerForm.addEventListener('submit', (event) => {
            let selectedRole = 'nong_dan';
            roleRadios.forEach((radio) => {
                if (radio.checked) {
                    selectedRole = radio.value;
                }
            });

            if (selectedRole !== 'ho_gia_dinh') {
                diaChiInput.setCustomValidity('');
                return;
            }

            const hasAddress = diaChiInput.value.trim() !== '';
            const hasCoordinates = houseLatInput.value.trim() !== '' && houseLngInput.value.trim() !== '';

            if (!hasAddress && !hasCoordinates) {
                event.preventDefault();
                diaChiInput.setCustomValidity('Vui lòng nhập địa chỉ hoặc chọn vị trí trên bản đồ.');
                diaChiInput.reportValidity();
                return;
            }

            diaChiInput.setCustomValidity('');
        });

        setActiveTab('<?php echo $activeTab === 'login' ? 'login' : 'register'; ?>');
        updateRoleUI();
    </script>
</body>
</html>
