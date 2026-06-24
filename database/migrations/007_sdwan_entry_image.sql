ALTER TABLE sdwan_entries
	ADD COLUMN image_path VARCHAR(500) NULL AFTER loja,
	ADD COLUMN image_name VARCHAR(255) NULL AFTER image_path,
	ADD COLUMN image_type VARCHAR(100) NULL AFTER image_name,
	ADD COLUMN image_size INT UNSIGNED NULL AFTER image_type;
