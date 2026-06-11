<?php
declare(strict_types=1);

use App\Services\TwoFactor;
use PHPUnit\Framework\TestCase;

final class TwoFactorTest extends TestCase
{
	public function testGenerateSecretLength(): void
	{
		$secret = TwoFactor::generateSecret(16);
		$this->assertSame(16, strlen($secret));
	}

	public function testVerifyValidCode(): void
	{
		$secret = 'JBSWY3DPEHPK3PXP';
		$timeSlice = (int) floor(time() / 30);
		$code = $this->totpForTest($secret, $timeSlice);
		$this->assertTrue(TwoFactor::verify($secret, $code));
	}

	public function testVerifyRejectsInvalidCode(): void
	{
		$this->assertFalse(TwoFactor::verify('JBSWY3DPEHPK3PXP', '000000'));
	}

	private function totpForTest(string $secret, int $timeSlice): string
	{
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = strtoupper($secret);
		$buffer = 0;
		$bitsLeft = 0;
		$key = '';
		for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
			$pos = strpos($alphabet, $secret[$i]);
			if ($pos === false) {
				continue;
			}
			$buffer = ($buffer << 5) | $pos;
			$bitsLeft += 5;
			if ($bitsLeft >= 8) {
				$bitsLeft -= 8;
				$key .= chr(($buffer >> $bitsLeft) & 0xff);
			}
		}
		$time = pack('N*', 0, $timeSlice);
		$hash = hash_hmac('sha1', $time, $key, true);
		$offset = ord($hash[19]) & 0x0f;
		$binary = (
			((ord($hash[$offset]) & 0x7f) << 24)
			| ((ord($hash[$offset + 1]) & 0xff) << 16)
			| ((ord($hash[$offset + 2]) & 0xff) << 8)
			| (ord($hash[$offset + 3]) & 0xff)
		);

		return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
	}
}
