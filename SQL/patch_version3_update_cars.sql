SET
    SQL_MODE = 'ALLOW_INVALID_DATES';

ALTER TABLE `cars` CHANGE `image` `image` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL
ALTER TABLE `cars_hist` CHANGE `image` `image` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL
/* Add columns to cars */
ALTER TABLE
    `cars`
ADD
    `user_id` INT(11) NULL DEFAULT NULL,
ADD
    `email` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `fname` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `lname` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `join_date` DATETIME NULL DEFAULT NULL,
ADD
    `city` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `state` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `country` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `lat` FLOAT NULL DEFAULT NULL,
ADD
    `lon` FLOAT NULL DEFAULT NULL,
ADD
    `website` VARCHAR(100) NULL DEFAULT NULL;

/* Add columns to cars and rename id to cars_id.  */

ALTER TABLE
    `cars_hist` CHANGE `id` `car_id` INT(11) UNSIGNED NOT NULL,
ADD
    `id` INT(11) NOT NULL AUTO_INCREMENT FIRST,
ADD
    `ctime` TIMESTAMP NULL
AFTER
    `username`,
ADD
    `mtime` TIMESTAMP NULL
AFTER
    `ctime`,
ADD
    `user_id` INT(11) NULL DEFAULT NULL,
ADD
    `email` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `fname` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `lname` VARCHAR(155) NULL DEFAULT NULL,
ADD
    `join_date` DATETIME NULL DEFAULT NULL,
ADD
    `city` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `state` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `country` VARCHAR(100) NULL DEFAULT NULL,
ADD
    `lat` FLOAT NULL DEFAULT NULL,
ADD
    `lon` FLOAT NULL DEFAULT NULL,
ADD
    `website` VARCHAR(100) NULL DEFAULT NULL,
    CHANGE COLUMN modifiedby ModifiedBy VARCHAR(30)
AFTER
    mtime,
    CHANGE COLUMN timestamp timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
AFTER
    website;

    ALTER TABLE `elanregi_spice`.`cars_hist` DROP INDEX `id`, ADD UNIQUE `id` (`id`) USING BTREE;



    /* Update the triggers for update */
DROP TRIGGER IF EXISTS `cars_update`;
DROP TRIGGER IF EXISTS `cars_delete`;
DROP TRIGGER IF EXISTS `cars_insert`;

DELIMITER $$ 

CREATE TRIGGER `cars_update` AFTER UPDATE ON `cars` FOR EACH ROW BEGIN
INSERT INTO
    cars_hist(
        operation,
        car_id,
        username,
        ctime,
        mtime,
        ModifiedBy,
        model,
        series,
        variant,
        year,
        type,
        chassis,
        color,
        engine,
        purchasedate,
        solddate,
        comments,
        image,
        user_id,
        email,
        fname,
        lname,
        join_date,
        city,
        state,
        country,
        lat,
        lon,
        website
    )
VALUES
    (
        'UPDATE',
        OLD.id,
        OLD.username,
        OLD.ctime,
        OLD.mtime,
        OLD.ModifiedBy,
        OLD.model,
        OLD.series,
        OLD.variant,
        OLD.year,
        OLD.type,
        OLD.chassis,
        OLD.color,
        OLD.engine,
        OLD.purchasedate,
        OLD.solddate,
        OLD.comments,
        OLD.image,
        OLD.user_id,
        OLD.email,
        OLD.fname,
        OLD.lname,
        OLD.join_date,
        OLD.city,
        OLD.state,
        OLD.country,
        OLD.lat,
        OLD.lon,
        OLD.website
    );

END 
$$ 

CREATE TRIGGER `cars_delete` AFTER DELETE ON `cars` FOR EACH ROW BEGIN
INSERT INTO
    cars_hist(
        operation,
        car_id,
        username,
        ctime,
        mtime,
        ModifiedBy,
        model,
        series,
        variant,
        year,
        type,
        chassis,
        color,
        engine,
        purchasedate,
        solddate,
        comments,
        image,
        user_id,
        email,
        fname,
        lname,
        join_date,
        city,
        state,
        country,
        lat,
        lon,
        website
    )
VALUES
    (
        'DELETE',
        OLD.id,
        OLD.username,
        OLD.ctime,
        OLD.mtime,
        OLD.ModifiedBy,
        OLD.model,
        OLD.series,
        OLD.variant,
        OLD.year,
        OLD.type,
        OLD.chassis,
        OLD.color,
        OLD.engine,
        OLD.purchasedate,
        OLD.solddate,
        OLD.comments,
        OLD.image,
        OLD.user_id,
        OLD.email,
        OLD.fname,
        OLD.lname,
        OLD.join_date,
        OLD.city,
        OLD.state,
        OLD.country,
        OLD.lat,
        OLD.lon,
        OLD.website
    );

END 
$$ 

CREATE TRIGGER `cars_insert` AFTER INSERT ON `cars` FOR EACH ROW BEGIN
INSERT INTO
    cars_hist(
        operation,
        car_id,
        username,
        ctime,
        mtime,
        ModifiedBy,
        model,
        series,
        variant,
        year,
        type,
        chassis,
        color,
        engine,
        purchasedate,
        solddate,
        comments,
        image,
        user_id,
        email,
        fname,
        lname,
        join_date,
        city,
        state,
        country,
        lat,
        lon,
        website
    )
VALUES
    (
        'INSERT',
        NEW.id,
        NEW.username,
        NEW.ctime,
        NEW.mtime,
        NEW.ModifiedBy,
        NEW.model,
        NEW.series,
        NEW.variant,
        NEW.year,
        NEW.type,
        NEW.chassis,
        NEW.color,
        NEW.engine,
        NEW.purchasedate,
        NEW.solddate,
        NEW.comments,
        NEW.image,
        NEW.user_id,
        NEW.email,
        NEW.fname,
        NEW.lname,
        NEW.join_date,
        NEW.city,
        NEW.state,
        NEW.country,
        NEW.lat,
        NEW.lon,
        NEW.website
    );

END 
$$ 

