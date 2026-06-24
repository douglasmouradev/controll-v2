<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SdwanAccessLink;
use App\Models\SdwanEntry;
use App\Services\Cache;
use App\Services\SdwanEntryService;

final class SdwanPublicController extends Controller
{
	public function form(): void
	{
		$code = SdwanAccessLink::normalizeCode((string) ($_GET['code'] ?? ''));
		if ($code === '') {
			$this->view('sdwan/invalid', ['layout' => 'auth', 'title' => 'Link inválido']);
			return;
		}

		$link = SdwanAccessLink::findActiveByCode($code);
		if ($link === null) {
			$this->view('sdwan/expired', ['layout' => 'auth', 'title' => 'Link expirado']);
			return;
		}

		$this->view('sdwan/public-form', [
			'layout' => 'auth',
			'title' => 'Cadastro SDWAN',
			'code' => $code,
			'expiresAt' => (string) ($link['expires_at'] ?? ''),
		]);
	}

	public function submit(): void
	{
		$code = SdwanAccessLink::normalizeCode((string) ($_POST['code'] ?? ''));
		$link = $code !== '' ? SdwanAccessLink::findActiveByCode($code) : null;
		if ($link === null) {
			$this->json(['success' => false, 'message' => 'Link inválido ou expirado. Solicite um novo link ao administrador.'], 403);
			return;
		}

		$validation = SdwanEntry::validateInput($_POST);
		if (!$validation['success']) {
			$this->json(['success' => false, 'message' => $validation['message'] ?? 'Dados inválidos'], 422);
			return;
		}

		$id = 0;
		try {
			$createdBy = isset($link['created_by']) ? (int) $link['created_by'] : null;
			$id = SdwanEntry::create($validation['data'], $createdBy > 0 ? $createdBy : null);
			SdwanEntryService::applyImageUpload($id, $_POST, $_FILES);
			$this->json([
				'success' => true,
				'message' => 'Registro enviado com sucesso. Obrigado!',
			]);
		} catch (\InvalidArgumentException $e) {
			if ($id > 0) {
				SdwanEntry::delete($id);
			}
			$this->json(['success' => false, 'message' => $e->getMessage()], 422);
		} catch (\Throwable $e) {
			error_log('Erro no cadastro público SDWAN: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao salvar registro. Tente novamente.'], 500);
		}
	}

	public function storeAddresses(): void
	{
		$code = SdwanAccessLink::normalizeCode((string) ($_GET['code'] ?? ''));
		if ($code === '' || SdwanAccessLink::findActiveByCode($code) === null) {
			$this->json(['success' => false, 'message' => 'Link inválido ou expirado'], 403);
			return;
		}

		$path = BASE_PATH . '/endereco.json';
		if (!is_file($path) || !is_readable($path)) {
			$this->json(['success' => false, 'message' => 'Arquivo de endereços não encontrado'], 500);
			return;
		}

		$mtime = (int) (@filemtime($path) ?: 0);
		$cacheKey = 'store:addresses:public:' . $mtime;
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$raw = trim((string) file_get_contents($path));
		if ($raw === '') {
			$payload = ['success' => true, 'data' => []];
			Cache::set($cacheKey, $payload, 3600);
			$this->json($payload);
			return;
		}

		$json = '[' . rtrim($raw, ", \n\r\t") . ']';
		$data = json_decode($json, true);
		if (!is_array($data)) {
			$this->json(['success' => false, 'message' => 'Erro ao ler arquivo de endereços'], 500);
			return;
		}

		$result = [];
		foreach ($data as $row) {
			if (!is_array($row)) {
				continue;
			}
			$sigla = trim((string) ($row['SIGLA'] ?? ''));
			$endereco = trim((string) ($row['ENDEREÇO'] ?? ($row['ENDERECO'] ?? '')));
			if ($sigla === '' || $endereco === '') {
				continue;
			}
			$result[] = ['sigla' => $sigla, 'endereco' => $endereco];
		}

		$payload = ['success' => true, 'data' => $result];
		Cache::set($cacheKey, $payload, 3600);
		$this->json($payload);
	}
}
