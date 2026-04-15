-- ============================================================
-- SEO Backend — Full Schema for Railway MySQL
-- Import this once via Railway's Query tab or MySQL client
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- sites
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sites` (
  `id`               int unsigned      NOT NULL AUTO_INCREMENT,
  `domain`           varchar(255)      NOT NULL,
  `language`         varchar(10)       NOT NULL DEFAULT 'en',
  `category`         varchar(100)          NULL DEFAULT NULL,
  `status`           enum('active','paused') NOT NULL DEFAULT 'active',
  `publish_endpoint` varchar(500)          NULL DEFAULT NULL,
  `publish_api_key`  varchar(255)          NULL DEFAULT NULL,
  `created_at`       datetime              NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime              NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- articles  (exact columns from your DESCRIBE output)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `articles` (
  `id`                int unsigned                          NOT NULL AUTO_INCREMENT,
  `site_id`           int unsigned                          NOT NULL,
  `title`             varchar(500)                          NOT NULL,
  `description`       text                                      NULL,
  `body`              longtext                                  NULL,
  `keyword`           varchar(100)                          NOT NULL,
  `category`          varchar(100)                              NULL,
  `originality_score` tinyint unsigned                          NULL DEFAULT 0,
  `status`            enum('pending','published','failed')      NULL DEFAULT 'pending',
  `google_indexed`    tinyint(1)                                NULL DEFAULT 0,
  `bing_indexed`      tinyint(1)                                NULL DEFAULT 0,
  `baidu_indexed`     tinyint(1)                                NULL DEFAULT 0,
  `yandex_indexed`    tinyint(1)                                NULL DEFAULT 0,
  `sogou_indexed`     tinyint(1)                                NULL DEFAULT 0,
  `indexed_360`       tinyint(1)                                NULL DEFAULT 0,
  `created_at`        datetime                                  NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime                                  NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_id`       (`site_id`),
  KEY `idx_status`        (`status`),
  KEY `idx_google_indexed`(`google_indexed`),
  KEY `idx_created_at`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- friendly_links  (exact columns from your DESCRIBE output)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `friendly_links` (
  `id`         int unsigned  NOT NULL AUTO_INCREMENT,
  `site_id`    int unsigned  NOT NULL,
  `link_url`   varchar(255)  NOT NULL,
  `link_text`  varchar(100)  NOT NULL,
  `created_at` datetime          NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- scheduled_tasks  (inferred from controller + cron command)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scheduled_tasks` (
  `id`               int unsigned                    NOT NULL AUTO_INCREMENT,
  `site_id`          int unsigned                    NOT NULL,
  `keyword`          varchar(255)                    NOT NULL,
  `language`         varchar(10)                     NOT NULL DEFAULT 'en',
  `category`         varchar(100)                        NULL DEFAULT NULL,
  `media_type`       varchar(50)                         NULL DEFAULT NULL,
  `frequency`        enum('daily','weekly','manual')     NOT NULL DEFAULT 'daily',
  `status`           enum('active','paused')             NOT NULL DEFAULT 'active',
  `publish_endpoint` varchar(500)                        NULL DEFAULT NULL,
  `publish_api_key`  varchar(255)                        NULL DEFAULT NULL,
  `next_run_at`      datetime                            NULL DEFAULT NULL,
  `last_run_at`      datetime                            NULL DEFAULT NULL,
  `created_at`       datetime                            NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime                            NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_id`    (`site_id`),
  KEY `idx_status`     (`status`),
  KEY `idx_next_run_at`(`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings  (key-value store used by SettingsController)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         int unsigned  NOT NULL AUTO_INCREMENT,
  `key`        varchar(100)  NOT NULL,
  `value`      text              NULL,
  `created_at` datetime          NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime          NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Done. All 5 tables created.
-- ============================================================
