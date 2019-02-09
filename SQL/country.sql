-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 09, 2019 at 06:56 PM
-- Server version: 5.7.23
-- PHP Version: 7.1.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `elanregi_reg`
--

-- --------------------------------------------------------

--
-- Table structure for table `country`
--

CREATE TABLE `country` (
  `id` int(3) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `country`
--

INSERT INTO `country` (`id`, `name`) VALUES
(2, 'Australia'),
(3, 'Canada'),
(4, 'Italy'),
(5, 'Netherlands'),
(6, 'New Zealand'),
(7, 'Norway'),
(8, 'South Africa'),
(9, 'Sweden'),
(10, 'United Kingdom'),
(11, 'United States'),
(12, '-------------'),
(13, 'Afghanistan'),
(14, 'Albania'),
(15, 'Algeria'),
(16, 'American Samoa'),
(17, 'Andorra'),
(18, 'Angola'),
(19, 'Anguilla'),
(20, 'Antarctica'),
(21, 'Antigua and Barbuda'),
(22, 'Argentina'),
(23, 'Armenia'),
(24, 'Aruba'),
(25, 'Austria'),
(26, 'Azerbaijan'),
(27, 'Bahamas'),
(28, 'Bahrain'),
(29, 'Bangladesh'),
(30, 'Barbados'),
(31, 'Belarus'),
(32, 'Belgium'),
(33, 'Belize'),
(34, 'Benin'),
(35, 'Bermuda'),
(36, 'Bhutan'),
(37, 'Bolivia'),
(38, 'Bosnia and Herzegovina'),
(39, 'Botswana'),
(40, 'Bouvet Island'),
(41, 'Brazil'),
(42, 'British Indian Ocean Territory'),
(43, 'British Virgin Islands'),
(44, 'Brunei'),
(45, 'Bulgaria'),
(46, 'Burkina Faso'),
(47, 'Burundi'),
(48, 'Cambodia'),
(49, 'Cameroon'),
(50, 'Cape Verde'),
(51, 'Cayman Islands'),
(52, 'Central African Republic'),
(53, 'Chad'),
(54, 'Chile'),
(55, 'China'),
(56, 'Christmas Island'),
(57, 'Cocos Islands'),
(58, 'Colombia'),
(59, 'Comoros'),
(60, 'Congo'),
(61, 'Cook Islands'),
(62, 'Costa Rica'),
(63, 'Croatia'),
(64, 'Cuba'),
(65, 'Cyprus'),
(66, 'Czech Republic'),
(67, 'Denmark'),
(68, 'Djibouti'),
(69, 'Dominica'),
(70, 'Dominican Republic'),
(71, 'East Timor'),
(72, 'Ecuador'),
(73, 'Egypt'),
(74, 'El Salvador'),
(75, 'Equatorial Guinea'),
(76, 'Eritrea'),
(77, 'Estonia'),
(78, 'Ethiopia'),
(79, 'Falkland Islands'),
(80, 'Faroe Islands'),
(81, 'Fiji'),
(82, 'Finland'),
(83, 'France'),
(84, 'French Guiana'),
(85, 'French Polynesia'),
(86, 'French Southern Territories'),
(87, 'Gabon'),
(88, 'Gambia'),
(89, 'Georgia'),
(90, 'Germany'),
(91, 'Ghana'),
(92, 'Gibraltar'),
(93, 'Greece'),
(94, 'Greenland'),
(95, 'Grenada'),
(96, 'Guadeloupe'),
(97, 'Guam'),
(98, 'Guatemala'),
(99, 'Guinea'),
(100, 'Guinea-Bissau'),
(101, 'Guyana'),
(102, 'Haiti'),
(103, 'Heard and McDonald Islands'),
(104, 'Honduras'),
(105, 'Hong Kong'),
(106, 'Hungary'),
(107, 'Iceland'),
(108, 'India'),
(109, 'Indonesia'),
(110, 'Iran'),
(111, 'Iraq'),
(112, 'Ireland'),
(113, 'Israel'),
(114, 'Ivory Coast'),
(115, 'Jamaica'),
(116, 'Japan'),
(117, 'Jordan'),
(118, 'Kazakhstan'),
(119, 'Kenya'),
(120, 'Kiribati'),
(121, 'North Korea'),
(122, 'South Korea'),
(123, 'Kuwait'),
(124, 'Kyrgyzstan'),
(125, 'Laos'),
(126, 'Latvia'),
(127, 'Lebanon'),
(128, 'Lesotho'),
(129, 'Liberia'),
(130, 'Libya'),
(131, 'Liechtenstein'),
(132, 'Lithuania'),
(133, 'Luxembourg'),
(134, 'Macau'),
(135, 'Macedonia'),
(136, 'Madagascar'),
(137, 'Malawi'),
(138, 'Malaysia'),
(139, 'Maldives'),
(140, 'Mali'),
(141, 'Malta'),
(142, 'Marshall Islands'),
(143, 'Martinique'),
(144, 'Mauritania'),
(145, 'Mauritius'),
(146, 'Mayotte'),
(147, 'Mexico'),
(148, 'Micronesia'),
(149, 'Moldova'),
(150, 'Monaco'),
(151, 'Mongolia'),
(152, 'Montserrat'),
(153, 'Morocco'),
(154, 'Mozambique'),
(155, 'Myanmar'),
(156, 'Namibia'),
(157, 'Nauru'),
(158, 'Nepal'),
(159, 'Netherlands Antilles'),
(160, 'New Caledonia'),
(161, 'Nicaragua'),
(162, 'Niger'),
(163, 'Nigeria'),
(164, 'Niue'),
(165, 'Norfolk Island'),
(166, 'Northern Mariana Islands'),
(167, 'Oman'),
(168, 'Pakistan'),
(169, 'Palau'),
(170, 'Panama'),
(171, 'Papua New Guinea'),
(172, 'Paraguay'),
(173, 'Peru'),
(174, 'Philippines'),
(175, 'Pitcairn Island'),
(176, 'Poland'),
(177, 'Portugal'),
(178, 'Puerto Rico'),
(179, 'Qatar'),
(180, 'Reunion'),
(181, 'Romania'),
(182, 'Russia'),
(183, 'Rwanda'),
(184, 'S. Georgia and S. Sandwich Isls.'),
(185, 'Saint Kitts & Nevis'),
(186, 'Saint Lucia'),
(187, 'Saint Vincent and The Grenadines'),
(188, 'Samoa'),
(189, 'San Marino'),
(190, 'Sao Tome and Principe'),
(191, 'Saudi Arabia'),
(192, 'Senegal'),
(193, 'Seychelles'),
(194, 'Sierra Leone'),
(195, 'Singapore'),
(196, 'Slovakia'),
(197, 'Slovenia'),
(198, 'Somalia'),
(199, 'Spain'),
(200, 'Sri Lanka'),
(201, 'St. Helena'),
(202, 'St. Pierre and Miquelon'),
(203, 'Sudan'),
(204, 'Suriname'),
(205, 'Svalbard and Jan Mayen Islands'),
(206, 'Swaziland'),
(207, 'Switzerland'),
(208, 'Syria'),
(209, 'Taiwan'),
(210, 'Tajikistan'),
(211, 'Tanzania'),
(212, 'Thailand'),
(213, 'Togo'),
(214, 'Tokelau'),
(215, 'Tonga'),
(216, 'Trinidad and Tobago'),
(217, 'Tunisia'),
(218, 'Turkey'),
(219, 'Turkmenistan'),
(220, 'Turks and Caicos Islands'),
(221, 'Tuvalu'),
(222, 'U.S. Minor Outlying Islands'),
(223, 'Uganda'),
(224, 'Ukraine'),
(225, 'United Arab Emirates'),
(226, 'Uruguay'),
(227, 'Uzbekistan'),
(228, 'Vanuatu'),
(229, 'Vatican City'),
(230, 'Venezuela'),
(231, 'Vietnam'),
(232, 'Virgin Islands'),
(233, 'Wallis and Futuna Islands'),
(234, 'Western Sahara'),
(235, 'Yemen'),
(236, 'Serbia and Montenegro'),
(237, 'Zaire'),
(238, 'Zambia'),
(239, 'Zimbabwe');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `country`
--
ALTER TABLE `country`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `country`
--
ALTER TABLE `country`
  MODIFY `id` int(3) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=240;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
