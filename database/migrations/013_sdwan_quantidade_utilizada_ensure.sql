SET @acupad_db := DATABASE();
SET @acupad_col_exists := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = @acupad_db
		AND TABLE_NAME = 'sdwan_entries'
		AND COLUMN_NAME = 'quantidade_utilizada'
);
SET @acupad_sql := IF(
	@acupad_col_exists = 0,
	'ALTER TABLE sdwan_entries ADD COLUMN quantidade_utilizada INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantidade_localizada',
	'SELECT 1'
);
PREPARE acupad_stmt FROM @acupad_sql;
EXECUTE acupad_stmt;
DEALLOCATE PREPARE acupad_stmt;
