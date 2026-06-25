<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;

final class SdwanNotifier
{
	public static function notifyPublicSubmission(int $entryId, int $notifyUserId, array $data): void
	{
		if ($notifyUserId <= 0) {
			return;
		}

		$loja = (string) ($data['loja'] ?? '');
		$pdv = (string) ($data['pdv_numero'] ?? '-');
		Notification::create(
			$notifyUserId,
			'sdwan_public_entry',
			'Novo cadastro ACUPAD via link',
			'Loja ' . $loja . ' — PDV ' . $pdv . ' (registro #' . $entryId . ')',
			$entryId,
			'normal'
		);
	}
}
