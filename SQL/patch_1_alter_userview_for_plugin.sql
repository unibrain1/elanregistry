ALTER
 ALGORITHM = UNDEFINED
DEFINER=`root`@`localhost` 
 SQL SECURITY DEFINER
 VIEW `usersview`
 AS select 
`elanregi_spice`.`users`.`id` AS `id`,
`elanregi_spice`.`users`.`email` AS `email`,
`elanregi_spice`.`users`.`fname` AS `fname`,
`elanregi_spice`.`users`.`lname` AS `lname`,
`elanregi_spice`.`users`.`username` AS `username`,
`elanregi_spice`.`users`.`join_date` AS `join_date`,
`elanregi_spice`.`users`.`last_login` AS `last_login`,
`elanregi_spice`.`users`.`logins` AS `logins`,
`elanregi_spice`.`users`.`force_pr` AS `force_pr`,
`elanregi_spice`.`users`.`email_verified` AS `email_verified`,
`elanregi_spice`.`users`.`permissions` AS `permissions`,
`elanregi_spice`.`profiles`.`city` AS `city`,
`elanregi_spice`.`profiles`.`state` AS `state`,
`elanregi_spice`.`profiles`.`country` AS `country`,
`elanregi_spice`.`profiles`.`lat` AS `lat`,
`elanregi_spice`.`profiles`.`lon` AS `lon`,
`elanregi_spice`.`profiles`.`website` AS `website` 
from 
(`elanregi_spice`.`users` join `elanregi_spice`.`profiles` on
	(
		(`elanregi_spice`.`users`.`id` = `elanregi_spice`.`profiles`.`user_id`)
	)
)