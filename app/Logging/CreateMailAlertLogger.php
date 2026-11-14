<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\NullHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;

/**
 * Builds the `mail_alert` log channel: e-mails entries at `error` and above
 * through the configured Laravel mailer, deduplicated, and never lets a
 * failure in the alerting path break the request or job being reported.
 */
class CreateMailAlertLogger
{
    /**
     * @param  array{to?: string|null, level?: string, dedup_seconds?: int|string, max_per_window?: int|string, cache_store?: string}  $config
     */
    public function __invoke(array $config): Logger
    {
        $recipients = $this->recipients($config['to'] ?? null);

        if ($recipients === []) {
            return new Logger('mail_alert', [new NullHandler]);
        }

        $handler = new MailAlertHandler(
            $recipients,
            Logger::toMonologLevel($config['level'] ?? 'error'),
            (int) ($config['dedup_seconds'] ?? 3600),
            (int) ($config['max_per_window'] ?? 20),
            (string) ($config['cache_store'] ?? 'file'),
        );

        return new Logger('mail_alert', [new WhatFailureGroupHandler([$handler])]);
    }

    /**
     * Comma-separated list; invalid entries are dropped instead of disabling the channel.
     *
     * @return list<string>
     */
    private function recipients(mixed $configured): array
    {
        if (! is_string($configured)) {
            return [];
        }

        $valid = array_filter(
            array_map('trim', explode(',', $configured)),
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        );

        return array_values($valid);
    }
}
