#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$result = \App\Services\SdwanCleanupService::run();

echo sprintf(
	"Limpeza SDWAN: %d imagem(ns) órfã(s), %d link(s) antigo(s) removido(s).\n",
	$result['orphan_images'],
	$result['expired_links']
);

exit(0);
