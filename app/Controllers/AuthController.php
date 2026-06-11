<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\AuditLock;
use App\Services\Auth;
use App\Services\RateLimiter;

final class AuthController extends Controller
{
	public function loginForm(): void
	{
		$this->view('auth/login', ['layout' => 'auth']);
	}

	public function login(): void
	{
		$email = trim($_POST['email'] ?? '');
		$password = (string) ($_POST['password'] ?? '');
		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
		$rateKey = 'login:' . strtolower($email !== '' ? $email : $ip);

		if ($email === '' || $password === '') {
			$this->view('auth/login', ['layout' => 'auth', 'error' => 'Informe e-mail e senha.']);
			return;
		}

		if (RateLimiter::tooManyAttempts($rateKey, 5, 15)) {
			$this->view('auth/login', ['layout' => 'auth', 'error' => 'Muitas tentativas. Aguarde 15 minutos e tente novamente.']);
			return;
		}

		$user = User::findByEmail($email);
		if (!$user || !password_verify($password, $user['password'])) {
			RateLimiter::hit($rateKey, $ip);
			$this->view('auth/login', ['layout' => 'auth', 'error' => 'Credenciais inválidas.']);
			return;
		}

		RateLimiter::clear($rateKey);
		Auth::instance()->login($user);

		if (AuditLock::shouldBlock($user)) {
			header('Location: /auditoria');
			return;
		}

		// Verificar se é primeiro login (password_changed_at é NULL)
		if ($user && empty($user['password_changed_at'])) {
			header('Location: /change-password-first');
			return;
		}

		header('Location: /');
	}

	public function auditoria(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();

		if (!AuditLock::shouldBlock($user)) {
			header('Location: /');
			return;
		}

		$this->view('auth/auditoria', [
			'layout' => 'auth',
			'user' => $user,
		]);
	}

	public function changePasswordFirst(): void
	{
		$this->requireAuth([]);
		$sessionUser = Auth::instance()->user();
		$dbUser = $sessionUser && isset($sessionUser['id'])
			? User::findById((int) $sessionUser['id'])
			: null;

		if ($dbUser && !empty($dbUser['password_changed_at'])) {
			header('Location: /');
			return;
		}

		$this->view('auth/change-password-first', [
			'layout' => 'auth',
			'user' => $dbUser ?: $sessionUser,
		]);
	}

	public function updatePasswordFirst(): void
	{
		$this->requireAuth([]);
		$sessionUser = Auth::instance()->user();
		if (!$sessionUser || !isset($sessionUser['id'])) {
			header('Location: /login');
			return;
		}

		$userId = (int) $sessionUser['id'];
		$dbUser = User::findById($userId);

		if ($dbUser && !empty($dbUser['password_changed_at'])) {
			header('Location: /');
			return;
		}

		$displayUser = $dbUser ?: $sessionUser;
		$newPassword = trim($_POST['password'] ?? '');
		$confirmPassword = trim($_POST['password_confirm'] ?? '');

		if ($newPassword === '' || $confirmPassword === '') {
			$this->view('auth/change-password-first', [
				'layout' => 'auth',
				'user' => $displayUser,
				'error' => 'Informe a nova senha e confirmação.',
			]);
			return;
		}

		if ($newPassword !== $confirmPassword) {
			$this->view('auth/change-password-first', [
				'layout' => 'auth',
				'user' => $displayUser,
				'error' => 'As senhas não conferem.',
			]);
			return;
		}

		if (strlen($newPassword) < 6) {
			$this->view('auth/change-password-first', [
				'layout' => 'auth',
				'user' => $displayUser,
				'error' => 'A senha deve ter no mínimo 6 caracteres.',
			]);
			return;
		}

		$updated = User::updatePasswordChanged($userId, $newPassword);

		if (!$updated) {
			$this->view('auth/change-password-first', [
				'layout' => 'auth',
				'user' => $displayUser,
				'error' => 'Erro ao atualizar senha. Tente novamente.',
			]);
			return;
		}

		header('Location: /');
		exit;
	}

	public function logout(): void
	{
		Auth::instance()->logout();
		header('Location: /login');
	}
}


