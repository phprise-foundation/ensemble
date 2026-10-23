<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\SodiumSecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SodiumSecretCipherTest extends TestCase
{
    public function testEncryptsAndDecrypts(): void
    {
        $cipher = new SodiumSecretCipher(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $ciphertext = $cipher->encrypt('github_pat_123');

        self::assertNotSame('github_pat_123', $ciphertext);
        self::assertSame('github_pat_123', $cipher->decrypt($ciphertext));
    }

    public function testUsesAFreshNoncePerEncryption(): void
    {
        $cipher = new SodiumSecretCipher(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        self::assertNotSame($cipher->encrypt('same-value'), $cipher->encrypt('same-value'));
    }

    public function testRejectsTamperedCiphertext(): void
    {
        $cipher = new SodiumSecretCipher(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $raw = base64_decode($cipher->encrypt('secret'), true);
        self::assertIsString($raw);
        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 0xFF);
        $tampered = base64_encode($raw);

        $this->expectException(RuntimeException::class);
        $cipher->decrypt($tampered);
    }

    public function testFailsWhenNoKeyIsConfigured(): void
    {
        $cipher = new SodiumSecretCipher('');

        $this->expectException(RuntimeException::class);
        $cipher->encrypt('secret');
    }
}
