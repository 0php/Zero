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

    /**
     * @var array<int, array{filename: string, content: string, contentType: string}>
     */
    private array $attachments = [];

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

    /**
     * Add one-click unsubscribe headers (RFC 8058). Gmail and Yahoo expect them from bulk
     * senders; without a URL, links to APP_URL/unsubscribe for the first recipient.
     */
    public function listUnsubscribe(?string $url = null): self
    {
        if ($url === null) {
            $email = $this->to[0]['address'] ?? '';
            $url = rtrim((string) env('APP_URL', ''), '/') . '/unsubscribe' . ($email !== '' ? '?email=' . urlencode($email) : '');
        }

        $this->headers['List-Unsubscribe'] = '<' . $this->sanitizeHeaderValue($url) . '>';
        $this->headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$this->sanitizeHeaderName($name)] = $this->sanitizeHeaderValue($value);

        return $this;
    }

    public function attach(string $filename, string $content, string $contentType = 'application/octet-stream'): self
    {
        $filename = $this->sanitizeFilename($filename);
        $contentType = trim($contentType) !== '' ? $contentType : 'application/octet-stream';

        $this->attachments[] = [
            'filename' => $filename,
            'content' => $content,
            'contentType' => $contentType,
        ];

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
     * @return array<int, array{filename: string, content: string, contentType: string}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
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
        if (empty($this->attachments)) {
            [$contentHeaders, $body] = $this->buildBodyEntity();
            $lines = array_merge($this->buildHeaderLines(null, false), $contentHeaders);

            return implode("\r\n", $lines) . "\r\n\r\n" . $body;
        }

        $boundary = $this->generateBoundary();
        $lines = $this->buildHeaderLines($boundary);
        $body = $this->buildMultipartBody($boundary);

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }

    /**
     * @return array<int, string>
     */
    private function buildHeaderLines(?string $boundary = null, bool $withContentType = true): array
    {
        $lines = [];
        $lines[] = 'Date: ' . $this->formatDate();
        $lines[] = 'Message-ID: ' . $this->generateMessageId();

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
        if ($boundary !== null) {
            $lines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        } elseif ($withContentType) {
            $lines[] = 'Content-Type: ' . $this->contentType;
            $lines[] = 'Content-Transfer-Encoding: 8bit';
        }

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

    private function sanitizeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = preg_replace('/[\r\n]+/', '', $filename);

        return $filename === '' ? 'attachment' : $filename;
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

    private function generateMessageId(): string
    {
        $domain = env('MAIL_HELO_DOMAIN', 'localhost') ?: 'localhost';

        return sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $domain);
    }

    private function formatDate(): string
    {
        return date(DATE_RFC2822);
    }

    private function generateBoundary(): string
    {
        return '=_Part_' . bin2hex(random_bytes(12));
    }

    private function buildMultipartBody(string $boundary): string
    {
        $parts = [];
        $parts[] = $this->buildBodyPart($boundary);

        foreach ($this->attachments as $attachment) {
            $parts[] = $this->buildAttachmentPart($boundary, $attachment);
        }

        $parts[] = '--' . $boundary . '--';

        return implode("\r\n", $parts);
    }

    private function buildBodyPart(string $boundary): string
    {
        [$contentHeaders, $body] = $this->buildBodyEntity();

        return implode("\r\n", array_merge(['--' . $boundary], $contentHeaders, ['', $body]));
    }

    /**
     * Content headers and body of the message text. HTML gets a plain-text alternative,
     * since HTML-only mail scores as spam; both are quoted-printable to keep lines short.
     *
     * @return array{0: array<int, string>, 1: string}
     */
    private function buildBodyEntity(): array
    {
        if (! str_starts_with($this->contentType, 'text/html')) {
            return [
                ['Content-Type: ' . $this->contentType, 'Content-Transfer-Encoding: quoted-printable'],
                $this->encodeQuotedPrintable($this->body),
            ];
        }

        $boundary = $this->generateBoundary();
        $body = implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            $this->encodeQuotedPrintable($this->htmlToText($this->body)),
            '--' . $boundary,
            'Content-Type: ' . $this->contentType,
            'Content-Transfer-Encoding: quoted-printable',
            '',
            $this->encodeQuotedPrintable($this->body),
            '--' . $boundary . '--',
        ]);

        return [['Content-Type: multipart/alternative; boundary="' . $boundary . '"'], $body];
    }

    private function encodeQuotedPrintable(string $body): string
    {
        // Encode with CRLF line breaks in place so they stay real line breaks, not =0A.
        return quoted_printable_encode($this->normalizeLineEndings($body));
    }

    private function htmlToText(string $html): string
    {
        $html = str_replace("\r", '', $html);
        $html = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        // Keep link targets readable in the text version.
        $html = preg_replace_callback('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function (array $m): string {
            $label = trim(strip_tags($m[2]));
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $label === '' || $label === $url ? $url : $label . ' (' . $url . ')';
        }, $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</t[dh]>#i', '  ', $html) ?? $html;
        $html = preg_replace('#</(p|div|h[1-6]|li|table)>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#</tr>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text) . "\n";
    }

    /**
     * @param array{filename: string, content: string, contentType: string} $attachment
     */
    private function buildAttachmentPart(string $boundary, array $attachment): string
    {
        $filename = $this->sanitizeHeaderValue($attachment['filename']);
        $contentType = $attachment['contentType'];
        $encoded = chunk_split(base64_encode($attachment['content']), 76, "\r\n");

        return implode("\r\n", [
            '--' . $boundary,
            'Content-Type: ' . $contentType . '; name="' . addcslashes($filename, '"\\') . '"',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: attachment; filename="' . addcslashes($filename, '"\\') . '"',
            '',
            rtrim($encoded, "\r\n"),
        ]);
    }
}
