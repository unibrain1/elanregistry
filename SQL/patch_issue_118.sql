/* Fix bad timestamps post mySQL 5.7 strict mode */

/* Fix CARS */

SET
    SQL_MODE
= 'ALLOW_INVALID_DATES';
ALTER TABLE
    `cars` CHANGE `ctime` `ctime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHANGE `purchasedate` `purchasedate` DATE NULL,
    CHANGE `solddate` `solddate` DATE NULL;
UPDATE
    `cars`
SET
    `mtime` = '1970-01-01 00:00:01'
WHERE
    `mtime` = '0000-00-00 00:00:00';

/* Fix CARS_HIST */

ALTER TABLE
    `cars_hist`
    CHANGE `purchasedate` `purchasedate` DATE NULL,
    CHANGE `solddate` `solddate` DATE NULL;
UPDATE
    `cars_hist`
SET
    `purchasedate` = NULL
WHERE
    `purchasedate` = '0000-00-00';
UPDATE
    `cars_hist`
SET
    `solddate` = NULL
WHERE
    `solddate` = '0000-00-00';