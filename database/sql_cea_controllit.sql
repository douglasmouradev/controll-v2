-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 19/11/2025 às 12:02
-- Versão do servidor: 11.4.4-MariaDB-log
-- Versão do PHP: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sql_cea_controllit`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `access_logs`
--

CREATE TABLE `access_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(100) NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `manager_id`, `color`, `is_active`, `created_at`) VALUES
(1, 'Technical Support', 'Suporte técnico e resolução de problemas', NULL, '#3498db', 1, '2025-11-17 18:09:16'),
(2, 'Billing', 'Cobrança e questões financeiras', NULL, '#e74c3c', 1, '2025-11-17 18:09:16'),
(3, 'Sales', 'Vendas e novos clientes', NULL, '#2ecc71', 1, '2025-11-17 18:09:16'),
(4, 'Development', 'Desenvolvimento e programação', NULL, '#9b59b6', 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `priority` enum('low','normal','high','critical') DEFAULT 'normal',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'Acesso total ao sistema', '2025-11-17 18:09:16'),
(2, 'manage_users', 'Gerenciar usuários', '2025-11-17 18:09:16'),
(3, 'manage_tickets', 'Gerenciar tickets', '2025-11-17 18:09:16'),
(4, 'view_reports', 'Visualizar relatórios', '2025-11-17 18:09:16'),
(5, 'manage_settings', 'Gerenciar configurações', '2025-11-17 18:09:16'),
(6, 'view_tickets', 'Visualizar tickets', '2025-11-17 18:09:16'),
(7, 'edit_tickets', 'Editar tickets', '2025-11-17 18:09:16'),
(8, 'delete_tickets', 'Deletar tickets', '2025-11-17 18:09:16'),
(9, 'manage_notifications', 'Gerenciar notificações', '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Admin', 'Administrador do sistema com acesso total', 1, '2025-11-17 18:09:16'),
(2, 'Manager', 'Gerente com acesso a relatórios e gerenciamento', 1, '2025-11-17 18:09:16'),
(3, 'Support', 'Agente de suporte com acesso a tickets', 1, '2025-11-17 18:09:16'),
(4, 'User', 'Usuário final com acesso limitado', 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sla_rules`
--

CREATE TABLE `sla_rules` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `priority` varchar(20) NOT NULL,
  `response_time_hours` int(11) NOT NULL,
  `resolution_time_hours` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `sla_rules`
--

INSERT INTO `sla_rules` (`id`, `category`, `priority`, `response_time_hours`, `resolution_time_hours`, `is_active`, `created_at`) VALUES
(1, 'Hardware', 'low', 24, 72, 1, '2025-11-17 18:09:16'),
(2, 'Hardware', 'medium', 12, 48, 1, '2025-11-17 18:09:16'),
(3, 'Hardware', 'high', 4, 24, 1, '2025-11-17 18:09:16'),
(4, 'Hardware', 'critical', 1, 8, 1, '2025-11-17 18:09:16'),
(5, 'Software', 'low', 24, 72, 1, '2025-11-17 18:09:16'),
(6, 'Software', 'medium', 12, 48, 1, '2025-11-17 18:09:16'),
(7, 'Software', 'high', 4, 24, 1, '2025-11-17 18:09:16'),
(8, 'Software', 'critical', 1, 8, 1, '2025-11-17 18:09:16'),
(9, 'Rede', 'low', 24, 72, 1, '2025-11-17 18:09:16'),
(10, 'Rede', 'medium', 12, 48, 1, '2025-11-17 18:09:16'),
(11, 'Rede', 'high', 4, 24, 1, '2025-11-17 18:09:16'),
(12, 'Rede', 'critical', 1, 8, 1, '2025-11-17 18:09:16'),
(13, 'E-mail', 'low', 24, 72, 1, '2025-11-17 18:09:16'),
(14, 'E-mail', 'medium', 12, 48, 1, '2025-11-17 18:09:16'),
(15, 'E-mail', 'high', 4, 24, 1, '2025-11-17 18:09:16'),
(16, 'E-mail', 'critical', 1, 8, 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sla_violations`
--

CREATE TABLE `sla_violations` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `violation_type` enum('response','resolution') NOT NULL,
  `hours_elapsed` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'system_name', 'Controll IT Help Desk', 'Nome do sistema', '2025-11-17 18:09:16'),
