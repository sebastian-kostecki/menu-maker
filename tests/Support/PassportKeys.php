<?php

declare(strict_types=1);

namespace Tests\Support;

use phpseclib4\Crypt\RSA;

/**
 * A throwaway RSA key pair for the Passport tests, generated once per process, never written to disk or committed.
 */
final class PassportKeys
{
    /** @var array{private: string, public: string}|null */
    private static ?array $pair = null;

    public static function install(): void
    {
        self::$pair ??= self::generate();

        config()->set('passport.private_key', self::$pair['private']);
        config()->set('passport.public_key', self::$pair['public']);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generate(): array
    {
        $key = RSA::createKey(2048);

        return ['private' => (string) $key, 'public' => (string) $key->getPublicKey()];
    }
}
