
--
-- Table structure for table `car_user_hist`
--

CREATE TABLE `car_user_hist` (
  `id` int(11) NOT NULL,
  `operation` varchar(32) NOT NULL,
  `carid` int(11) UNSIGNED NOT NULL,
  `userid` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



--
-- Indexes for table `car_hist`
--
ALTER TABLE `car_user_hist`
  ADD UNIQUE KEY `id` (`id`) USING BTREE;


--
-- AUTO_INCREMENT for table `car_hist`
--
ALTER TABLE `car_user_hist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;




--
-- Triggers `car`
--
DELIMITER $$
CREATE TRIGGER `car_user_update` AFTER UPDATE ON `car_user` FOR EACH ROW 
INSERT INTO
    car_user_hist(
        operation,
        carid,
        userid
    )
VALUES
    (
        'UPDATE',
        OLD.carid,
        OLD.userid
    );

$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `car_user_insert` AFTER INSERT ON `car_user` FOR EACH ROW 
INSERT INTO
    car_user_hist(
        operation,
        carid,
        userid
    )
VALUES
    (
        'INSERT',
        NEW.carid,
        NEW.userid
    );
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `car_user_delete` AFTER DELETE ON `car_user` FOR EACH ROW 
INSERT INTO
    car_user_hist(
        operation,
        carid,
        userid
    )
VALUES
    (
        'DELETE',
        OLD.carid,
        OLD.userid
    );


$$
DELIMITER ;