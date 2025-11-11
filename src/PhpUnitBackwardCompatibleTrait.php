<?php

namespace Http\Client\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @deprecated this trait was to help using phpunit 7 and 8 which are obsolete. this trait will be removed in the next major version.
 */
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
