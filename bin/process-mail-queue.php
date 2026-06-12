#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$result = \App\Services\EmailQueue::process(
	(int) ($argv[1] ?? getenv('MAIL_QUEUE_BATCH') ?: 25)
);

echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
