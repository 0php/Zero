<?php

declare(strict_types=1);

namespace Zero\Lib\Mail;

final class Message
{
    /**
     * @var array{address: string, name: string|null}|null
     */
    private ?array $from = null;

    /**
     * @var array{address: string, name: string|null}|null
     */
    private ?array $replyTo = null;

    /**
     * @var array<int, array{address: string, name: string|null}>
     */
    private array $to = [];

    /**
     * @var array<int, array{address: string, name: string|null}>
     */
    private array $cc = [];

    /**
     * @var array<int, array{address: string, name: string|null}>
     */
    private array $bcc = [];

    private string $subject = '';

    private string $body = '';

    private string $contentType = 'text/plain; charset=UTF-8';

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    public function __construct(array $defaultFrom = [])
    {
        if (! empty($defaultFrom['address'])) {
            $this->from($defaultFrom['address'], $defaultFrom['name'] ?? null);
        }
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->from = [
            'address' => $this->sanitizeAddress($address),
            'name' => $this->sanitizeName($name),
        ];

        return $this;
    }

    public function replyTo(string $address, ?string $name = null): self
    {
        $this->replyTo = [
            'address' => $this->sanitizeAddress($address),
            'name' => $this->sanitizeName($name),
        ];

        return $this;
    }

    public function to(string $address, ?string $name = null): self
    {
        $this->to[] = [
            'address' => $this->sanitizeAddress($address),
            'name' => $this->sanitizeName($name),
        ];

        return $this;
    }

    public function cc(string $address, ?string $name = null): self
    {
        $this->cc[] = [
            'address' => $this->sanitizeAddress($address),
            'name' => $this->sanitizeName($name),
        ];

        return $this;
    }

    public function bcc(string $address, ?string $name = null): self
    {
        $this->bcc[] = [
            'address' => $this->sanitizeAddress($address),
            'name' => $this->sanitizeName($name),
        ];

        return $this;
    }

    public function subject(string $subject): self
    {
        $subject = $this->sanitizeHeaderValue($subject);
        $this->subject = $subject;

        return $this;
    }

    public function text(string $body): self
    {
        return $this->body($body, 'text/plain; charset=UTF-8');
    }

    public function html(string $body): self
    {
        return $this->body($body, 'text/html; charset=UTF-8');
    }

    public function body(string $body, string $contentType): self
    {
        $this->body = $this->normalizeLineEndings($body);
        $this->contentType = $contentType;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$this->sanitizeHeaderName($name)] = $this->sanitizeHeaderValue($value);

        return $this;
    }

    /**
     * @return array{address: string, name: string|null}|null
     */
    public function getFrom(): ?array
    {
        return $this->from;
    }

    /**
     * @return array{address: string, name: string|null}|null
     */
    public function getReplyTo(): ?array
    {
        return $this->replyTo;
    }

    /**
     * @return array<int, array{address: string, name: string|null}>
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @return array<int, array{address: string, name: string|null}>
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * @return array<int, array{address: string, name: string|null}>
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return array<string, string>
     */
    public function getCustomHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<int, array{address: string, name: string|null}>
     */
    public function getEnvelopeRecipients(): array
    {
        $recipients = [];

        foreach ([$this->to, $this->cc, $this->bcc] as $collection) {
            foreach ($collection as $entry) {
                $recipients[$entry['address']] = $entry;
            }
        }

        return array_values($recipients);
    }

    public function toMimeString(): string
    {
        $lines = $this->buildHeaderLines();
        $body = $this->body === '' ? '' : $this->body;

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }

    /**
     * @return array<int, string>
     */
    private function buildHeaderLines(): array
    {
        $lines = [];
        $lines[] = 'Date: ' . $this->formatDate();

        if ($this->subject !== '') {
            $lines[] = 'Subject: ' . $this->encodeHeader($this->subject);
        }

        if ($this->from !== null) {
            $lines[] = 'From: ' . $this->formatAddress($this->from);
        }

        if (! empty($this->to)) {
            $lines[] = 'To: ' . $this->formatAddressList($this->to);
        }

        if (! empty($this->cc)) {
            $lines[] = 'Cc: ' . $this->formatAddressList($this->cc);
        }

        if ($this->replyTo !== null) {
            $lines[] = 'Reply-To: ' . $this->formatAddress($this->replyTo);
        }

        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: ' . $this->contentType;
        $lines[] = 'Content-Transfer-Encoding: 8bit';

        foreach ($this->headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    private function sanitizeAddress(string $address): string
    {
        $address = trim($address);
        $address = preg_replace('/[\r\n]+/', '', $address);

        return $address;
    }

    private function sanitizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim($name);
        $name = preg_replace('/[\r\n]+/', ' ', $name);

        return $name === '' ? null : $name;
    }

    private function sanitizeHeaderName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);

        return $name;
    }

    private function sanitizeHeaderValue(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value));
    }

    private function normalizeLineEndings(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace("\n", "\r\n", $value);
    }

    private function formatAddressList(array $addresses): string
    {
        $formatted = [];

        foreach ($addresses as $address) {
            $formatted[] = $this->formatAddress($address);
        }

        return implode(', ', $formatted);
    }

    private function formatAddress(array $address): string
    {
        $email = $address['address'];
        $name = $address['name'];

        if ($name === null || $name === '') {
            return $email;
        }

        $encoded = $this->encodeHeader($name);

        if ($encoded !== $name) {
            return sprintf('%s <%s>', $encoded, $email);
        }

        $escaped = addcslashes($encoded, '\"\\');

        return sprintf('"%s" <%s>', $escaped, $email);
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return $value;
    }

    private function formatDate(): string
    {
        return date(DATE_RFC2822);
    }
}
