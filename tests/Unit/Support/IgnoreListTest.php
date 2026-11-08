<?php

namespace Goodport\JsErrorLogger\Tests\Unit\Support;

use Goodport\JsErrorLogger\Support\IgnoreList;
use PHPUnit\Framework\TestCase;

final class IgnoreListTest extends TestCase
{
    /**
     * Message fragments are matched case-insensitively.
     */
    public function test_message_fragment_is_matched_case_insensitively(): void
    {
        $ignoreList = new IgnoreList([], ['request aborted']);

        $this->assertTrue($ignoreList->shouldIgnore('Mozilla/5.0', 'Request Aborted'));
    }

    /**
     * User agent fragments are matched case-insensitively.
     */
    public function test_user_agent_fragment_is_matched_case_insensitively(): void
    {
        $ignoreList = new IgnoreList(['googlebot'], []);

        $this->assertTrue($ignoreList->shouldIgnore('Mozilla GoogleBot/2.1', 'Any message'));
    }

    /**
     * A null user agent is allowed and does not match an empty list.
     */
    public function test_null_user_agent_is_allowed(): void
    {
        $ignoreList = new IgnoreList(['bot'], ['aborted']);

        $this->assertFalse($ignoreList->shouldIgnore(null, 'Clean message'));
    }

    /**
     * Empty fragments are ignored instead of matching every value.
     */
    public function test_empty_fragments_are_ignored(): void
    {
        $ignoreList = new IgnoreList([''], ['']);

        $this->assertFalse($ignoreList->shouldIgnore('Mozilla/5.0', 'Clean message'));
    }

    /**
     * An event that matches neither list is reported.
     */
    public function test_unmatched_event_is_not_ignored(): void
    {
        $ignoreList = new IgnoreList(['bot'], ['request aborted']);

        $this->assertFalse($ignoreList->shouldIgnore('Mozilla/5.0', 'TypeError: value is undefined'));
    }
}
