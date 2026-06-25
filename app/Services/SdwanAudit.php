<?php
declare(strict_types=1);

namespace App\Services;

final class SdwanAudit
{
	public static function record(string $action, ?string $resource = null, bool $success = true): void
	{
		AuditLog::record('sdwan_' . $action, $resource, $success);
	}

	/** @return array<int, array<string, mixed>> */
	public static function recent(int $limit = 40): array
	{
		return AuditLog::recentByActionPrefix('sdwan_', $limit);
	}

	public static function actionLabel(string $action): string
	{
		return match ($action) {
			'sdwan_create' => 'Registro criado',
			'sdwan_update' => 'Registro atualizado',
			'sdwan_delete' => 'Registro excluído',
			'sdwan_public_create' => 'Cadastro via link',
			'sdwan_import' => 'Importação CSV',
			'sdwan_link_generate' => 'Link gerado',
			'sdwan_link_revoke' => 'Link revogado',
			'sdwan_settings_update' => 'Configurações',
			'sdwan_stores_upload' => 'Upload de lojas',
			'sdwan_cleanup' => 'Limpeza',
			default => $action,
		};
	}
}
