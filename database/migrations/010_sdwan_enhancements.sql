ALTER TABLE sdwan_access_links
	ADD COLUMN submission_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER expires_at,
	ADD COLUMN max_submissions INT UNSIGNED NOT NULL DEFAULT 50 AFTER submission_count;
