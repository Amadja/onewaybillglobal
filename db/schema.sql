CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(160) NOT NULL,
  `company_name` VARCHAR(180) DEFAULT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `import_permit` VARCHAR(80) DEFAULT NULL,
  `website` VARCHAR(200) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shipments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_number` VARCHAR(60) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `origin_country` VARCHAR(80) NOT NULL,
  `destination_country` VARCHAR(80) NOT NULL,
  `origin_location` VARCHAR(180) DEFAULT NULL,
  `destination_location` VARCHAR(180) DEFAULT NULL,
  `service_type` VARCHAR(120) NOT NULL,
  `package_description` VARCHAR(180) DEFAULT NULL,
  `package_weight_kg` DECIMAL(10,2) DEFAULT 0,
  `current_status` VARCHAR(100) DEFAULT 'In Transit',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_tracking_number` (`tracking_number`),
  CONSTRAINT `fk_shipments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shipment_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(140) NOT NULL,
  `location` VARCHAR(180) DEFAULT NULL,
  `carrier_name` VARCHAR(120) DEFAULT NULL,
  `flight_number` VARCHAR(40) DEFAULT NULL,
  `event_date` DATE DEFAULT NULL,
  `event_time` TIME DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 0,
  `is_alert` TINYINT(1) DEFAULT 0,
  `alert_level` VARCHAR(16) NOT NULL DEFAULT 'none',
  `description` TEXT DEFAULT NULL,
  KEY `idx_shipment_id` (`shipment_id`),
  CONSTRAINT `fk_events_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `warehouse_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id` INT UNSIGNED NOT NULL,
  `warehouse_name` VARCHAR(180) DEFAULT NULL,
  `warehouse_location` VARCHAR(180) DEFAULT NULL,
  `entry_date` DATE DEFAULT NULL,
  `entry_time` TIME DEFAULT NULL,
  `free_storage_days` INT DEFAULT 5,
  `daily_rate_per_kg_usd` DECIMAL(10,2) DEFAULT 3.00,
  `formula_text` VARCHAR(220) DEFAULT 'Charge = max((days_in_warehouse - free_days), 0) * weight_kg * daily_rate',
  KEY `idx_shipment_id_warehouse` (`shipment_id`),
  CONSTRAINT `fk_warehouse_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `charges` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id` INT UNSIGNED NOT NULL,
  `charge_type` VARCHAR(80) NOT NULL,
  `amount_usd` DECIMAL(12,2) DEFAULT 0.00,
  `days_used` INT DEFAULT 0,
  `weight_kg` DECIMAL(10,2) DEFAULT 0,
  `rate_value` DECIMAL(10,2) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_shipment_charge` (`shipment_id`),
  CONSTRAINT `fk_charges_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(60) DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auth_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `display_name` VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
