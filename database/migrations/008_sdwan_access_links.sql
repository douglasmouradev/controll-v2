CREATE TABLE IF NOT EXISTS sdwan_access_links (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	code CHAR(4) NOT NULL,
	created_by INT UNSIGNED NULL,
	expires_at DATETIME NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_sdwan_access_code (code),
	KEY idx_sdwan_access_expires (expires_at),
	KEY idx_sdwan_access_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
