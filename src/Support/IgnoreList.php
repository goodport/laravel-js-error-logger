<?php

namespace Goodport\JsErrorLogger\Support;

final class IgnoreList
{
    /**
     * @var list<string>
     */
    private array $userAgentFragments;

    /**
     * @var list<string>
     */
    private array $messageFragments;

    /**
     * @param  list<string>  $userAgentFragments
     * @param  list<string>  $messageFragments
     */
    public function __construct(array $userAgentFragments, array $messageFragments)
    {
        $this->userAgentFragments = $this->normalize($userAgentFragments);
        $this->messageFragments = $this->normalize($messageFragments);
    }

    /**
     * Determines whether the event matches an ignored user agent or message.
     */
    public function shouldIgnore(?string $userAgent, string $message): bool
    {
        return $this->matches($userAgent, $this->userAgentFragments)
            || $this->matches($message, $this->messageFragments);
    }

    /**
     * @param  list<string>  $fragments
     */
    private function matches(?string $value, array $fragments): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $haystack = mb_strtolower($value, 'UTF-8');

        foreach ($fragments as $fragment) {
            if (str_contains($haystack, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $fragments
     * @return list<string>
     */
    private function normalize(array $fragments): array
    {
        $normalized = [];

        foreach ($fragments as $fragment) {
            $fragment = mb_strtolower(trim($fragment), 'UTF-8');

            if ($fragment === '') {
                continue;
            }

            $normalized[] = $fragment;
        }

        return $normalized;
    }
}
