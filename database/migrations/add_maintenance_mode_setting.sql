INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('maintenance_mode', '0', 'Modo manutenção — bloqueia acesso de usuários finais')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
