<?php
declare(strict_types=1);

namespace App\Services;

final class StoreAddressService
{
	/** @var array<int, string>|null */
	private static ?array $siglaCache = null;

	public static function clearCache(): void
	{
		self::$siglaCache = null;
	}

	public static function findBySigla(string $sigla): ?array
	{
		$sigla = strtoupper(trim($sigla));
		foreach (self::loadAddresses() as $row) {
			if ($row['sigla'] === $sigla) {
				return $row;
			}
		}

		return null;
	}

	/** @return array{success: bool, message?: string, total?: int} */
	public static function uploadAddressesFile(array $file): array
	{
		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return ['success' => false, 'message' => 'Arquivo não enviado'];
		}

		$tmp = (string) ($file['tmp_name'] ?? '');
		$name = (string) ($file['name'] ?? '');
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, ['json'], true)) {
			return ['success' => false, 'message' => 'Envie um arquivo JSON de lojas'];
		}

		$raw = file_get_contents($tmp);
		if ($raw === false || trim($raw) === '') {
			return ['success' => false, 'message' => 'Arquivo vazio'];
		}

		$json = '[' . rtrim(trim($raw), ", \n\r\t") . ']';
		$data = json_decode($json, true);
		if (!is_array($data) || $data === []) {
			return ['success' => false, 'message' => 'JSON de lojas inválido'];
		}

		$path = self::addressesFile();
		$backup = $path . '.backup.' . date('YmdHis');
		if (is_file($path)) {
			@copy($path, $backup);
		}

		if (@file_put_contents($path, $raw) === false) {
			return ['success' => false, 'message' => 'Não foi possível salvar o arquivo de lojas'];
		}

		self::clearCache();
		$total = count(self::loadAddresses());

		return ['success' => true, 'message' => 'Planilha de lojas atualizada (' . $total . ' lojas)', 'total' => $total];
	}

	public static function addressesFile(): string
	{
		return BASE_PATH . '/endereco.json';
	}

	/** @return array<int, array{sigla: string, endereco: string}> */
	public static function loadAddresses(): array
	{
		$path = self::addressesFile();
		if (!is_file($path) || !is_readable($path)) {
			return [];
		}

		$raw = trim((string) file_get_contents($path));
		if ($raw === '') {
			return [];
		}

		$json = '[' . rtrim($raw, ", \n\r\t") . ']';
		$data = json_decode($json, true);
		if (!is_array($data)) {
			return [];
		}

		$result = [];
		foreach ($data as $row) {
			if (!is_array($row)) {
				continue;
			}
			$sigla = strtoupper(trim((string) ($row['SIGLA'] ?? '')));
			$endereco = trim((string) ($row['ENDEREÇO'] ?? ($row['ENDERECO'] ?? '')));
			if ($sigla === '' || $endereco === '') {
				continue;
			}
			$result[] = ['sigla' => $sigla, 'endereco' => $endereco];
		}

		usort($result, static fn (array $a, array $b): int => strcmp($a['sigla'], $b['sigla']));

		return $result;
	}

	/** @return array<int, string> */
	public static function loadSiglas(): array
	{
		if (self::$siglaCache !== null) {
			return self::$siglaCache;
		}

		self::$siglaCache = array_values(array_unique(array_map(
			static fn (array $row): string => $row['sigla'],
			self::loadAddresses()
		)));

		return self::$siglaCache;
	}

	public static function isValidSigla(string $sigla): bool
	{
		$sigla = strtoupper(trim($sigla));
		if ($sigla === '') {
			return false;
		}

		return in_array($sigla, self::loadSiglas(), true);
	}

	/** @return array{success: bool, data: array<int, array{sigla: string, endereco: string}>} */
	public static function apiPayload(): array
	{
		return [
			'success' => true,
			'data' => self::loadAddresses(),
		];
	}
}
