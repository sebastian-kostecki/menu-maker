<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\SymfonyMailerHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Builds the `mail_alert` log channel: e-mails entries at `error` and above
 * through the configured Laravel mailer, once per window per identical entry,
 * and never lets a mail failure break the reporting path.
 */
class CreateMailAlertLogger
{
    public const SUBJECT_MAX_LENGTH = 150;

    /**
     * @param  array{to?: string|null, level?: string, dedup_seconds?: int|string, dedup_store?: string|null}  $config
     */
    public function __invoke(array $config): Logger
    {
        $recipient = $config['to'] ?? null;

        if (! is_string($recipient) || trim($recipient) === '') {
            return new Logger('mail_alert', [new NullHandler]);
        }

        $level = Logger::toMonologLevel($config['level'] ?? 'error');
        $dedupSeconds = (int) ($config['dedup_seconds'] ?? 3600);
        $store = $config['dedup_store'] ?? storage_path('logs/mail-alert-dedup.log');

        $email = (new Email)
            ->from(new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ))
            ->to(trim($recipient));

        $mailHandler = new class(Mail::mailer()->getSymfonyTransport(), $email, $level) extends SymfonyMailerHandler
        {
            protected function getSubjectFormatter(?string $format): FormatterInterface
            {
                return new class(sprintf('[%s %s]', config('app.name'), config('app.env'))) implements FormatterInterface
                {
                    public function __construct(private readonly string $prefix) {}

                    public function format(LogRecord $record): string
                    {
                        $firstLine = Str::before($record->message, "\n");

                        return Str::limit(sprintf(
                            '%s %s: %s',
                            $this->prefix,
                            strtolower($record->level->getName()),
                            $firstLine,
                        ), CreateMailAlertLogger::SUBJECT_MAX_LENGTH);
                    }

                    public function formatBatch(array $records): string
                    {
                        return implode("\n", array_map($this->format(...), $records));
                    }
                };
            }
        };
        $mailHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\ncontext: %context%\nextra: %extra%\n",
            null,
            true,
            true,
            true,
        ));

        $deduplicated = new DeduplicationHandler(
            new WhatFailureGroupHandler([$mailHandler]),
            $store,
            $level,
            $dedupSeconds,
        );

        return new Logger('mail_alert', [new WhatFailureGroupHandler([$deduplicated])]);
    }
}
