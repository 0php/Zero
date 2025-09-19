<?php

declare(strict_types=1);

namespace Zero\Lib\Mail;

use Closure;
use InvalidArgumentException;
use Zero\Lib\Mail\Transport\SmtpTransport;

final class Mailer
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    private function __construct()
    {
        $this->config = config('mail');
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Send a message using the configured mail transport.
     */
    public static function send(Closure $callback): void
    {
        self::instance()->dispatch($callback);
    }

    /**
     * Send a raw message without a callback.
     */
    public static function raw(string $to, string $subject, string $body, bool $isHtml = false): void
    {
        self::instance()->dispatch(function (Message $message) use ($to, $subject, $body, $isHtml) {
            $message->to($to)->subject($subject);

            if ($isHtml) {
                $message->html($body);
            } else {
                $message->text($body);
            }
        });
    }

    /**
     * @param Closure(Message):void $callback
     */
    public function dispatch(Closure $callback): void
    {
        $message = new Message($this->config['from'] ?? []);

        $callback($message);

        if ($message->getFrom() === null) {
            throw new MailException('Email message must define a "From" address.');
        }

        if (empty($message->getEnvelopeRecipients())) {
            throw new MailException('Email message must define at least one recipient.');
        }

        $transport = $this->resolveTransport($this->config['default'] ?? 'smtp');
        $transport->send($message);
    }

    private function resolveTransport(string $driver): SmtpTransport
    {
        return match ($driver) {
            'smtp' => new SmtpTransport($this->config['smtp'] ?? []),
            default => throw new InvalidArgumentException(sprintf('Unsupported mail driver "%s".', $driver)),
        };
    }
}
