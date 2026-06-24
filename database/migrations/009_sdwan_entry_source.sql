ALTER TABLE sdwan_entries
	ADD COLUMN entry_source VARCHAR(20) NOT NULL DEFAULT 'dashboard' AFTER created_by,
	ADD COLUMN access_link_id INT UNSIGNED NULL AFTER entry_source,
	ADD KEY idx_sdwan_entry_source (entry_source),
	ADD KEY idx_sdwan_access_link (access_link_id);
