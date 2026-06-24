<?php
declare(strict_types=1);

namespace App\Services;

final class SdwanAudit
{
	public static function record(string $action, ?string $resource = null, bool $success = true): void
	{
		AuditLog::record('sdwan_' . $action, $resource, $success);
	}
}
