CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `age` VARCHAR(20) NULL,
  `contact_time` VARCHAR(20) NULL,
  `source` VARCHAR(20) NULL,
  `calc_summary` VARCHAR(255) NULL,
  `agree_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `referrer` VARCHAR(500) NULL,
  `landing_url` VARCHAR(500) NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