(2, 'max_file_size', '10485760', 'Tamanho máximo de arquivo em bytes (10MB)', '2025-11-17 18:09:16'),
(3, 'allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt', 'Tipos de arquivo permitidos', '2025-11-17 18:09:16'),
(4, 'auto_assign_tickets', 'false', 'Atribuição automática de chamados', '2025-11-17 18:09:16'),
(5, 'notification_email', 'suporte@controllit.com.br', 'E-mail para notificações', '2025-11-17 18:09:16'),
(6, 'sla_enabled', 'true', 'Habilitar regras de SLA', '2025-11-17 18:09:16'),
(7, 'default_priority', '2', 'Prioridade padrão para novos tickets', '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tags`
--

INSERT INTO `tags` (`id`, `name`, `color`, `description`, `is_active`, `created_at`) VALUES
(1, 'urgent', '#e74c3c', 'Tickets urgentes que requerem atenção imediata', 1, '2025-11-17 18:09:16'),
(2, 'bug', '#f39c12', 'Bugs reportados no sistema', 1, '2025-11-17 18:09:16'),
(3, 'feature', '#2ecc71', 'Solicitações de novas funcionalidades', 1, '2025-11-17 18:09:16'),
(4, 'documentation', '#3498db', 'Problemas relacionados à documentação', 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category_id` int(11) NOT NULL,
  `priority_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `name` varchar(120) DEFAULT NULL,
  `registration` varchar(60) DEFAULT NULL,
  `unit` varchar(120) DEFAULT NULL,
  `cep` varchar(12) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `address_number` varchar(20) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `project_type` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `uf` varchar(5) DEFAULT NULL,
  `internal_order` varchar(120) DEFAULT NULL,
  `invoice` varchar(120) DEFAULT NULL,
  `daily_destination` varchar(255) DEFAULT NULL,
  `external_ticket` varchar(120) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `support_response` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tickets`
--

INSERT INTO `tickets` (`id`, `title`, `description`, `category_id`, `priority_id`, `status_id`, `user_id`, `assigned_to`, `department_id`, `rating`, `name`, `registration`, `unit`, `cep`, `address`, `address_number`, `city`, `uf`, `internal_order`, `invoice`, `daily_destination`, `external_ticket`, `logo_path`, `support_response`, `created_at`, `updated_at`, `closed_at`) VALUES
(16, 'Deu ruim no cdp', 'Kkkkk', 1, 3, 2, 20, 10, NULL, NULL, 'ROGERIO WANDERLEY NOGUEIRA', '0000', 'Cdp', '06070-010', 'Rua Alberto Mendes Junior', '135', 'Osasco', 'Sp', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 20:11:10', '2025-11-18 21:46:29', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tickets_backup`
--

CREATE TABLE `tickets_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int(11) NOT NULL,
  `priority_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_categories`
--

CREATE TABLE `ticket_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `ticket_categories`
--

INSERT INTO `ticket_categories` (`id`, `name`, `description`, `parent_id`, `color`, `icon`, `active`, `created_at`) VALUES
(1, 'Hardware', 'Problemas relacionados a equipamentos físicos', NULL, '#3498db', 'desktop', 1, '2025-11-17 18:09:16'),
(2, 'Software', 'Problemas com programas e aplicativos', NULL, '#2ecc71', 'laptop', 1, '2025-11-17 18:09:16'),
(3, 'Rede', 'Problemas de conectividade e infraestrutura de rede', NULL, '#9b59b6', 'network-wired', 1, '2025-11-17 18:09:16'),
(4, 'E-mail', 'Problemas com sistema de e-mail', NULL, '#e74c3c', 'envelope', 1, '2025-11-17 18:09:16'),
(5, 'Outros', 'Outros tipos de problemas', NULL, '#f39c12', 'question-circle', 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se é comentário interno (não visível para o usuário)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_history`
--

CREATE TABLE `ticket_history` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_priorities`
--

CREATE TABLE `ticket_priorities` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `level` int(11) NOT NULL COMMENT '1=Baixa, 2=Média, 3=Alta, 4=Crítica',
  `color` varchar(7) NOT NULL COMMENT 'Código hexadecimal da cor',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `ticket_priorities`
--

INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `created_at`) VALUES
(1, 'Baixa', 1, '#10b981', '2025-11-17 18:09:16'),
(2, 'Média', 2, '#f59e0b', '2025-11-17 18:09:16'),
(3, 'Alta', 3, '#ef4444', '2025-11-17 18:09:16'),
(4, 'Crítica', 4, '#dc2626', '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_statuses`
--

CREATE TABLE `ticket_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL COMMENT 'Para uso no código',
  `color` varchar(7) NOT NULL,
  `is_final` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se é um status final (fechado)',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `ticket_statuses`
--

INSERT INTO `ticket_statuses` (`id`, `name`, `slug`, `color`, `is_final`, `created_at`) VALUES
(1, 'Aberto', 'aberto', '#f59e0b', 0, '2025-11-17 18:09:16'),
(2, 'Em Andamento', 'em_andamento', '#3b82f6', 0, '2025-11-17 18:09:16'),
(3, 'Fechado', 'fechado', '#10b981', 1, '2025-11-17 18:09:16'),
(4, 'Cancelado', 'cancelado', '#6b7280', 1, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ticket_tags`
--

CREATE TABLE `ticket_tags` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `user_type` enum('user','support','admin') NOT NULL DEFAULT 'user',
  `role_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `password_changed_at`, `user_type`, `role_id`, `department_id`, `phone`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@controllit.com.br', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', 1, 1, NULL, 1, '2025-11-17 18:09:16', '2025-11-17 18:09:16'),
(2, 'Agente de Suporte', 'suporte@controllit.com.br', 'suporte', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'support', 3, 1, NULL, 1, '2025-11-17 18:09:16', '2025-11-17 18:09:16'),
(3, 'Usuário Padrão', 'usuario@controllit.com.br', 'usuario', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'user', 4, NULL, NULL, 1, '2025-11-17 18:09:16', '2025-11-17 18:09:16'),
(5, 'Novo Admin', 'novoadmin@local', 'novoadmin', '$2y$10$ghKvgK4ry3jhJZVsYy/D1ulDN170gF4D8kTi8pd0IYRJ12YzkW112', '2025-11-18 18:19:18', 'admin', 1, 1, NULL, 1, '2025-11-17 18:27:55', '2025-11-18 18:19:18'),
(10, 'Douglas', 'douglas@controllit.com.br', 'douglas', '$2y$10$Bn0GXhxoADvtWy8S8jAOiun7D2nMaMyiS3DS1uB1Z44q4kLJEpPhy', '2025-11-18 18:08:18', 'admin', NULL, NULL, NULL, 1, '2025-11-17 20:48:38', '2025-11-18 18:08:18'),
(12, 'Carlos', 'carlos@controllit.com.br', 'carlos', '$2y$10$mWsKnZPdX6FRm8EaveAWZOp95y.I2m4panxRNZGk8lSKSdpY58d/y', NULL, 'user', NULL, NULL, NULL, 1, '2025-11-18 02:18:53', '2025-11-18 02:18:53'),
(13, 'Victor Gabriel', 'victor.gabriel@controllit.com.br', 'victorgabriel', '$2y$10$OwYESDSrQrLKf0LAWklcDOW1HZEdHLE5C8JQ3faU1/7GDdJuBqFf.', '2025-11-18 18:13:07', 'user', NULL, NULL, NULL, 1, '2025-11-18 11:09:14', '2025-11-18 18:13:07'),
(14, 'Jeovana Anjos', 'jeovana.anjos@controllit.com.br', 'jeovanaanjos', '$2y$10$P2c4q6dc6ZCmS0eHo0OZ2uWojXkpnrLT4tkAtQdYldv/iui2Qblga', '2025-11-18 19:56:57', 'user', NULL, NULL, NULL, 1, '2025-11-18 11:09:50', '2025-11-18 19:56:57'),
(15, 'Talita Moura', 'talita.moura@controllit.com.br', 'talitamoura', '$2y$10$0uL5B8e6yR4933zyXvcuBe96nCp31rybfEKFmAozyPa/PN18TSADm', '2025-11-18 18:13:07', 'user', NULL, NULL, NULL, 1, '2025-11-18 11:10:15', '2025-11-18 18:13:07'),
(16, 'Big Boss', 'manoel.silva@controllit.com.br', 'bigboss', '$2y$10$FnFi0JoIIvjMdOZL/4/brO6vZ2lMXajgNY3MPC.6k/c5ASlyVWhVG', '2025-11-18 19:40:32', 'admin', NULL, NULL, NULL, 1, '2025-11-18 11:13:45', '2025-11-18 19:40:32'),
(17, 'Edgar', 'edgar.santana@controllit.com.br', 'edgar', '$2y$10$ycUgOzwwFKcfNG9VDplBpuFadCc7YGb0ykubrunJ.NF7Fo/.RbIuq', '2025-11-18 18:17:47', 'user', NULL, NULL, NULL, 1, '2025-11-18 11:26:24', '2025-11-18 18:17:47'),
(18, 'Luis Fernando', 'luis@controllit.com.br', 'luisfernando', '$2y$10$KPUWga7i65aHaKsaBSvCourv0v73DfAqZS7Uj/rrniGestmKll99.', '2025-11-18 19:35:29', 'user', NULL, NULL, NULL, 1, '2025-11-18 11:52:48', '2025-11-18 19:35:29'),
(19, 'Osmar Araujo', 'osmar.araujo@cea.com.br', 'osmararaujo', '$2y$10$MU2uD/6.L7hDGWUZjDySweQz6eeesa3NN/Sp0wmfFF8Iysf28WhCa', NULL, 'user', NULL, NULL, NULL, 1, '2025-11-18 19:49:22', '2025-11-18 19:49:22'),
(20, 'Rogerio Wanderley Nogueira', 'rogerio.nogueira@cea.com.br', 'rogeriowanderleynogueira', '$2y$10$DfkSUVXbmoMfGIFwGZurtOrSdKGA/Mb1lgXpZR2NITEi8trLnjzyC', '2025-11-18 20:08:08', 'user', NULL, NULL, NULL, 1, '2025-11-18 20:05:26', '2025-11-18 20:08:08'),
(21, 'Giane Gonçalves Lopes', 'giane.lopes@cea.com.br', 'gianegonalveslopes', '$2y$10$k8.yjC8QDoBLT6vm8emVieOLrT9EfHKwbQCzJolW3.XO8QIINHlDi', NULL, 'user', NULL, NULL, NULL, 1, '2025-11-18 20:07:26', '2025-11-18 20:07:26'),
(22, 'Josi Silva', 'josi@controllit.com.br', 'josisilva', '$2y$10$SoKr195nCz46owdu6tM10uyiAlx6KGeEJCWIL3Uq/YWOZZnau4PSS', '2025-11-18 20:12:08', 'admin', NULL, NULL, NULL, 1, '2025-11-18 20:11:32', '2025-11-18 20:12:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, '2025-11-17 18:09:16'),
(2, 1, 8, '2025-11-17 18:09:16'),
(3, 1, 7, '2025-11-17 18:09:16'),
(4, 1, 9, '2025-11-17 18:09:16'),
(5, 1, 5, '2025-11-17 18:09:16'),
(6, 1, 3, '2025-11-17 18:09:16'),
(7, 1, 2, '2025-11-17 18:09:16'),
(8, 1, 4, '2025-11-17 18:09:16'),
(9, 1, 6, '2025-11-17 18:09:16');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `v_tickets_full`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `v_tickets_full` (
`id` int(11)
,`title` varchar(255)
,`description` text
,`created_at` timestamp
,`updated_at` timestamp
,`closed_at` timestamp
,`user_name` varchar(255)
,`user_email` varchar(255)
,`assigned_to_name` varchar(255)
,`assigned_to_email` varchar(255)
,`category_name` varchar(100)
,`priority_name` varchar(50)
,`priority_level` int(11)
,`priority_color` varchar(7)
,`status_name` varchar(50)
,`status_slug` varchar(50)
,`status_color` varchar(7)
,`status_is_final` tinyint(1)
,`resolution_time_hours` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `v_user_stats`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `v_user_stats` (
`id` int(11)
,`name` varchar(255)
,`email` varchar(255)
,`user_type` enum('user','support','admin')
,`total_tickets` bigint(21)
,`open_tickets` bigint(21)
,`in_progress_tickets` bigint(21)
,`closed_tickets` bigint(21)
,`avg_resolution_time` decimal(24,4)
);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `access_logs`
--
ALTER TABLE `access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_success` (`success`);

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_resource` (`resource`,`resource_id`);

--
-- Índices de tabela `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Índices de tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ticket_id` (`ticket_id`);

--
-- Índices de tabela `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Índices de tabela `sla_rules`
--
ALTER TABLE `sla_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sla_rule` (`category`,`priority`);

--
-- Índices de tabela `sla_violations`
--
ALTER TABLE `sla_violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_violation_type` (`violation_type`);

--
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Índices de tabela `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_status_id` (`status_id`),
  ADD KEY `idx_priority_id` (`priority_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_closed_at` (`closed_at`);

--
-- Índices de tabela `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Índices de tabela `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `ticket_priorities`
--
ALTER TABLE `ticket_priorities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Índices de tabela `ticket_statuses`
--
ALTER TABLE `ticket_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `ticket_tags`
--
ALTER TABLE `ticket_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ticket_tag` (`ticket_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_department_id` (`department_id`);

--
-- Índices de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `access_logs`
--
ALTER TABLE `access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sla_rules`
--
ALTER TABLE `sla_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `sla_violations`
--
ALTER TABLE `sla_violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `ticket_categories`
--
ALTER TABLE `ticket_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `ticket_comments`
--
ALTER TABLE `ticket_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ticket_history`
--
ALTER TABLE `ticket_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ticket_priorities`
--
ALTER TABLE `ticket_priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `ticket_statuses`
--
ALTER TABLE `ticket_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `ticket_tags`
--
ALTER TABLE `ticket_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

-- --------------------------------------------------------

--
-- Estrutura para view `v_tickets_full`
--
DROP TABLE IF EXISTS `v_tickets_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`sql_cea_controllit`@`localhost` SQL SECURITY DEFINER VIEW `v_tickets_full`  AS SELECT `t`.`id` AS `id`, `t`.`title` AS `title`, `t`.`description` AS `description`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at`, `t`.`closed_at` AS `closed_at`, `u`.`name` AS `user_name`, `u`.`email` AS `user_email`, `at`.`name` AS `assigned_to_name`, `at`.`email` AS `assigned_to_email`, `tc`.`name` AS `category_name`, `tp`.`name` AS `priority_name`, `tp`.`level` AS `priority_level`, `tp`.`color` AS `priority_color`, `ts`.`name` AS `status_name`, `ts`.`slug` AS `status_slug`, `ts`.`color` AS `status_color`, `ts`.`is_final` AS `status_is_final`, CASE WHEN `t`.`closed_at` is not null THEN timestampdiff(HOUR,`t`.`created_at`,`t`.`closed_at`) ELSE NULL END AS `resolution_time_hours` FROM (((((`tickets` `t` left join `users` `u` on(`t`.`user_id` = `u`.`id`)) left join `users` `at` on(`t`.`assigned_to` = `at`.`id`)) left join `ticket_categories` `tc` on(`t`.`category_id` = `tc`.`id`)) left join `ticket_priorities` `tp` on(`t`.`priority_id` = `tp`.`id`)) left join `ticket_statuses` `ts` on(`t`.`status_id` = `ts`.`id`)) ;

-- --------------------------------------------------------

--
-- Estrutura para view `v_user_stats`
--
DROP TABLE IF EXISTS `v_user_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`sql_cea_controllit`@`localhost` SQL SECURITY DEFINER VIEW `v_user_stats`  AS SELECT `u`.`id` AS `id`, `u`.`name` AS `name`, `u`.`email` AS `email`, `u`.`user_type` AS `user_type`, count(`t`.`id`) AS `total_tickets`, count(case when `ts`.`slug` = 'aberto' then 1 end) AS `open_tickets`, count(case when `ts`.`slug` = 'em_andamento' then 1 end) AS `in_progress_tickets`, count(case when `ts`.`is_final` = 1 then 1 end) AS `closed_tickets`, avg(case when `t`.`closed_at` is not null then timestampdiff(HOUR,`t`.`created_at`,`t`.`closed_at`) else NULL end) AS `avg_resolution_time` FROM ((`users` `u` left join `tickets` `t` on(`u`.`id` = `t`.`user_id`)) left join `ticket_statuses` `ts` on(`t`.`status_id` = `ts`.`id`)) GROUP BY `u`.`id`, `u`.`name`, `u`.`email`, `u`.`user_type` ;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `access_logs`
--
ALTER TABLE `access_logs`
  ADD CONSTRAINT `access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sla_violations`
--
ALTER TABLE `sla_violations`
  ADD CONSTRAINT `sla_violations_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`),
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tickets_ibfk_6` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD CONSTRAINT `ticket_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD CONSTRAINT `ticket_comments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD CONSTRAINT `ticket_history_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `ticket_tags`
--
ALTER TABLE `ticket_tags`
  ADD CONSTRAINT `ticket_tags_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
