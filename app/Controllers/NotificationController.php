<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;
use App\Services\Auth;

final class NotificationController extends Controller
{
	public function index(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		if (!$user) {
			$this->json(['success' => false, 'message' => 'Não autenticado'], 401);
			return;
		}

		$userId = (int) $user['id'];
		$this->json([
			'success' => true,
			'unread' => Notification::unreadCount($userId),
			'notifications' => Notification::recent($userId, 30),
		]);
	}

	public function markRead(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		if (!$user) {
			$this->json(['success' => false, 'message' => 'Não autenticado'], 401);
			return;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}

		$ok = Notification::markRead($id, (int) $user['id']);
		$this->json([
			'success' => $ok,
			'unread' => Notification::unreadCount((int) $user['id']),
		]);
	}

	public function markAllRead(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		if (!$user) {
			$this->json(['success' => false, 'message' => 'Não autenticado'], 401);
			return;
		}

		Notification::markAllRead((int) $user['id']);
		$this->json(['success' => true, 'unread' => 0]);
	}
}
