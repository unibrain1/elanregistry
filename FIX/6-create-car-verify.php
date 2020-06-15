<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the DB
$db = DB::getInstance();

echo "Updating cars.mtime to auto increment timestamp<br>";
$db->query("ALTER TABLE cars CHANGE mtime mtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

echo "Creating car verification information<br>";

$db->query("ALTER TABLE `cars` ADD `vericode` VARCHAR(32) NOT NULL AFTER `mtime`, ADD `last_verified` TIMESTAMP NULL DEFAULT NULL AFTER `vericode`");

// Now update the users_carview

$db->query("
DROP VIEW
    `users_carsview`;
CREATE VIEW `users_carsview` AS(
    SELECT
        `c`.`id` AS `id`,
        `c`.`username` AS `username`,
        `c`.`ctime` AS `ctime`,
        `c`.`mtime` AS `mtime`,
        `c`.`vericode` AS `vericode`,
        `c`.`last_verified` AS `last_verified`,
        `c`.`ModifiedBy` AS `ModifiedBy`,
        `c`.`model` AS `model`,
        `c`.`series` AS `series`,
        `c`.`variant` AS `variant`,
        `c`.`year` AS `year`,
        `c`.`type` AS `type`,
        `c`.`chassis` AS `chassis`,
        `c`.`color` AS `color`,
        `c`.`engine` AS `engine`,
        `c`.`purchasedate` AS `purchasedate`,
        `c`.`solddate` AS `solddate`,
        `c`.`comments` AS `comments`,
        `c`.`image` AS `image`,
        `u`.`id` AS `user_id`,
        `u`.`email` AS `email`,
        `u`.`fname` AS `fname`,
        `u`.`lname` AS `lname`,
        `u`.`join_date` AS `join_date`,
        `u`.`last_login` AS `last_login`,
        `u`.`logins` AS `logins`,
        `u`.`city` AS `city`,
        `u`.`state` AS `state`,
        `u`.`country` AS `country`,
        `u`.`lat` AS `lat`,
        `u`.`lon` AS `lon`,
        `u`.`website` AS `website`
    FROM
        (
            (
                `cars` `c`
            JOIN `car_user` `cu` ON
                ((`c`.`id` = `cu`.`carid`))
            )
        JOIN `usersview` `u` ON
            ((`cu`.`userid` = `u`.`id`))
        )
)
UNION
    (
    SELECT
        `c`.`id` AS `id`,
        `c`.`username` AS `username`,
        `c`.`ctime` AS `ctime`,
        `c`.`mtime` AS `mtime`,
        `c`.`vericode` AS `last_verified`,
        `c`.`mtime` AS `mtime`,
        `c`.`ModifiedBy` AS `ModifiedBy`,
        `c`.`model` AS `model`,
        `c`.`series` AS `series`,
        `c`.`variant` AS `variant`,
        `c`.`year` AS `year`,
        `c`.`type` AS `type`,
        `c`.`chassis` AS `chassis`,
        `c`.`color` AS `color`,
        `c`.`engine` AS `engine`,
        `c`.`purchasedate` AS `purchasedate`,
        `c`.`solddate` AS `solddate`,
        `c`.`comments` AS `comments`,
        `c`.`image` AS `image`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`,
        NULL AS `NULL`
    FROM
        (
            `cars` `c`
        LEFT JOIN `car_user` `cu` ON
            ((`c`.`id` = `cu`.`carid`))
        )
    WHERE
        ISNULL(`cu`.`carid`)
);
");
