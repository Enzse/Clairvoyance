<?php

namespace Tests\Unit;

use Core\Mail\Message;
use Tests\TestCase;

class MailMessageTest extends TestCase
{
    public function testHeadersCannotBeInjected(): void
    {
        $message = new Message("ben@example.com\r\nBcc: everyone@example.com", "Hi\nBcc: x@example.com", 'text', '<p>html</p>');
        $mime = $message->toMime('noreply@example.com', 'Clairvoyance', 'example.com');
        $this->assertSame(0, preg_match('/^Bcc:/m', $mime));
    }

    public function testNonAsciiSubjectsAreEncodedAndBodiesSurvive(): void
    {
        $message = new Message('ben@example.com', 'Join Café Ñora', "Line one\n.leading dot", '<p>Café</p>');
        $mime = $message->toMime('noreply@example.com', 'Clairvoyance', 'example.com');
        $this->assertTrue(strpos($mime, 'Subject: =?UTF-8?B?' . base64_encode('Join Café Ñora') . '?=') !== false);

        preg_match_all("/base64\r\n\r\n(.+?)\r\n--/s", $mime, $parts);
        $this->assertSame("Line one\n.leading dot", base64_decode(str_replace("\r\n", '', $parts[1][0])));
        $this->assertSame('<p>Café</p>', base64_decode(str_replace("\r\n", '', $parts[1][1])));
    }

    public function testEveryLineEndsInCrlfAndNoLineStartsWithASingleDot(): void
    {
        $mime = (new Message('a@example.com', 'S', str_repeat('x', 500), 'h'))->toMime('n@example.com', 'C', 'example.com');
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $mime));
        $this->assertSame(0, preg_match('/^\.[^.]/m', $mime));
    }
}
