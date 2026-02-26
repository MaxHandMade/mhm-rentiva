-- MHM Rentiva Transfer Data Export
-- Generated: 2026-02-14 23:42:50
-- Site: http://localhost/otokira

-- Transfer Locations (Transfer-enabled only)
-- Total: 3 locations

INSERT INTO `wp_rentiva_transfer_locations` VALUES ('73', 'İstanbul Havalimanı (IST)', 'airport', '1', '1', '2026-02-11 01:26:14', '0', '1');
INSERT INTO `wp_rentiva_transfer_locations` VALUES ('74', 'Sabiha Gökçen Havalimanı (SAW)', 'airport', '2', '1', '2026-02-11 01:26:28', '1', '1');
INSERT INTO `wp_rentiva_transfer_locations` VALUES ('75', 'Kadıköy Şehir Merkezi', 'city_center', '3', '1', '2026-02-11 01:28:22', '1', '1');

-- Transfer Routes (between Transfer-enabled locations only)
-- Total: 4 routes

INSERT INTO `wp_rentiva_transfer_routes` VALUES ('36', '73', '75', '52', '65', 'fixed', '850.00', '0.00', '2026-02-11 01:30:05');
INSERT INTO `wp_rentiva_transfer_routes` VALUES ('37', '75', '73', '52', '65', 'fixed', '850.00', '0.00', '2026-02-11 01:33:36');
INSERT INTO `wp_rentiva_transfer_routes` VALUES ('40', '74', '73', '90', '110', 'fixed', '1500.00', '0.00', '2026-02-11 01:37:29');
INSERT INTO `wp_rentiva_transfer_routes` VALUES ('41', '73', '74', '90', '120', 'fixed', '1600.00', '0.00', '2026-02-11 01:37:57');

-- Export Complete
