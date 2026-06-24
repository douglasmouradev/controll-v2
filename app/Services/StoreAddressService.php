<?php
declare(strict_types=1);

namespace App\Services;

final class StoreAddressService
{
	/** @var array<int, string>|null */
	private static ?array $siglaCache = null;

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
