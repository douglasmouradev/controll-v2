<?php
declare(strict_types=1);

namespace App\Services;

final class Cache
{
	private static function dir(): string
	{
		$dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/cache';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}

		return $dir;
	}

	private static function path(string $key): string
	{
		return self::dir() . '/' . hash('sha256', $key) . '.cache';
	}

	public static function get(string $key): mixed
	{
		$file = self::path($key);
		if (!is_file($file)) {
			return null;
		}

		$raw = @file_get_contents($file);
		if ($raw === false) {
			return null;
		}

		$data = @unserialize($raw, ['allowed_classes' => false]);
		if (!is_array($data) || !isset($data['expires'], $data['value'])) {
			@unlink($file);
			return null;
		}

		if ($data['expires'] !== 0 && $data['expires'] < time()) {
			@unlink($file);
			return null;
		}

		return $data['value'];
	}

	public static function set(string $key, mixed $value, int $ttlSeconds = 60): void
	{
		$payload = [
			'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
			'value' => $value,
		];
		@file_put_contents(self::path($key), serialize($payload), LOCK_EX);
	}

	public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
	{
		$cached = self::get($key);
		if ($cached !== null) {
			return $cached;
		}

		$value = $callback();
		self::set($key, $value, $ttlSeconds);

		return $value;
	}

	public static function forget(string $key): void
	{
		$file = self::path($key);
		if (is_file($file)) {
			@unlink($file);
		}
	}

	public static function flush(): void
	{
		foreach (glob(self::dir() . '/*.cache') ?: [] as $file) {
			@unlink($file);
		}
	}
}
