<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SdwanAccessLink;
use App\Models\SdwanEntry;
use App\Services\Cache;
use App\Services\SdwanEntryService;
use App\Services\SdwanNotifier;
use App\Services\SdwanAudit;
use App\Services\StoreAddressService;

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
		if ($link === null || !SdwanAccessLink::canAcceptSubmission($link)) {
			$this->view('sdwan/expired', ['layout' => 'auth', 'title' => 'Link expirado']);
			return;
		}

		$presented = SdwanAccessLink::present($link);
		$this->view('sdwan/public-form', [
			'layout' => 'auth',
			'title' => 'Cadastro SDWAN',
			'code' => $code,
			'expiresAt' => (string) ($link['expires_at'] ?? ''),
			'linkInfo' => $presented,
		]);
	}

	public function submit(): void
	{
		$code = SdwanAccessLink::normalizeCode((string) ($_POST['code'] ?? ''));
		$link = $code !== '' ? SdwanAccessLink::findActiveByCode($code) : null;
		if ($link === null || !SdwanAccessLink::canAcceptSubmission($link)) {
			$this->json(['success' => false, 'message' => 'Link inválido, expirado ou limite de cadastros atingido.'], 403);
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
			$id = SdwanEntry::create(
				$validation['data'],
				$createdBy > 0 ? $createdBy : null,
				[
					'entry_source' => 'public',
					'access_link_id' => (int) ($link['id'] ?? 0) ?: null,
				]
			);
			SdwanEntryService::applyImageUpload($id, $_POST, $_FILES);
			$linkId = (int) ($link['id'] ?? 0);
			if ($linkId > 0) {
				SdwanAccessLink::incrementSubmission($linkId);
			}
			$notifyUserId = (int) ($link['created_by'] ?? 0);
			if ($notifyUserId > 0) {
				SdwanNotifier::notifyPublicSubmission($id, $notifyUserId, $validation['data']);
			}
			SdwanAudit::record('public_create', 'entry:' . $id);
			$entry = SdwanEntry::findById($id);
			$response = [
				'success' => true,
				'message' => 'Registro enviado com sucesso. Obrigado!',
				'entry' => $entry,
			];
			if (!empty($validation['warning'])) {
				$response['warning'] = $validation['warning'];
			}
			$this->json($response);
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
		if ($code === '' || ($link = SdwanAccessLink::findActiveByCode($code)) === null || !SdwanAccessLink::canAcceptSubmission($link)) {
			$this->json(['success' => false, 'message' => 'Link inválido ou expirado'], 403);
			return;
		}

		$path = StoreAddressService::addressesFile();
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

		$payload = StoreAddressService::apiPayload();
		Cache::set($cacheKey, $payload, 3600);
		$this->json($payload);
	}
}
