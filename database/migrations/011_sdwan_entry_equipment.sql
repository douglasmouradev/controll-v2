ALTER TABLE sdwan_entries
	ADD COLUMN serie_antena VARCHAR(60) NOT NULL DEFAULT '' AFTER pdv_serie,
	ADD COLUMN serie_acupad VARCHAR(60) NOT NULL DEFAULT '' AFTER serie_antena,
	ADD COLUMN setor VARCHAR(120) NOT NULL DEFAULT '' AFTER serie_acupad;
