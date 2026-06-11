<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Csrf;

final class Router
{
	/** @var array<string, array<string, array{0: class-string, 1: string}>> */
	private array $routes;

	public function __construct(array $routes)
	{
		$this->routes = $routes;
	}

	public function dispatch(string $method, string $path): void
	{
		$method = strtoupper($method);
		$path = rtrim($path, '/') ?: '/';
		$handler = $this->routes[$method][$path] ?? null;
		if ($handler === null) {
			$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
			http_response_code(404);
			if ($isAjax) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['success' => false, 'message' => 'Rota não encontrada'], JSON_UNESCAPED_UNICODE);
			} else {
				echo 'Not Found';
			}
			return;
		}
		[$class, $action] = $handler;

		if ($method === 'POST' && !$this->verifyCsrf()) {
			return;
		}

		try {
			$controller = new $class();
			$controller->$action();
		} catch (\Throwable $e) {
			$errorMsg = $e->getMessage() . "\n" . $e->getTraceAsString();
			error_log('Erro no router: ' . $errorMsg);
			$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
			http_response_code(500);
			if ($isAjax) {
				header('Content-Type: application/json; charset=utf-8');
				$message = defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Erro interno do servidor';
				echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
			} else {
				$message = defined('APP_DEBUG') && APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Erro interno do servidor';
				echo $message;
			}
		}
	}

	private function verifyCsrf(): bool
	{
		$token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
		if (Csrf::verify($token)) {
			return true;
		}

		$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

		http_response_code(403);
		if ($isAjax) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'success' => false,
				'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
			], JSON_UNESCAPED_UNICODE);
		} else {
			echo 'Sessão expirada. Recarregue a página e tente novamente.';
		}
		return false;
	}
}


