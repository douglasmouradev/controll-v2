SET @acupad_db := DATABASE();

SET @acupad_col_exists := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = @acupad_db
		AND TABLE_NAME = 'sdwan_entries'
		AND COLUMN_NAME = 'serie_antena'
);
SET @acupad_sql := IF(
	@acupad_col_exists = 0,
	'ALTER TABLE sdwan_entries ADD COLUMN serie_antena VARCHAR(60) NOT NULL DEFAULT '''' AFTER pdv_serie',
	'SELECT 1'
);
PREPARE acupad_stmt FROM @acupad_sql;
EXECUTE acupad_stmt;
DEALLOCATE PREPARE acupad_stmt;

SET @acupad_col_exists := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = @acupad_db
		AND TABLE_NAME = 'sdwan_entries'
		AND COLUMN_NAME = 'serie_acupad'
);
SET @acupad_sql := IF(
	@acupad_col_exists = 0,
	'ALTER TABLE sdwan_entries ADD COLUMN serie_acupad VARCHAR(60) NOT NULL DEFAULT '''' AFTER serie_antena',
	'SELECT 1'
);
PREPARE acupad_stmt FROM @acupad_sql;
EXECUTE acupad_stmt;
DEALLOCATE PREPARE acupad_stmt;

SET @acupad_col_exists := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = @acupad_db
		AND TABLE_NAME = 'sdwan_entries'
		AND COLUMN_NAME = 'setor'
);
SET @acupad_sql := IF(
	@acupad_col_exists = 0,
	'ALTER TABLE sdwan_entries ADD COLUMN setor VARCHAR(120) NOT NULL DEFAULT '''' AFTER serie_acupad',
	'SELECT 1'
);
PREPARE acupad_stmt FROM @acupad_sql;
EXECUTE acupad_stmt;
DEALLOCATE PREPARE acupad_stmt;
