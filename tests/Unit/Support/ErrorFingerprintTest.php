<?php

namespace Goodport\JsErrorLogger\Tests\Unit\Support;

use Goodport\JsErrorLogger\Support\ErrorFingerprint;
use Goodport\JsErrorLogger\Support\LogSanitizer;
use PHPUnit\Framework\TestCase;

final class ErrorFingerprintTest extends TestCase
{
    /**
     * Numbers are normalized so different records share one fingerprint.
     */
    public function test_numbers_are_normalized(): void
    {
        $fingerprint = $this->fingerprint();

        $this->assertSame('Order <n> failed||', $fingerprint->make('Order 42 failed', null, null));
        $this->assertSame(
            $fingerprint->make('Order 42 failed', null, null),
            $fingerprint->make('Order 100 failed', null, null),
        );
    }

    /**
     * Long hexadecimal values are normalized as hashes.
     */
    public function test_hexadecimal_values_are_normalized(): void
    {
        $fingerprint = $this->fingerprint();

        $first = $fingerprint->make('Request deadbeef1234 failed', null, null);
        $second = $fingerprint->make('Request cafebabe5678 failed', null, null);

        $this->assertSame('Request <hash> failed||', $first);
        $this->assertSame($first, $second);
    }

    /**
     * The query string and fragment are removed from the source file.
     */
    public function test_file_query_string_is_removed(): void
    {
        $fingerprint = $this->fingerprint();

        $this->assertSame(
            'Problem|https://example.com/app.js|10',
            $fingerprint->make('Problem', 'https://example.com/app.js?v=1#top', 10),
        );
    }

    /**
     * The same error in a different file version has the same fingerprint.
     */
    public function test_file_version_does_not_change_the_fingerprint(): void
    {
        $fingerprint = $this->fingerprint();

        $this->assertSame(
            $fingerprint->make('Problem', 'https://example.com/app.js?v=1', 10),
            $fingerprint->make('Problem', 'https://example.com/app.js?v=2', 10),
        );
    }

    /**
     * The line number is part of the fingerprint.
     */
    public function test_line_number_is_part_of_the_fingerprint(): void
    {
        $fingerprint = $this->fingerprint();

        $this->assertNotSame(
            $fingerprint->make('Problem', 'app.js', 10),
            $fingerprint->make('Problem', 'app.js', 11),
        );
    }

    private function fingerprint(): ErrorFingerprint
    {
        return new ErrorFingerprint(new LogSanitizer);
    }
}
