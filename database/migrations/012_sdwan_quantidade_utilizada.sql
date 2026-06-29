ALTER TABLE sdwan_entries
	ADD COLUMN quantidade_utilizada INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantidade_localizada;
