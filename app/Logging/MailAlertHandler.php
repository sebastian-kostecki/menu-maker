<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sends an alert e-mail for a log record, immediately and without buffering,
 * so it works the same in web requests and in long-running queue workers.
 *
 * Repeats of the same error are suppressed for `$dedupSeconds`, and at most
 * `$maxPerWindow` alerts go out per window. The dedup state lives in a cache
 * store that does not depend on the database, which may be the very thing
 * that is down. The mail transport is resolved at send time, never while the
 * log channel is being built.
 */
final class MailAlertHandler extends AbstractProcessingHandler
{
    public const SUBJECT_MAX_LENGTH = 150;

    private const FAILURE_BACKOFF_SECONDS = 60;

    private const COUNTER_KEY = 'mail-alert:sent-in-window';

    /**
     * @param  list<string>  $recipients
     */
    public function __construct(
        private readonly array $recipients,
        int|string|Level $level,
        private readonly int $dedupSeconds,
        private readonly int $maxPerWindow,
        private readonly string $cacheStore,
    ) {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        $cache = Cache::store($this->cacheStore);
        $key = $this->dedupKey($record);

        if ($cache->has($key) || $this->windowIsFull($cache)) {
            return;
        }

        try {
            $this->send($record);
        } catch (Throwable) {
            // Mail is down: do not suppress this error for the whole window,
            // only back off briefly so every repeat does not retry SMTP.
            $cache->put($key, true, self::FAILURE_BACKOFF_SECONDS);

            return;
        }

        $cache->put($key, true, $this->dedupSeconds);
        $cache->add(self::COUNTER_KEY, 0, $this->dedupSeconds);
        $cache->increment(self::COUNTER_KEY);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\ncontext: %context%\nextra: %extra%\n",
            null,
            true,
            true,
            true,
        );
    }

    /**
     * Same exception class thrown from the same line is one error, whatever its
     * (possibly variable) message says. Plain log entries fall back to level + first line.
     */
    private function dedupKey(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        $fingerprint = $exception instanceof Throwable
            ? implode('|', [$exception::class, $exception->getFile(), $exception->getLine()])
            : $record->level->getName().'|'.$this->firstLine($record->message);

        return 'mail-alert:'.md5($fingerprint);
    }

    private function windowIsFull(Repository $cache): bool
    {
        return (int) $cache->get(self::COUNTER_KEY, 0) >= $this->maxPerWindow;
    }

    private function send(LogRecord $record): void
    {
        $email = (new Email)
            ->from(new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ))
            ->to(...$this->recipients)
            ->subject($this->subject($record))
            ->text((string) $record->formatted);

        Mail::mailer()->getSymfonyTransport()->send($email);
    }

    private function subject(LogRecord $record): string
    {
        return Str::limit(sprintf(
            '[%s %s] %s: %s',
            config('app.name'),
            config('app.env'),
            strtolower($record->level->getName()),
            $this->firstLine($record->message),
        ), self::SUBJECT_MAX_LENGTH);
    }

    private function firstLine(string $message): string
    {
        return trim((string) preg_replace('/[[:cntrl:]]+/', ' ', (string) preg_replace('/[\r\n].*/s', '', $message)));
    }
}
