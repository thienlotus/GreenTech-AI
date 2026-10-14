-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 11, 2026 at 04:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nckh_giam_sat_dich_hai`
--

-- --------------------------------------------------------

--
-- Table structure for table `chi_tiet_dich_hai`
--

CREATE TABLE `chi_tiet_dich_hai` (
  `id` int(11) NOT NULL,
  `lich_su_id` int(11) DEFAULT NULL,
  `ten_loai_sau` varchar(100) NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 1,
  `do_chinh_xac` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chi_tiet_dich_hai`
--

INSERT INTO `chi_tiet_dich_hai` (`id`, `lich_su_id`, `ten_loai_sau`, `so_luong`, `do_chinh_xac`) VALUES
(105, 116, 'snail', 1, NULL),
(106, 117, 'snail', 1, NULL),
(107, 118, 'aphids', 2, NULL),
(108, 118, 'flea beetle', 2, NULL),
(109, 119, 'aphids', 2, NULL),
(110, 119, 'flea beetle', 2, NULL),
(111, 120, 'aphids', 2, NULL),
(112, 120, 'flea beetle', 2, NULL),
(113, 121, 'aphids', 2, NULL),
(114, 121, 'flea beetle', 2, NULL),
(115, 122, 'aphids', 2, NULL),
(116, 122, 'flea beetle', 2, NULL),
(117, 123, 'aphids', 2, NULL),
(118, 123, 'flea beetle', 2, NULL),
(119, 124, 'aphids', 2, NULL),
(120, 124, 'flea beetle', 2, NULL),
(121, 125, 'aphids', 2, NULL),
(122, 125, 'flea beetle', 2, NULL),
(123, 126, 'rice gall midge', 1, NULL),
(124, 127, 'rice gall midge', 1, NULL),
(125, 128, 'asiatic rice borer', 1, NULL),
(126, 129, 'asiatic rice borer', 1, NULL),
(127, 131, 'caterpillar', 1, NULL),
(128, 132, 'aphids', 2, NULL),
(129, 132, 'flea beetle', 2, NULL),
(130, 133, 'aphids', 2, NULL),
(131, 133, 'flea beetle', 2, NULL),
(132, 135, 'snail', 1, NULL),
(133, 136, 'snail', 1, NULL),
(134, 137, 'yellow rice borer', 1, NULL),
(135, 139, 'aphids', 2, NULL),
(136, 139, 'flea beetle', 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lich_su_quet`
--

CREATE TABLE `lich_su_quet` (
  `id` int(11) NOT NULL,
  `hinh_anh_goc` varchar(255) NOT NULL,
  `hinh_anh_ket_qua` varchar(255) DEFAULT NULL,
  `ngay_quet` datetime DEFAULT current_timestamp(),
  `trang_thai` varchar(50) DEFAULT 'Thành công',
  `khu_vuc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lich_su_quet`
--

INSERT INTO `lich_su_quet` (`id`, `hinh_anh_goc`, `hinh_anh_ket_qua`, `ngay_quet`, `trang_thai`, `khu_vuc`) VALUES
(116, '1773241727_snail.jpg', 'result_1773241727_snail.jpg', '2026-03-11 22:08:47', 'Thành công', 'Thôn 1 (Vùng Lúa nước)'),
(117, '1773241733_snail.jpg', 'result_1773241733_snail.jpg', '2026-03-11 22:08:53', 'Thành công', 'Thôn 1 (Vùng Lúa nước)'),
(118, '1773241740_bo.jpg', 'result_1773241740_bo.jpg', '2026-03-11 22:09:00', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(119, '1773241746_bo.jpg', 'result_1773241746_bo.jpg', '2026-03-11 22:09:07', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(120, '1773241751_bo.jpg', 'result_1773241751_bo.jpg', '2026-03-11 22:09:11', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(121, '1773241755_bo.jpg', 'result_1773241755_bo.jpg', '2026-03-11 22:09:15', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(122, '1773241760_bo.jpg', 'result_1773241760_bo.jpg', '2026-03-11 22:09:20', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(123, '1773241767_bo.jpg', 'result_1773241767_bo.jpg', '2026-03-11 22:09:28', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(124, '1773241774_bo.jpg', 'result_1773241774_bo.jpg', '2026-03-11 22:09:34', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(125, '1773241779_bo.jpg', 'result_1773241779_bo.jpg', '2026-03-11 22:09:40', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(126, '1773241801_Rice_Stemfly.jpg', 'result_1773241801_Rice_Stemfly.jpg', '2026-03-11 22:10:02', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(127, '1773241808_Rice_Stemfly.jpg', 'result_1773241808_Rice_Stemfly.jpg', '2026-03-11 22:10:08', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(128, '1773241812_rice_leaf_roller.jpg', 'result_1773241812_rice_leaf_roller.jpg', '2026-03-11 22:10:12', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(129, '1773241823_asiatic_rice_borer.jpg', 'result_1773241823_asiatic_rice_borer.jpg', '2026-03-11 22:10:24', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(130, '1773241832_grasshopper.jpg', 'result_1773241832_grasshopper.jpg', '2026-03-11 22:10:33', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(131, '1773241840_caterpillar.png', 'result_1773241840_caterpillar.png', '2026-03-11 22:10:41', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(132, '1773241845_bo.jpg', 'result_1773241845_bo.jpg', '2026-03-11 22:10:45', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(133, '1773241849_bo.jpg', 'result_1773241849_bo.jpg', '2026-03-11 22:10:49', 'Thành công', 'Thôn 3 (Vùng Cà chua)'),
(134, '1773241915_camera_capture.jpg', 'result_1773241915_camera_capture.jpg', '2026-03-11 22:11:56', 'Thành công', 'Thôn 1 (Vùng Lúa nước)'),
(135, '1773243133_snail.jpg', 'result_1773243133_snail.jpg', '2026-03-11 22:32:14', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(136, '1773243320_snail.jpg', 'result_1773243320_snail.jpg', '2026-03-11 22:35:20', 'Thành công', 'Thôn 2 (Vùng Cải xanh)'),
(137, '1773243522_yellow_rice_borer.jpg', 'result_1773243522_yellow_rice_borer.jpg', '2026-03-11 22:38:43', 'Thành công', 'Thôn 1 (Vùng Lúa nước)'),
(138, '1773243580_camera_capture.jpg', 'result_1773243580_camera_capture.jpg', '2026-03-11 22:39:41', 'Thành công', 'Thôn 1 (Vùng Lúa nước)'),
(139, '1773244006_bo.jpg', 'result_1773244006_bo.jpg', '2026-03-11 22:46:46', 'Thành công', 'Thôn 1 (Vùng Lúa nước)');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chi_tiet_dich_hai`
--
ALTER TABLE `chi_tiet_dich_hai`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lich_su_id` (`lich_su_id`);

--
-- Indexes for table `lich_su_quet`
--
ALTER TABLE `lich_su_quet`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chi_tiet_dich_hai`
--
ALTER TABLE `chi_tiet_dich_hai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `lich_su_quet`
--
ALTER TABLE `lich_su_quet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chi_tiet_dich_hai`
--
ALTER TABLE `chi_tiet_dich_hai`
  ADD CONSTRAINT `chi_tiet_dich_hai_ibfk_1` FOREIGN KEY (`lich_su_id`) REFERENCES `lich_su_quet` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ho_ten` varchar(100) NOT NULL,
  `so_dien_thoai` varchar(20) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `khu_vuc` varchar(100) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'farmer' COMMENT 'farmer: Nông dân, home: Gia đình',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `so_dien_thoai` (`so_dien_thoai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;