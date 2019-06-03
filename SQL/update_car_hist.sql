-- Fix some bad date
UPDATE cars_hist SET solddate = '2008-07-03' WHERE solddate = '2208-07-03';

-- Change ID to user_id and add a unique field
ALTER TABLE cars_hist DROP INDEX id;
ALTER TABLE cars_hist ADD id INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id(10));
ALTER TABLE cars_hist CHANGE id car_id INT(10) UNSIGNED NOT NULL;

-- Add the User fields 
ALTER TABLE cars_hist ADD COLUMN user_id	int(11);
ALTER TABLE cars_hist ADD COLUMN email	varchar(155);
ALTER TABLE cars_hist ADD COLUMN fname	varchar(255);
ALTER TABLE cars_hist ADD COLUMN lname	varchar(255);		
ALTER TABLE cars_hist ADD COLUMN join_date	datetime;		
ALTER TABLE cars_hist ADD COLUMN city	varchar(100);
ALTER TABLE cars_hist ADD COLUMN state	varchar(100);	
ALTER TABLE cars_hist ADD COLUMN country	varchar(100);
ALTER TABLE cars_hist ADD COLUMN lat	float;
ALTER TABLE cars_hist ADD COLUMN lon	float;
ALTER TABLE cars_hist ADD COLUMN website	varchar(100);