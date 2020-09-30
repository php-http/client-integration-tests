<?php

namespace Http\Client\Tests;

use PHPUnit\Framework\TestCase;

trait PhpUnitBackwardCompatibleTrait
{
    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        // For supporting both phpunit 7 and 8 without display any deprecation.
        if (method_exists(TestCase::class, 'assertStringContainsString')) {
            parent::assertStringContainsString($needle, $haystack, $message);
        } else {
            parent::assertContains($needle, $haystack, $message);
        }
    }
}
