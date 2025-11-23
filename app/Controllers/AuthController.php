<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\Auth;

final class AuthController extends Controller
{
	public function loginForm(): void
	{
		$this->view('auth/login', []);
	}

	public function login(): void
	{
		$email = trim($_POST['email'] ?? '');
		$password = (string) ($_POST['password'] ?? '');
		if ($email === '' || $password === '') {
			$this->view('auth/login', ['error' => 'Informe e-mail e senha.']);
			return;
		}
		$user = User::findByEmail($email);
		if (!$user || !password_verify($password, $user['password'])) {
			$this->view('auth/login', ['error' => 'Credenciais inválidas.']);
			return;
		}
		Auth::instance()->login($user);
		
		// Verificar se é primeiro login (password_changed_at é NULL)
		if ($user && empty($user['password_changed_at'])) {
			header('Location: /change-password-first');
			return;
		}
		
		header('Location: /');
	}

	public function changePasswordFirst(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		
		// Verificar se já trocou a senha
		if ($user && !empty($user['password_changed_at'])) {
			header('Location: /');
			return;
		}
		
		$this->view('auth/change-password-first', ['user' => $user]);
	}

	public function updatePasswordFirst(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		
		// Verificar se já trocou a senha
		if ($user && !empty($user['password_changed_at'])) {
			header('Location: /');
			return;
		}
		
		$newPassword = trim($_POST['password'] ?? '');
		$confirmPassword = trim($_POST['password_confirm'] ?? '');
		
		if ($newPassword === '' || $confirmPassword === '') {
			$this->view('auth/change-password-first', [
				'user' => $user,
				'error' => 'Informe a nova senha e confirmação.'
			]);
			return;
		}
		
		if ($newPassword !== $confirmPassword) {
			$this->view('auth/change-password-first', [
				'user' => $user,
				'error' => 'As senhas não conferem.'
			]);
			return;
		}
		
		if (strlen($newPassword) < 6) {
			$this->view('auth/change-password-first', [
				'user' => $user,
				'error' => 'A senha deve ter no mínimo 6 caracteres.'
			]);
			return;
		}
		
		// Atualizar senha e marcar como trocada
		$updated = User::updatePasswordChanged((int) $user['id'], $newPassword);
		
		if ($updated) {
			header('Location: /');
		} else {
			$this->view('auth/change-password-first', [
				'user' => $user,
				'error' => 'Erro ao atualizar senha. Tente novamente.'
			]);
		}
	}

	public function logout(): void
	{
		Auth::instance()->logout();
		header('Location: /login');
	}
}


