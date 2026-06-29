<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Auth;
use App\Services\AuditLock;

abstract class Controller
{
	protected function view(string $template, array $data = []): void
	{
		extract($data, EXTR_SKIP);
		$auth = Auth::instance();
		$viewFile = BASE_PATH . '/app/Views/' . $template . '.php';
		if (!file_exists($viewFile)) {
			http_response_code(500);
			echo 'View not found: ' . htmlspecialchars($template);
			return;
		}
		require BASE_PATH . '/app/Views/partials/header.php';
		require $viewFile;
		require BASE_PATH . '/app/Views/partials/footer.php';
	}

	protected function json(array $payload, int $status = 200): void
	{
		// Limpar qualquer output anterior
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE);
		exit;
	}

	protected function requireAuth(array $roles = []): void
	{
		$auth = Auth::instance();
		$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		
		if (!$auth->check()) {
			// Limpar qualquer output antes
			while (ob_get_level() > 0) {
				ob_end_clean();
			}
			if ($isAjax) {
				http_response_code(401);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['success' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
				exit;
			} else {
				header('Location: /login');
				exit;
			}
		}
		if ($roles !== [] && !$auth->hasAnyRole($roles)) {
			// Limpar qualquer output antes
			while (ob_get_level() > 0) {
				ob_end_clean();
			}
			if ($isAjax) {
				http_response_code(403);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
				exit;
			} else {
				http_response_code(403);
				echo 'Acesso negado';
				exit;
			}
		}

		$user = $auth->user();
		if (AuditLock::shouldBlock($user)) {
			$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
			if (!AuditLock::isAllowedPath($path)) {
				while (ob_get_level() > 0) {
					ob_end_clean();
				}
				if ($isAjax) {
					http_response_code(403);
					header('Content-Type: application/json; charset=utf-8');
					echo json_encode([
						'success' => false,
						'message' => AuditLock::blockMessage(),
					], JSON_UNESCAPED_UNICODE);
					exit;
				}
				header('Location: /auditoria');
				exit;
			}
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
	}
}


