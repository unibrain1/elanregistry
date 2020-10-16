-- phpMyAdmin SQL Dump
-- version 4.9.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 16, 2020 at 05:26 PM
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
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` int(10) NOT NULL,
  `menu_title` varchar(255) NOT NULL,
  `parent` int(10) NOT NULL,
  `dropdown` int(1) NOT NULL,
  `logged_in` int(1) NOT NULL,
  `display_order` int(10) NOT NULL,
  `label` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `icon_class` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `menu_title`, `parent`, `dropdown`, `logged_in`, `display_order`, `label`, `link`, `icon_class`) VALUES
(15, 'main', 43, 0, 1, 1000, '{{hr}}', '', ''),
(44, 'main', 2, 0, 1, 1, 'Manage Cars', 'app/manage_cars.php', 'fa fa-fw fa-car'),
(9, 'main', 2, 0, 1, 4, '{{dashboard}}', 'users/admin.php', 'fa fa-fw fa-cogs'),
(45, 'main', 2, 0, 1, 2, '{{hr}}', '#', ''),
(6, 'main', -1, 0, 0, 99, '{{login}}', 'users/login.php', 'fa fa-fw fa-sign-in-alt'),
(5, 'main', -1, 0, 0, 50, '{{register}}', 'users/join.php', 'fa fa-fw fa-plus-square'),
(4, 'main', -1, 1, 0, 60, '{{help}}', '', 'fa fa-fw fa-life-ring'),
(3, 'main', 43, 0, 1, 110, '{{username}}', 'users/account.php', 'fa fa-fw fa-user'),
(2, 'main', -1, 1, 1, 140, 'Admin', '', 'fa fa-fw fa-cogs'),
(37, 'main', -1, 0, 0, 40, 'Identification Guide', 'app/identification.php', 'fa fa-fw fa-binoculars'),
(25, 'main', -1, 0, 0, 10, 'List Cars', 'app/list_cars.php', 'fa fa-fw fa-car'),
(31, 'main', 30, 1, 1, 99999, 'Dashboard', 'users/admin.php', ''),
(29, 'main', 27, 0, 1, 1, 'List Cars', 'app/list_cars.php', ''),
(39, 'main', -1, 0, 1, 11, 'List Cars', 'app/list_cars.php', 'fa fa-fw fa-car'),
(34, 'main', 30, 1, 1, 99999, '{{logout}}', 'users/logout.php', 'fa fa-fw fa-sign-out'),
(16, 'main', 43, 0, 1, 99999, '{{logout}}', 'users/logout.php', 'fa fa-fw fa-sign-out'),
(17, 'main', -1, 0, 0, 0, '{{home}}', '', 'fa fa-fw fa-home'),
(19, 'main', 4, 0, 0, 1, '{{forgot}}', 'users/forgot_password.php', 'fa fa-fw fa-wrench'),
(21, 'main', -1, 0, 1, 130, '{{messages}}', '', ''),
(41, 'main', -1, 0, 1, 30, 'Identification Guide', 'app/identification.php', 'fa fa-fw fa-binoculars'),
(38, 'main', -1, 0, 1, 10, '{{home}}', '#', 'fa fa-fw fa-home'),
(42, 'main', -1, 0, 1, 40, 'Add Car', 'app/edit_car.php', 'fa fa-fw fa-plus'),
(43, 'main', -1, 1, 1, 99999, '{{account}}', '#', 'fa fa-fw fa-user'),
(47, 'main', -1, 0, 1, 100, 'Feedback', 'app/contact.php', 'fa fa-fw fa-comments'),
(48, 'main', -1, 0, 0, 20, 'Statistics', 'app/statistics.php', 'fa fa-fw fa-pie-chart'),
(49, 'main', -1, 0, 1, 20, 'Statistics', 'app/statistics.php', 'fa fa-fw fa-pie-chart'),
(50, 'main', -1, 0, 0, 30, 'Stories', 'stories/', 'fa fa-fw fa-book'),
(51, 'main', -1, 0, 1, 20, 'Stories', 'stories/', 'fa fa-fw fa-book'),
(53, 'main', 2, 0, 1, 20, 'Fixes', 'FIX/', 'fa fa-fw fa-wrench'),
(54, 'main', -1, 0, 1, 35, 'Factory Data', 'app/list_factory.php', 'fa fa-fw fa-list-alt'),
(55, 'main', -1, 0, 0, 41, 'Factory Data', 'app/list_factory.php', 'fa fa-fw fa-list-alt'),
(56, 'main', 2, 0, 1, 30, 'Email Verification', 'app/verify', 'fa fa-fw fa-envelope-o'),
(57, 'main', 2, 0, 1, 99999, 'Admin User', 'users/admin.php?view=users', 'fa fa-fw fa-user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
