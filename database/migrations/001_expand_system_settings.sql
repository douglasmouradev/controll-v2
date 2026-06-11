INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('audit_lock_enabled', '1', 'Bloqueio por auditoria até data configurada'),
('audit_available_date', '2026-06-15', 'Data de liberação do acesso para usuários finais'),
('notification_email', 'Grupotitanium@titaniumtelecom.com.br', 'E-mail para notificações de chamados')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
