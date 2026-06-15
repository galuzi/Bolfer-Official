-- Schema mínimo para o projeto CBW
-- MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS bolfer_local DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bolfer_local;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('founder','admin','staff') NOT NULL DEFAULT 'admin',
  discord_activity_display_name VARCHAR(120) NULL,
  discord_activity_enabled TINYINT(1) NOT NULL DEFAULT 1,
  two_factor_secret VARCHAR(64) NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_recovery_codes LONGTEXT NULL,
  two_factor_confirmed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_invite_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invite_key VARCHAR(100) NOT NULL UNIQUE,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_by_admin_id BIGINT UNSIGNED NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_admin_invite_creator
    FOREIGN KEY (created_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_admin_invite_used_by
    FOREIGN KEY (used_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  token_name VARCHAR(120) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_api_tokens_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  KEY idx_admin_api_tokens_admin (admin_id),
  KEY idx_admin_api_tokens_expires (expires_at),
  KEY idx_admin_api_tokens_revoked (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  limiter_key VARCHAR(191) NOT NULL PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  blocked_until DATETIME NULL DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rate_limits_blocked_until (blocked_until),
  KEY idx_rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  email_verification_token_hash CHAR(64) NULL,
  email_verification_sent_at TIMESTAMP NULL DEFAULT NULL,
  password_reset_token_hash CHAR(64) NULL,
  password_reset_sent_at TIMESTAMP NULL DEFAULT NULL,
  two_factor_secret VARCHAR(64) NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_recovery_codes LONGTEXT NULL,
  two_factor_confirmed_at TIMESTAMP NULL DEFAULT NULL,
  role ENUM('user','vip','moderador') NOT NULL DEFAULT 'user',
  market_coins INT UNSIGNED NOT NULL DEFAULT 0,
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  banned_reason VARCHAR(255) NULL,
  banned_at TIMESTAMP NULL DEFAULT NULL,
  banned_by_admin_id BIGINT UNSIGNED NULL,
  last_ip_address VARCHAR(45) NULL,
  last_fingerprint_hash CHAR(64) NULL,
  session_revoked_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_users_banned_admin
    FOREIGN KEY (banned_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_users_email_verified_at (email_verified_at),
  KEY idx_users_email_verification_token_hash (email_verification_token_hash),
  KEY idx_users_password_reset_token_hash (password_reset_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  username_snapshot VARCHAR(100) NULL,
  email_snapshot VARCHAR(180) NULL,
  ip_address VARCHAR(45) NULL,
  fingerprint_hash CHAR(64) NULL,
  reason VARCHAR(255) NOT NULL,
  severity VARCHAR(40) NOT NULL DEFAULT 'manual',
  note TEXT NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  banned_by_admin_id BIGINT UNSIGNED NULL,
  revoked_by_admin_id BIGINT UNSIGNED NULL,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bans_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_bans_admin
    FOREIGN KEY (banned_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_bans_revoked_admin
    FOREIGN KEY (revoked_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_bans_status (status),
  KEY idx_bans_user (user_id),
  KEY idx_bans_ip (ip_address),
  KEY idx_bans_fingerprint (fingerprint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ban_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matched_ban_id BIGINT UNSIGNED NULL,
  matched_user_id BIGINT UNSIGNED NULL,
  login_input VARCHAR(180) NULL,
  username_input VARCHAR(100) NULL,
  email_input VARCHAR(180) NULL,
  ip_address VARCHAR(45) NULL,
  fingerprint_hash CHAR(64) NULL,
  user_agent TEXT NULL,
  route VARCHAR(255) NULL,
  action VARCHAR(60) NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ban_attempts_ban
    FOREIGN KEY (matched_ban_id) REFERENCES bans(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_ban_attempts_user
    FOREIGN KEY (matched_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_ban_attempts_created_at (created_at),
  KEY idx_ban_attempts_ip (ip_address),
  KEY idx_ban_attempts_fingerprint (fingerprint_hash),
  KEY idx_ban_attempts_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  username_snapshot VARCHAR(100) NULL,
  email_snapshot VARCHAR(180) NULL,
  ip_address VARCHAR(45) NULL,
  fingerprint_hash CHAR(64) NULL,
  user_agent TEXT NULL,
  route VARCHAR(255) NULL,
  action VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_access_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_user_access_logs_user (user_id),
  KEY idx_user_access_logs_ip (ip_address),
  KEY idx_user_access_logs_created_at (created_at),
  KEY idx_user_access_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  item_name VARCHAR(140) NOT NULL,
  item_type VARCHAR(60) NOT NULL DEFAULT 'outro',
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  description TEXT NULL,
  unlock_cost INT UNSIGNED NOT NULL DEFAULT 0,
  locked_content TEXT NULL,
  is_unlocked TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_inventory_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  KEY idx_user_inventory_user (user_id),
  KEY idx_user_inventory_type (item_type),
  KEY idx_user_inventory_name (item_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_market_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  amount INT NOT NULL,
  transaction_type VARCHAR(50) NOT NULL,
  note VARCHAR(255) NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  related_inventory_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_market_transactions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_user_market_transactions_admin
    FOREIGN KEY (created_by_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_transactions_inventory
    FOREIGN KEY (related_inventory_id) REFERENCES user_inventory(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_user_market_transactions_user (user_id),
  KEY idx_user_market_transactions_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_market_listings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_user_id BIGINT UNSIGNED NOT NULL,
  buyer_user_id BIGINT UNSIGNED NULL,
  source_inventory_id BIGINT UNSIGNED NULL,
  item_name VARCHAR(140) NOT NULL,
  item_type VARCHAR(60) NOT NULL DEFAULT 'outro',
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  description TEXT NULL,
  unlock_cost INT UNSIGNED NOT NULL DEFAULT 0,
  locked_content TEXT NULL,
  price_coins INT UNSIGNED NOT NULL,
  status ENUM('active','sold','cancelled') NOT NULL DEFAULT 'active',
  sold_at TIMESTAMP NULL DEFAULT NULL,
  cancelled_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_market_listings_seller
    FOREIGN KEY (seller_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_user_market_listings_buyer
    FOREIGN KEY (buyer_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_listings_inventory
    FOREIGN KEY (source_inventory_id) REFERENCES user_inventory(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_user_market_listings_status (status),
  KEY idx_user_market_listings_seller (seller_user_id),
  KEY idx_user_market_listings_buyer (buyer_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  unit_price DECIMAL(10,2) NOT NULL,
  stock INT NULL,
  minimum_quantity INT UNSIGNED NOT NULL DEFAULT 1,
  server_label VARCHAR(120) NOT NULL DEFAULT 'LDMO Omegamon',
  delivery_eta VARCHAR(50) NOT NULL DEFAULT '5min-1h',
  delivery_method VARCHAR(80) NULL,
  product_type VARCHAR(20) NOT NULL DEFAULT 'item',
  product_description TEXT NULL,
  account_info TEXT NULL,
  account_images LONGTEXT NULL,
  description TEXT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id VARCHAR(32) NOT NULL UNIQUE,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  unit_price_snapshot DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  total_amount_snapshot DECIMAL(10,2) NOT NULL,
  status ENUM(
    'created',
    'pending_payment',
    'paid_waiting_contact',
    'in_delivery',
    'delivered',
    'cancelled',
    'rejected'
  ) NOT NULL DEFAULT 'created',
  contact_channel ENUM('whatsapp','discord') NULL,
  contact_value VARCHAR(120) NULL,
  in_game_nick VARCHAR(60) NOT NULL,
  in_game_server VARCHAR(120) NOT NULL DEFAULT 'LDMO Omegamon',
  delivery_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_orders_user_status (user_id, status),
  CONSTRAINT fk_orders_product
    FOREIGN KEY (product_id) REFERENCES products(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_market_topups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  amount_brl DECIMAL(10,2) NOT NULL,
  coins_amount INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  paid_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_market_topups_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_user_market_topups_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  KEY idx_user_market_topups_status (status),
  KEY idx_user_market_topups_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_market_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(60) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  seller_user_id BIGINT UNSIGNED NULL,
  buyer_user_id BIGINT UNSIGNED NULL,
  target_user_id BIGINT UNSIGNED NULL,
  admin_id BIGINT UNSIGNED NULL,
  listing_id BIGINT UNSIGNED NULL,
  inventory_id BIGINT UNSIGNED NULL,
  topup_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  item_name_snapshot VARCHAR(140) NULL,
  item_type_snapshot VARCHAR(60) NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  price_coins INT UNSIGNED NULL,
  coins_amount INT NULL,
  unlock_cost INT UNSIGNED NULL,
  amount_brl DECIMAL(10,2) NULL,
  item_lock_state ENUM('open','locked') NOT NULL DEFAULT 'open',
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_market_logs_actor
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_seller
    FOREIGN KEY (seller_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_buyer
    FOREIGN KEY (buyer_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_target
    FOREIGN KEY (target_user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_listing
    FOREIGN KEY (listing_id) REFERENCES user_market_listings(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_inventory
    FOREIGN KEY (inventory_id) REFERENCES user_inventory(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_topup
    FOREIGN KEY (topup_id) REFERENCES user_market_topups(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_user_market_logs_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_user_market_logs_event_type (event_type),
  KEY idx_user_market_logs_created_at (created_at),
  KEY idx_user_market_logs_target_user (target_user_id),
  KEY idx_user_market_logs_seller_user (seller_user_id),
  KEY idx_user_market_logs_buyer_user (buyer_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'mercadopago',
  mp_payment_id VARCHAR(64) NULL,
  status VARCHAR(40) NOT NULL,
  raw_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  UNIQUE KEY uq_mp_payment_id (mp_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  admin_id BIGINT UNSIGNED NULL,
  action ENUM('status_change','note_added','webhook_update') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_logs_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_order_logs_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NULL,
  activity_type VARCHAR(60) NOT NULL,
  activity_scope VARCHAR(60) NOT NULL DEFAULT 'admin',
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  fields_json LONGTEXT NULL,
  webhook_url VARCHAR(500) NULL,
  status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
  is_manual TINYINT(1) NOT NULL DEFAULT 0,
  error_message VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_discord_activity_logs_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
  KEY idx_discord_activity_logs_admin (admin_id),
  KEY idx_discord_activity_logs_type (activity_type),
  KEY idx_discord_activity_logs_scope (activity_scope),
  KEY idx_discord_activity_logs_status (status),
  KEY idx_discord_activity_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_products_active ON products(is_active);

