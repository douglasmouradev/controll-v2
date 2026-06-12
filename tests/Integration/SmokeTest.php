<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
	private static ?string $baseUrl = null;

	public static function setUpBeforeClass(): void
	{
		self::$baseUrl = getenv('APP_TEST_URL') ?: 'http://127.0.0.1:8000';
	}

	public function testLoginPageLoads(): void
	{
		$response = $this->httpGet('/login');
		$this->assertSame(200, $response['status']);
		$this->assertStringContainsString('Login', $response['body']);
	}

	public function testHealthEndpoint(): void
	{
		$response = $this->httpGet('/health');
		$this->assertContains($response['status'], [200, 503]);
		$data = json_decode($response['body'], true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('status', $data);
		$this->assertArrayHasKey('checks', $data);
	}

	public function testProtectedRouteRedirectsGuests(): void
	{
		$response = $this->httpGet('/dashboard/summary', false);
		$this->assertContains($response['status'], [302, 401, 403]);
	}

	/**
	 * @return array{status:int, body:string, headers:array<string, string>}
	 */
	private function httpGet(string $path, bool $followRedirects = true): array
	{
		$url = rtrim((string) self::$baseUrl, '/') . $path;
		$ch = curl_init($url);
		if ($ch === false) {
			$this->fail('curl_init failed');
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_FOLLOWLOCATION => $followRedirects,
			CURLOPT_TIMEOUT => 10,
		]);

		$raw = curl_exec($ch);
		if ($raw === false) {
			$err = curl_error($ch);
			curl_close($ch);
			$this->fail('HTTP request failed: ' . $err);
		}

		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		$headerText = substr($raw, 0, $headerSize);
		$body = substr($raw, $headerSize);
		$headers = [];
		foreach (explode("\r\n", $headerText) as $line) {
			if (str_contains($line, ':')) {
				[$name, $value] = explode(':', $line, 2);
				$headers[strtolower(trim($name))] = trim($value);
			}
		}

		return ['status' => $status, 'body' => $body, 'headers' => $headers];
	}
}
