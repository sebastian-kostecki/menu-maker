<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(function (): void {
    $this->dedupStore = tempnam(sys_get_temp_dir(), 'mail-alert-dedup-');
    unlink($this->dedupStore);

    $this->configureAlertChannel = function (?string $to = 'ops@example.com'): void {
        config([
            'logging.channels.mail_alert.to' => $to,
            'logging.channels.mail_alert.dedup_store' => $this->dedupStore,
        ]);
        Log::forgetChannel('mail_alert');
        Mail::forgetMailers();
    };

    $this->flushAlertChannel = function (): void {
        Log::channel('mail_alert')->getLogger()->close();
    };

    $this->sentMessages = fn (): array => Mail::mailer()->getSymfonyTransport()->messages()->all();
});

afterEach(function (): void {
    @unlink($this->dedupStore);
    Context::flush();
});

it('sends one e-mail with the context user_id for an error entry', function (): void {
    ($this->configureAlertChannel)();
    Context::add('user_id', 4242);

    Log::channel('mail_alert')->error('Something broke');
    ($this->flushAlertChannel)();

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
    ($this->flushAlertChannel)();

    expect(($this->sentMessages)())->toBeEmpty();
});

it('sends one e-mail when the same error is logged twice within the window', function (): void {
    ($this->configureAlertChannel)();

    Log::channel('mail_alert')->error('Repeated failure');
    ($this->flushAlertChannel)();
    Log::channel('mail_alert')->error('Repeated failure');
    ($this->flushAlertChannel)();

    expect(($this->sentMessages)())->toHaveCount(1);
});

it('does nothing and does not throw without a recipient', function (): void {
    ($this->configureAlertChannel)(null);

    Log::channel('mail_alert')->error('No one to tell');
    ($this->flushAlertChannel)();

    expect(($this->sentMessages)())->toBeEmpty();
});

it('does not propagate a failing mail transport', function (): void {
    Mail::extend('throwing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
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
    ($this->flushAlertChannel)();

    expect(true)->toBeTrue();
});
