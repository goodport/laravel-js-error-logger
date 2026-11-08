<?php

namespace Goodport\JsErrorLogger\Tests\Unit\Support;

use Goodport\JsErrorLogger\Support\LogSanitizer;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    /**
     * C0 control characters, tab, carriage return and newline become spaces.
     */
    public function test_c0_control_characters_are_removed(): void
    {
        $sanitizer = new LogSanitizer;

        $this->assertSame('a b c d e', $sanitizer->clean("a\nb\tc\rd\x00e"));
        $this->assertSame(' backspace', $sanitizer->clean("\x08backspace"));
    }

    /**
     * C1 control characters become spaces.
     */
    public function test_c1_control_characters_are_removed(): void
    {
        $sanitizer = new LogSanitizer;

        $this->assertSame('a b', $sanitizer->clean("a\u{0085}b"));
    }

    /**
     * Unicode line and paragraph separators become spaces.
     */
    public function test_unicode_separators_are_removed(): void
    {
        $sanitizer = new LogSanitizer;

        $this->assertSame('a b c', $sanitizer->clean("a\u{2028}b\u{2029}c"));
    }

    /**
     * Truncation does not split a multibyte character.
     */
    public function test_truncation_is_multibyte_safe(): void
    {
        $sanitizer = new LogSanitizer;

        $result = $sanitizer->sanitize(str_repeat('Ж', 10), 3);

        $this->assertSame('ЖЖЖ', $result);
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    /**
     * Invalid UTF-8 input is scrubbed instead of breaking the sanitizer.
     */
    public function test_invalid_utf8_is_scrubbed(): void
    {
        $sanitizer = new LogSanitizer;

        $result = $sanitizer->clean("\xFFabc");

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    /**
     * Query strings and fragments are removed from URLs and files.
     */
    public function test_query_string_and_fragment_are_stripped(): void
    {
        $sanitizer = new LogSanitizer;

        $this->assertSame(
            'https://example.com/page',
            $sanitizer->stripQueryAndFragment('https://example.com/page?token=secret#section'),
        );
        $this->assertSame('/assets/app.js', $sanitizer->stripQueryAndFragment('/assets/app.js?v=1'));
        $this->assertSame('/assets/app.js', $sanitizer->stripQueryAndFragment('/assets/app.js'));
    }
}
