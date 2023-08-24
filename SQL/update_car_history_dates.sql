UPDATE `cars_hist`
SET mtime = timestamp
WHERE `operation` LIKE 'UPDATE' AND `mtime` IS NULL;

UPDATE `cars_hist`
SET ctime = timestamp, mtime=timestamp
WHERE `operation` LIKE 'INSERT' AND `ctime` IS NULL