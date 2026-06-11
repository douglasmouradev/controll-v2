<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\Auth;
use App\Services\AuditLog;
use App\Services\TwoFactor;

final class SecurityController extends Controller
{
	public function status(): void
	{
		$this->requireAuth([]);
		$user = $this->currentUserWith2fa();
		if (!$user || !TwoFactor::isStaffRole((string) ($user['role'] ?? ''))) {
			$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
			return;
		}

		$this->json([
			'success' => true,
			'enabled' => TwoFactor::isEnabledForUser($user),
		]);
	}

	public function setup(): void
	{
		$this->requireAuth([]);
		$user = $this->currentUserWith2fa();
		if (!$user || !TwoFactor::isStaffRole((string) ($user['role'] ?? ''))) {
			$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
			return;
		}

		if (TwoFactor::isEnabledForUser($user)) {
			$this->json(['success' => false, 'message' => '2FA já está ativo.'], 422);
			return;
		}

		$secret = TwoFactor::generateSecret();
		$_SESSION['pending_2fa_setup_secret'] = $secret;
		$email = (string) ($user['email'] ?? $user['username'] ?? 'user');

		$this->json([
			'success' => true,
			'secret' => $secret,
			'otpauth_uri' => TwoFactor::getOtpAuthUri($secret, $email),
		]);
	}

	public function confirm(): void
	{
		$this->requireAuth([]);
		$user = $this->currentUserWith2fa();
		if (!$user || !TwoFactor::isStaffRole((string) ($user['role'] ?? ''))) {
			$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
			return;
		}

		$secret = (string) ($_SESSION['pending_2fa_setup_secret'] ?? '');
		$code = trim((string) ($_POST['code'] ?? ''));
		if ($secret === '' || $code === '') {
			$this->json(['success' => false, 'message' => 'Informe o código do autenticador.'], 422);
			return;
		}

		if (!TwoFactor::enableForUser((int) $user['id'], $secret, $code)) {
			$this->json(['success' => false, 'message' => 'Código inválido.'], 422);
			return;
		}

		unset($_SESSION['pending_2fa_setup_secret']);
		AuditLog::record('two_factor_enabled', 'user:' . (int) $user['id']);
		$this->json(['success' => true, 'message' => 'Autenticação em duas etapas ativada.']);
	}

	public function disable(): void
	{
		$this->requireAuth([]);
		$user = $this->currentUserWith2fa();
		if (!$user || !TwoFactor::isStaffRole((string) ($user['role'] ?? ''))) {
			$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
			return;
		}

		$code = trim((string) ($_POST['code'] ?? ''));
		if ($code === '') {
			$this->json(['success' => false, 'message' => 'Informe o código do autenticador.'], 422);
			return;
		}

		if (!TwoFactor::disableForUser((int) $user['id'], $code)) {
			$this->json(['success' => false, 'message' => 'Código inválido.'], 422);
			return;
		}

		AuditLog::record('two_factor_disabled', 'user:' . (int) $user['id']);
		$this->json(['success' => true, 'message' => 'Autenticação em duas etapas desativada.']);
	}

	private function currentUserWith2fa(): ?array
	{
		$sessionUser = Auth::instance()->user();
		if (!$sessionUser || !isset($sessionUser['id'])) {
			return null;
		}

		return User::findById((int) $sessionUser['id']);
	}
}
