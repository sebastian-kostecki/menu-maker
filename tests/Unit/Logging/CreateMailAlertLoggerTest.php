<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(function (): void {
    $this->configureAlertChannel = function (?string $to = 'ops@example.com', array $overrides = []): void {
        config([
            'logging.channels.mail_alert.to' => $to,
            'logging.channels.mail_alert.cache_store' => 'array',
            ...collect($overrides)->mapWithKeys(fn ($value, $key): array => ["logging.channels.mail_alert.$key" => $value])->all(),
        ]);
        Log::forgetChannel('mail_alert');
        Mail::forgetMailers();
    };

    $this->sentMessages = fn (): array => Mail::mailer()->getSymfonyTransport()->messages()->all();
});

afterEach(function (): void {
    Context::flush();
});

it('sends one e-mail immediately, with the context user_id, for an error entry', function (): void {
    ($this->configureAlertChannel)();
    Context::add('user_id', 4242);

    Log::channel('mail_alert')->error('Something broke');

    $messages = ($this->sentMessages)();

    expect($messages)->toHaveCount(1);

    $email = $messages[0]->getOriginalMessage();

    expect($email->getTo()[0]->getAddress())->toBe('ops@example.com')
        ->and($email->getSubject())->toContain('error: Something broke')
        ->and($email->getTextBody())->toContain('user_id')
        ->and($email->getTextBody())->toContain('4242');
});

it('sends nothing for entries below error level', function (): void {
    ($this->configureAlertChannel)();

    Log::channel('mail_alert')->warning('Just a warning');

    expect(($this->sentMessages)())->toBeEmpty();
});

it('sends one e-mail when the same error is logged twice within the window', function (): void {
    ($this->configureAlertChannel)();

    Log::channel('mail_alert')->error('Repeated failure');
    Log::channel('mail_alert')->error('Repeated failure');

    expect(($this->sentMessages)())->toHaveCount(1);
});

it('treats the same exception thrown from the same line as one error despite a variable message', function (): void {
    ($this->configureAlertChannel)();

    foreach ([101, 202, 303] as $sessionId) {
        $exception = new RuntimeException("select * from sessions where id = $sessionId"); // same line each iteration
        Log::channel('mail_alert')->error($exception->getMessage(), ['exception' => $exception]);
    }

    expect(($this->sentMessages)())->toHaveCount(1);
});

it('does not merge different exceptions that share a message', function (): void {
    ($this->configureAlertChannel)();

    $first = new RuntimeException('Same message');
    $second = new RuntimeException('Same message');

    Log::channel('mail_alert')->error('Same message', ['exception' => $first]);
    Log::channel('mail_alert')->error('Same message', ['exception' => $second]);

    expect(($this->sentMessages)())->toHaveCount(2);
});

it('caps the number of alerts per window', function (): void {
    ($this->configureAlertChannel)('ops@example.com', ['max_per_window' => 2]);

    Log::channel('mail_alert')->error('First');
    Log::channel('mail_alert')->error('Second');
    Log::channel('mail_alert')->error('Third');

    expect(($this->sentMessages)())->toHaveCount(2);
});

it('keeps the subject on a single line', function (): void {
    ($this->configureAlertChannel)();

    Log::channel('mail_alert')->error("First line\r\nBcc: attacker@example.com\nthird");

    $email = ($this->sentMessages)()[0]->getOriginalMessage();

    expect($email->getSubject())->toContain('First line')
        ->and($email->getSubject())->not->toMatch('/[\r\n]/')
        ->and($email->getSubject())->not->toContain('Bcc');
});

it('does nothing and does not throw without a recipient', function (): void {
    ($this->configureAlertChannel)(null);

    Log::channel('mail_alert')->error('No one to tell');

    expect(($this->sentMessages)())->toBeEmpty();
});

it('ignores invalid recipients and keeps the valid ones', function (): void {
    ($this->configureAlertChannel)('not-an-address, ops@example.com , second@example.com');

    Log::channel('mail_alert')->error('Two recipients');

    $email = ($this->sentMessages)()[0]->getOriginalMessage();

    expect(collect($email->getTo())->map->getAddress()->all())->toBe(['ops@example.com', 'second@example.com']);
});

it('does nothing when every recipient is invalid', function (): void {
    ($this->configureAlertChannel)('not-an-address');

    Log::channel('mail_alert')->error('Nobody valid');

    expect(($this->sentMessages)())->toBeEmpty();
});

it('does not propagate a failing mail transport and does not retry it for every repeat', function (): void {
    $attempts = new ArrayObject;

    Mail::extend('throwing', fn () => new class($attempts) extends AbstractTransport
    {
        public function __construct(private readonly ArrayObject $attempts)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            $this->attempts[] = 1;

            throw new RuntimeException('SMTP down');
        }

        public function __toString(): string
        {
            return 'throwing';
        }
    });
    config(['mail.mailers.throwing' => ['transport' => 'throwing'], 'mail.default' => 'throwing']);
    ($this->configureAlertChannel)();

    Log::channel('mail_alert')->error('Reporting while mail is down');
    Log::channel('mail_alert')->error('Reporting while mail is down');

    expect($attempts)->toHaveCount(1);
});

it('does not recurse when the mailer writes to the log and mail_alert is part of the default stack', function (): void {
    config([
        'mail.default' => 'log',
        'logging.default' => 'stack',
        'logging.channels.stack.channels' => ['mail_alert'],
    ]);
    ($this->configureAlertChannel)();
    Log::forgetChannel('stack');

    expect(fn () => Log::error('Mailer is the log and the log mails'))->not->toThrow(Throwable::class);
});
