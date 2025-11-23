SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Drop views first
DROP VIEW IF EXISTS `v_user_stats`;
DROP VIEW IF EXISTS `v_tickets_full`;

-- Drop tables (children before parents)
DROP TABLE IF EXISTS `ticket_comments`;
DROP TABLE IF EXISTS `ticket_history`;
DROP TABLE IF EXISTS `ticket_attachments`;
DROP TABLE IF EXISTS `ticket_tags`;
DROP TABLE IF EXISTS `sla_violations`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `access_logs`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `ticket_statuses`;
DROP TABLE IF EXISTS `ticket_priorities`;
DROP TABLE IF EXISTS `ticket_categories`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `roles`;

-- Base reference tables
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `manager_id` INT NULL,
  `color` VARCHAR(7),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  -- Opcional: FK para users(manager_id) pode ser adicionada após criar users
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `parent_id` INT NULL,
  `color` VARCHAR(7),
  `icon` VARCHAR(50),
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_parent_id` (`parent_id`),
  CONSTRAINT `fk_ticket_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `ticket_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_priorities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `level` INT NOT NULL UNIQUE COMMENT '1=Baixa, 2=Média, 3=Alta, 4=Crítica',
  `color` VARCHAR(7) NOT NULL COMMENT 'Código hexadecimal da cor',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_statuses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `color` VARCHAR(7) NOT NULL,
  `is_final` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `user_type` ENUM('user', 'support', 'admin') NOT NULL DEFAULT 'user',
  `role_id` INT NULL,
  `department_id` INT NULL,
  `phone` VARCHAR(20) NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_username` (`username`),
  INDEX `idx_user_type` (`user_type`),
  INDEX `idx_active` (`active`),
  INDEX `idx_role_id` (`role_id`),
  INDEX `idx_department_id` (`department_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tickets (normalizado)
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `category_id` INT NOT NULL,
  `priority_id` INT NOT NULL,
  `status_id` INT NOT NULL DEFAULT 1,
  `user_id` INT NOT NULL,
  `assigned_to` INT NULL,
  `department_id` INT NULL,
  `rating` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL,
  FOREIGN KEY (`category_id`) REFERENCES `ticket_categories`(`id`),
  FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`),
  FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_status_id` (`status_id`),
  INDEX `idx_priority_id` (`priority_id`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_department_id` (`department_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_updated_at` (`updated_at`),
  INDEX `idx_closed_at` (`closed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `color` VARCHAR(7),
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attachments
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_attach_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attach_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket tags (N:N)
CREATE TABLE IF NOT EXISTS `ticket_tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ticket_tags_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_ticket_tag` (`ticket_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments
CREATE TABLE IF NOT EXISTS `ticket_comments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `is_internal` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- History
CREATE TABLE IF NOT EXISTS `ticket_history` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `description` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs & notifications
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT,
  `action` VARCHAR(100) NOT NULL,
  `resource` VARCHAR(255) NULL,
  `success` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_ip_address` (`ip_address`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_action` (`action`),
  INDEX `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `resource` VARCHAR(100) NOT NULL,
  `resource_id` INT NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_resource` (`resource`, `resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `ticket_id` INT NULL,
  `priority` ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
  `is_read` BOOLEAN DEFAULT FALSE,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_read` (`is_read`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SLA
CREATE TABLE IF NOT EXISTS `sla_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(100) NOT NULL,
  `priority` VARCHAR(20) NOT NULL,
  `response_time_hours` INT NOT NULL,
  `resolution_time_hours` INT NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_sla_rule` (`category`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sla_violations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `violation_type` ENUM('response', 'resolution') NOT NULL,
  `hours_elapsed` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sla_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_violation_type` (`violation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds
INSERT INTO `roles` (`name`, `description`) VALUES
('Admin', 'Administrador do sistema com acesso total'),
('Manager', 'Gerente com acesso a relatórios e gerenciamento'),
('Support', 'Agente de suporte com acesso a tickets'),
('User', 'Usuário final com acesso limitado');

INSERT INTO `permissions` (`name`, `description`) VALUES
('admin', 'Acesso total ao sistema'),
('manage_users', 'Gerenciar usuários'),
('manage_tickets', 'Gerenciar tickets'),
('view_reports', 'Visualizar relatórios'),
('manage_settings', 'Gerenciar configurações'),
('view_tickets', 'Visualizar tickets'),
('edit_tickets', 'Editar tickets'),
('delete_tickets', 'Deletar tickets'),
('manage_notifications', 'Gerenciar notificações');

INSERT INTO `departments` (`name`, `description`, `color`) VALUES
('Technical Support', 'Suporte técnico e resolução de problemas', '#3498db'),
('Billing', 'Cobrança e questões financeiras', '#e74c3c'),
('Sales', 'Vendas e novos clientes', '#2ecc71'),
('Development', 'Desenvolvimento e programação', '#9b59b6');

INSERT INTO `ticket_categories` (`name`, `description`, `color`, `icon`) VALUES
('Hardware', 'Problemas relacionados a equipamentos físicos', '#3498db', 'desktop'),
('Software', 'Problemas com programas e aplicativos', '#2ecc71', 'laptop'),
('Rede', 'Problemas de conectividade e infraestrutura de rede', '#9b59b6', 'network-wired'),
('E-mail', 'Problemas com sistema de e-mail', '#e74c3c', 'envelope'),
('Outros', 'Outros tipos de problemas', '#f39c12', 'question-circle');

INSERT INTO `ticket_priorities` (`name`, `level`, `color`) VALUES
('Baixa', 1, '#10b981'),
('Média', 2, '#f59e0b'),
('Alta', 3, '#ef4444'),
('Crítica', 4, '#dc2626');

INSERT INTO `ticket_statuses` (`name`, `slug`, `color`, `is_final`) VALUES
('Aberto', 'aberto', '#f59e0b', FALSE),
('Em Andamento', 'em_andamento', '#3b82f6', FALSE),
('Fechado', 'fechado', '#10b981', TRUE),
('Cancelado', 'cancelado', '#6b7280', TRUE);

INSERT INTO `tags` (`name`, `color`, `description`) VALUES
('urgent', '#e74c3c', 'Tickets urgentes que requerem atenção imediata'),
('bug', '#f39c12', 'Bugs reportados no sistema'),
('feature', '#2ecc71', 'Solicitações de novas funcionalidades'),
('documentation', '#3498db', 'Problemas relacionados à documentação');

INSERT INTO `sla_rules` (`category`, `priority`, `response_time_hours`, `resolution_time_hours`) VALUES
('Hardware', 'low', 24, 72),
('Hardware', 'medium', 12, 48),
('Hardware', 'high', 4, 24),
('Hardware', 'critical', 1, 8),
('Software', 'low', 24, 72),
('Software', 'medium', 12, 48),
('Software', 'high', 4, 24),
('Software', 'critical', 1, 8),
('Rede', 'low', 24, 72),
('Rede', 'medium', 12, 48),
('Rede', 'high', 4, 24),
('Rede', 'critical', 1, 8),
('E-mail', 'low', 24, 72),
('E-mail', 'medium', 12, 48),
('E-mail', 'high', 4, 24),
('E-mail', 'critical', 1, 8);

-- Admin user (senha "password")
INSERT INTO `users` (`name`, `email`, `username`, `password_hash`, `user_type`, `role_id`, `department_id`) VALUES
('Administrador', 'admin@controllit.com.br', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);

-- Give all permissions to admin user id 1
INSERT INTO `user_permissions` (`user_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Views
CREATE OR REPLACE VIEW `v_tickets_full` AS
SELECT 
    t.`id`,
    t.`title`,
    t.`description`,
    t.`created_at`,
    t.`updated_at`,
    t.`closed_at`,
    u.`name` as `user_name`,
    u.`email` as `user_email`,
    at.`name` as `assigned_to_name`,
    at.`email` as `assigned_to_email`,
    tc.`name` as `category_name`,
    tp.`name` as `priority_name`,
    tp.`level` as `priority_level`,
    tp.`color` as `priority_color`,
    ts.`name` as `status_name`,
    ts.`slug` as `status_slug`,
    ts.`color` as `status_color`
FROM `tickets` t
LEFT JOIN `users` u ON t.`user_id` = u.`id`
LEFT JOIN `users` at ON t.`assigned_to` = at.`id`
LEFT JOIN `ticket_categories` tc ON t.`category_id` = tc.`id`
LEFT JOIN `ticket_priorities` tp ON t.`priority_id` = tp.`id`
LEFT JOIN `ticket_statuses` ts ON t.`status_id` = ts.`id`;

CREATE OR REPLACE VIEW `v_user_stats` AS
SELECT 
    u.`id`,
    u.`name`,
    u.`email`,
    u.`user_type`,
    COUNT(t.`id`) as `total_tickets`,
    COUNT(CASE WHEN ts.`slug` = 'aberto' THEN 1 END) as `open_tickets`,
    COUNT(CASE WHEN ts.`slug` = 'em_andamento' THEN 1 END) as `in_progress_tickets`,
    COUNT(CASE WHEN ts.`is_final` = TRUE THEN 1 END) as `closed_tickets`
FROM `users` u
LEFT JOIN `tickets` t ON u.`id` = t.`user_id`
LEFT JOIN `ticket_statuses` ts ON t.`status_id` = ts.`id`
GROUP BY u.`id`, u.`name`, u.`email`, u.`user_type`;
