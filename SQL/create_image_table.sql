-- Create images table

CREATE TABLE `elanregi_spice`.`images` ( `id` INT NOT NULL AUTO_INCREMENT , `ctime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , `carid` INT(10) NOT NULL , `featured` BOOLEAN NULL DEFAULT NULL , `image` VARCHAR(100) NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB;



