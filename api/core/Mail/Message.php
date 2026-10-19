<?php

namespace Core\Mail;

/** One email with a plain-text and an HTML body. */
class Message
{
    public string $to;
    public string $subject;
    public string $text;
    public string $html;

    public function __construct(string $to, string $subject, string $text, string $html)
    {
        $this->to = self::clean($to);
        $this->subject = self::clean($subject);
        $this->text = $text;
        $this->html = $html;
    }

    /**
     * The message as sent over SMTP DATA (CRLF line endings, dot-stuffed, without the final
     * "."). Bodies are base64 so long lines and non-ASCII text survive any relay.
     */
    public function toMime(string $from, string $fromName, string $messageIdDomain): string
    {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader(self::clean($fromName)) . ' <' . self::clean($from) . '>',
            'To: <' . $this->to . '>',
            'Subject: ' . self::encodeHeader($this->subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . self::clean($messageIdDomain) . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $part = fn(string $type, string $body) => implode("\r\n", [
            "--{$boundary}",
            "Content-Type: {$type}; charset=UTF-8",
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ]);
        $mime = implode("\r\n", $headers) . "\r\n\r\n"
            . $part('text/plain', $this->text) . "\r\n"
            . $part('text/html', $this->html) . "\r\n"
            . "--{$boundary}--";
        // A line that starts with "." would end DATA early, so SMTP doubles it
        return preg_replace('/^\./m', '..', $mime);
    }

    /** RFC 2047 encoding for headers that aren't plain ASCII. */
    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /** Header values can never carry line breaks (that would let input add headers). */
    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
