-- phpMyAdmin SQL Dump
-- version 4.9.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 14, 2020 at 03:56 PM
-- Server version: 5.7.26
-- PHP Version: 7.4.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `elanregi_spice`
--

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `page` varchar(100) NOT NULL,
  `title` varchar(50) NOT NULL,
  `private` int(11) NOT NULL DEFAULT '0',
  `re_auth` int(1) NOT NULL DEFAULT '0',
  `core` int(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `page`, `title`, `private`, `re_auth`, `core`) VALUES
(1, 'index.php', 'Home', 0, 0, 1),
(2, 'z_us_root.php', '', 0, 0, 1),
(3, 'users/account.php', 'Account Dashboard', 1, 0, 1),
(4, 'users/admin.php', 'Admin Dashboard', 1, 0, 1),
(11, 'users/edit_profile.php', 'Edit Profile', 1, 0, 0),
(14, 'users/forgot_password.php', 'Forgotten Password', 0, 0, 1),
(15, 'users/forgot_password_reset.php', 'Reset Forgotten Password', 0, 0, 1),
(16, 'users/index.php', 'Home', 0, 0, 1),
(17, 'users/init.php', '', 0, 0, 1),
(18, 'users/join.php', 'Join', 0, 0, 1),
(19, 'users/joinThankYou.php', 'Join', 0, 0, 0),
(20, 'users/login.php', 'Login', 0, 0, 1),
(21, 'users/logout.php', 'Logout', 0, 0, 1),
(22, 'users/profile.php', 'Profile', 1, 0, 0),
(24, 'users/user_settings.php', 'User Settings', 1, 0, 1),
(25, 'users/verify.php', 'Account Verification', 0, 0, 1),
(26, 'users/verify_resend.php', 'Account Verification', 0, 0, 1),
(27, 'users/view_all_users.php', 'View All Users', 1, 0, 0),
(28, 'usersc/empty.php', '', 0, 0, 0),
(45, 'users/maintenance.php', 'Maintenance', 0, 0, 1),
(68, 'users/update.php', 'Update Manager', 1, 0, 1),
(81, 'users/admin_pin.php', 'Verification PIN Set', 1, 0, 1),
(98, 'usersc/account.php', 'Account Dashboard', 1, 0, 0),
(106, 'usersc/user_settings.php', 'User Settings', 1, 0, 0),
(107, 'usersc/admin_user.php', 'User Manager', 1, 0, 0),
(122, 'app/list_cars.php', 'List Cars', 0, 0, 0),
(123, 'app/identification.php', 'Identification Guide', 0, 0, 0),
(124, 'app/contact.php', 'Feedback', 1, 0, 0),
(125, 'app/edit_car.php', 'UpdateCar', 1, 0, 0),
(126, 'app/car_details.php', 'Car Details', 0, 0, 0),
(127, 'app/manage_cars.php', 'Manage Cars', 1, 0, 0),
(129, 'app/send_form_email.php', '', 1, 0, 0),
(130, 'app/statistics.php', 'Statistics', 0, 0, 0),
(131, 'usersc/admin_users.php', 'User Manager', 1, 1, 0),
(133, 'app/mapmarkers2.xml.php', 'Map data to XML', 0, 0, 0),
(146, 'stories/index.php', 'User Submitted Car Histories and Stories', 0, 0, 0),
(154, 'FIX/geocode.php', '', 1, 0, 0),
(155, 'FIX/geocode2.php', '', 1, 0, 0),
(157, 'FIX/index.php', '', 1, 0, 0),
(158, 'FIX/0-update_car_history_triggers.php', '', 1, 0, 0),
(159, 'FIX/1-update_car_with_owner.php', '', 1, 0, 0),
(160, 'FIX/2-update_car_history_owner.php', '', 1, 0, 0),
(161, 'FIX/3-update_car_insert_dates.php', '', 1, 0, 0),
(162, 'FIX/4-delete-admin-hist-records.php', '', 1, 0, 0),
(165, 'FIX/5-fix-dates.php', '', 1, 0, 0),
(166, 'FIX/6-create-car-verify.php', '', 1, 0, 0),
(172, 'FIX/7-update-car-verify.php', '', 1, 0, 0),
(177, 'app/verify/index.php', '', 1, 0, 0),
(178, 'app/verify/_email_template.php', '', 1, 0, 0),
(179, 'app/verify/send_email.php', '', 1, 0, 0),
(180, 'app/verify/verify_car.php', '', 0, 0, 0),
(185, 'stories/brian_walton/index.php', '', 0, 0, 0),
(187, 'stories/SGO_2F/index.php', '', 0, 0, 0),
(188, 'app/list_factory.php', '', 0, 0, 0),
(189, 'FIX/8-load_factory_data.php', '', 1, 0, 0),
(190, 'FIX/9-reload_pages_menus.php', '', 1, 0, 0),
(195, 'app/validate.php', '', 1, 0, 0),
(196, 'error/index.php', '', 0, 0, 0),
(197, 'app/fileupload.php', '', 1, 0, 0),
(198, 'app/image_sav.php', '', 1, 0, 0),
(199, 'FIX/10-update-car-table.php', '', 1, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
