#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__));

$result = \App\Services\EmailQueue::process(
	(int) ($argv[1] ?? getenv('MAIL_QUEUE_BATCH') ?: 25)
);

echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
