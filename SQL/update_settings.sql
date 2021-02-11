ALTER TABLE `settings` ADD `elan_backup_age` INT NOT NULL;
ALTER TABLE `settings` ADD `elan_google_maps_key` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_google_geo_key` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_image_dir` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_image_max` INT NOT NULL;

ALTER TABLE `settings` ADD `elan_jquery_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_jquery_ui_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_bootstrap_js_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_bootstrap_css_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_popper_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_fontawesome_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_bootswatch_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_datatables_js_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_datatables_css_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_datepicker_js_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_datepicker_css_cdn` TEXT NOT NULL;

ALTER TABLE `settings` ADD `elan_dropzone_js_cdn` TEXT NOT NULL;
ALTER TABLE `settings` ADD `elan_dropzone_css_cdn` TEXT NOT NULL;

UPDATE `settings` SET `elan_backup_age`         = '30' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_google_maps_key`    = 'AIzaSyBXQRDsHxF-xqZc-QaH7HK_3C1srIluRLU' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_google_geo_key`     = 'AIzaSyDe6iL2X8LI5jQY_7NLOPReQmuEEBVc0Oc' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_image_dir`          = 'app/userimages/' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_image_max`          = '6' WHERE `settings`.`id`  = 1;

UPDATE `settings` SET `elan_jquery_cdn`         = '&lt;script  src=&quot;https://code.jquery.com/jquery-3.5.1.min.js&quot;   integrity=&quot;sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=&quot;   crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_jquery_ui_cdn`      = '&lt;script src=&quot;https://code.jquery.com/ui/1.12.1/jquery-ui.min.js&quot; integrity=&quot;sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=&quot; crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_bootstrap_js_cdn`   = '&lt;script src=&quot;https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js&quot; integrity=&quot;sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx&quot; crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_bootstrap_css_cdn`  = '&lt;link rel=&quot;stylesheet&quot; href=&quot;https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css&quot; integrity=&quot;sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2&quot; crossorigin=&quot;anonymous&quot;&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_popper_cdn`         = '&lt;script src=&quot;https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js&quot; integrity=&quot;sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN&quot; crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_fontawesome_cdn`    = '&lt;script src=&quot;https://kit.fontawesome.com/2d8f489b15.js&quot; crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_bootswatch_cdn`     = '&lt;link rel=&quot;stylesheet&quot; href=&quot;https://cdnjs.cloudflare.com/ajax/libs/bootswatch/4.6.0/simplex/bootstrap.min.css&quot; integrity=&quot;sha512-9hj+qhrmo7MUSzKG3nwkDWncL1x8e2d1wfJxufofoBMMLXlqlqvjpT0V0blusJ8CFx9fs9Ru7ICYkVrz62Q33w==&quot; crossorigin=&quot;anonymous&quot; /&gt;' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_datatables_js_cdn`  = '&lt;script type=&quot;text/javascript&quot; src=&quot;https://cdn.datatables.net/v/bs4/dt-1.10.23/fh-3.1.8/r-2.2.7/rg-1.1.2/sc-2.0.3/sb-1.0.1/sp-1.2.2/datatables.min.js&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_datatables_css_cdn` = '&lt;link rel=&quot;stylesheet&quot; type=&quot;text/css&quot; href=&quot;https://cdn.datatables.net/v/bs4/dt-1.10.23/fh-3.1.8/r-2.2.7/rg-1.1.2/sc-2.0.3/sb-1.0.1/sp-1.2.2/datatables.min.css&quot; /&gt;' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_datepicker_js_cdn`  = '&lt;script src=&quot;https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js&quot;&gt;&lt;/script&gt;' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_datepicker_css_cdn` = '&lt;link rel=&quot;stylesheet&quot; href=&quot;https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css&quot; /&gt;' WHERE `settings`.`id` = 1;

UPDATE `settings` SET `elan_dropzone_js_cdn`    = '&lt;script src=&quot;https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.4/min/dropzone.min.js&quot; integrity=&quot;sha512-dGvmY7yzI6BpkyUDPksBkw5cb0uthau1dhw/2ZHU9nezEFOArD4H1+yx141qmm+V/QSZFOOjF2p6nUhhy4HJ1g==&quot; ' WHERE `settings`.`id` = 1;
UPDATE `settings` SET `elan_dropzone_css_cdn`   = '&lt;link rel=&quot;stylesheet&quot; href=&quot;https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.4/dropzone.min.css&quot; integrity=&quot;sha512-jU/7UFiaW5UBGODEopEqnbIAHOI8fO6T99m7Tsmqs2gkdujByJfkCbbfPSN4Wlqlb9TGnsuC0YgUgWkRBK7B9A==&quot; ' WHERE `settings`.`id` = 1;




