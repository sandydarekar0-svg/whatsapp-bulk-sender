-- Users Table
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20),
  `role` ENUM('admin', 'reseller', 'user') DEFAULT 'user',
  `parent_id` INT COMMENT 'Reseller ID if user is customer of reseller',
  `credits` INT DEFAULT 0,
  `monthly_limit` INT DEFAULT 1000,
  `used_this_month` INT DEFAULT 0,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `api_key` VARCHAR(255) UNIQUE,
  `api_secret` VARCHAR(255),
  `whatsapp_phone` VARCHAR(20),
  `whatsapp_token` TEXT,
  `connection_type` ENUM('api', 'personal') DEFAULT 'api',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Templates Table
CREATE TABLE `templates` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `template_name` VARCHAR(100) NOT NULL,
  `template_content` TEXT NOT NULL,
  `variables` JSON,
  `approved` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  KEY `user_id` (`user_id`)
);

-- Campaigns Table
CREATE TABLE `campaigns` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `campaign_name` VARCHAR(100) NOT NULL,
  `template_id` INT NOT NULL,
  `total_contacts` INT DEFAULT 0,
  `sent_count` INT DEFAULT 0,
  `failed_count` INT DEFAULT 0,
  `pending_count` INT DEFAULT 0,
  `status` ENUM('draft', 'scheduled', 'running', 'completed', 'paused', 'failed') DEFAULT 'draft',
  `scheduled_time` DATETIME,
  `media_url` TEXT,
  `webhook_url` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE CASCADE,
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
);

-- Campaign Contacts Table
CREATE TABLE `campaign_contacts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `campaign_id` INT NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `variables` JSON,
  `status` ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
  `response` TEXT,
  `sent_at` DATETIME,
  `retry_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
  KEY `campaign_id` (`campaign_id`),
  KEY `status` (`status`)
);

-- API Keys Table
CREATE TABLE `api_keys` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `key_name` VARCHAR(100) NOT NULL,
  `api_key` VARCHAR(255) UNIQUE NOT NULL,
  `api_secret` VARCHAR(255) NOT NULL,
  `rate_limit` INT DEFAULT 100,
  `webhook_url` TEXT,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  KEY `api_key` (`api_key`)
);

-- Credits Table
CREATE TABLE `credits` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `amount` INT NOT NULL,
  `transaction_type` ENUM('purchase', 'used', 'refund', 'admin_adjustment') DEFAULT 'purchase',
  `reference_id` VARCHAR(100),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  KEY `user_id` (`user_id`)
);

-- Reseller Commission Table
CREATE TABLE `reseller_commission` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `reseller_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `message_count` INT NOT NULL,
  `commission_percentage` INT DEFAULT 20,
  `commission_amount` DECIMAL(10,2),
  `status` ENUM('pending', 'paid', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Proxy Table
CREATE TABLE `proxies` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `proxy_url` TEXT NOT NULL,
  `proxy_type` ENUM('http', 'socks5') DEFAULT 'http',
  `username` VARCHAR(100),
  `password` VARCHAR(100),
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_checked` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Webhooks Table
CREATE TABLE `webhooks` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `webhook_url` TEXT NOT NULL,
  `webhook_secret` VARCHAR(255),
  `events` JSON,
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_delivery` DATETIME,
  `delivery_count` INT DEFAULT 0,
  `failure_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  KEY `user_id` (`user_id`)
);

-- Message Logs Table
CREATE TABLE `message_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `campaign_contact_id` INT,
  `user_id` INT NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `message_type` ENUM('text', 'image', 'document', 'video') DEFAULT 'text',
  `status` ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
  `external_id` VARCHAR(100),
  `cost` DECIMAL(5,2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
);

-- Settings Table
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('sms_cost', '0.5'),
('image_cost', '1'),
('document_cost', '1.5'),
('video_cost', '2'),
('monthly_limit_user', '1000'),
('monthly_limit_reseller', '10000'),
('reseller_commission_percentage', '20'),
('whatsapp_api_url', 'https://api.whatsapp.com'),
('enable_personal_connection', '1'),
('enable_api_integration', '1');
