<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Contract\Security\SecretCipher;
use InvalidArgumentException;
use Override;
use RuntimeException;

final readonly class SodiumSecretCipher implements SecretCipher
{
    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    public function __construct(private string $key)
    {
        if ($key !== '' && strlen($key) !== self::KEY_BYTES) {
            throw new InvalidArgumentException('The encryption key must have 32 bytes.');
        }
    }

    public static function fromEnvironment(): self
    {
        $encoded = getenv('APP_ENCRYPTION_KEY');
        if ($encoded === false || $encoded === '') {
            return new self('');
        }

        $key = base64_decode($encoded, true);
        if ($key === false) {
            throw new InvalidArgumentException('APP_ENCRYPTION_KEY must be base64.');
        }

        return new self($key);
    }

    #[Override]
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key());

        return base64_encode($nonce . $ciphertext);
    }

    #[Override]
    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) <= self::NONCE_BYTES) {
            throw new InvalidArgumentException('The ciphertext is malformed.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $encrypted = substr($raw, self::NONCE_BYTES);
        $plaintext = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key());
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt the secret.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        if ($this->key === '') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is not configured.');
        }

        return $this->key;
    }
}
