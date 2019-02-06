-- Modify users_carsview to add Model

-- View for all car and cars with users
CREATE OR REPLACE  VIEW users_carsview  AS   (select
  c.*, 
  u.id as user_id, u.email, u.fname, u.lname, u.join_date, u.last_login, u.logins, u.city,u.state,u.country,u.lat,u.lon,u.website
	FROM cars c
        INNER JOIN car_user cu
            ON c.id = cu.carid
        INNER JOIN usersView u
            ON cu.userid = u.id
)
    
union
(
    SELECT c.*,
    null,null,null,null,null,null,null,null,null,null,null,null,null
    FROM cars c
    left outer join car_user cu 
    	on c.id = cu.carid 
    where cu.carid is null
)


