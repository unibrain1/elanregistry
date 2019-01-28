DELETE FROM elanregi_spice.users WHERE id > 20;
DELETE FROM elanregi_spice.cars WHERE id > 20;
DELETE FROM elanregi_spice.car_user WHERE id > 20;
DELETE FROM elanregi_spice.cars_hist WHERE id > 20;
DELETE FROM elanregi_spice.profiles WHERE `user_id` > 20
DELETE FROM elanregi_spice.cars_hist WHERE tid > 20;



ALTER TABLE elanregi_spice.users  AUTO_INCREMENT = 10
ALTER TABLE elanregi_spice.cars  AUTO_INCREMENT = 10

