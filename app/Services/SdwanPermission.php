<?php
declare(strict_types=1);

namespace App\Services;

final class SdwanPermission
{
	/** Qualquer usuário autenticado pode visualizar o ACUPAD. */
	public static function canView(): bool
	{
		return Auth::instance()->check();
	}

	/** Criar, editar, excluir, importar e configurar. */
	public static function canManage(): bool
	{
		$user = Auth::instance()->user();
		if (!$user) {
			return false;
		}

		return TicketAccess::isStaff((string) ($user['role'] ?? ''));
	}

	/** Auditoria e ações exclusivas de administrador. */
	public static function canAdmin(): bool
	{
		return Auth::instance()->isAdmin();
	}

	public static function requireView(): void
	{
		if (!self::canView()) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['success' => false, 'message' => 'Não autenticado']);
			exit;
		}
	}

	public static function requireManage(): void
	{
		if (!self::canManage()) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação no Projeto ACUPAD']);
			exit;
		}
	}
}
