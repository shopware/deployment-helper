<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration\Fastly;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Integration\Fastly\FastlyContext;

#[CoversClass(FastlyContext::class)]
class FastlyContextTest extends TestCase
{
    public function testHasSnippet(): void
    {
        $context = new FastlyContext([
            ['name' => 'snippet1', 'type' => 'recv', 'content' => 'content1', 'priority' => 10, 'updated_at' => '2023-01-01'],
            ['name' => 'snippet2', 'type' => 'recv', 'content' => 'content2', 'priority' => 20, 'updated_at' => '2023-01-02'],
        ], 'service123', 1);

        static::assertTrue($context->hasSnippet('snippet1'));
        static::assertFalse($context->hasSnippet('snippet3'));
    }

    public function testHasSnippetChanged(): void
    {
        $context = new FastlyContext([
            ['name' => 'snippet1', 'type' => 'recv', 'content' => 'content1', 'priority' => 10, 'updated_at' => '2023-01-01'],
            ['name' => 'snippet2', 'type' => 'recv', 'content' => 'content2', 'priority' => 20, 'updated_at' => '2023-01-02'],
        ], 'service123', 1);

        static::assertTrue($context->hasSnippetChanged('snippet1', 'content2'));
        static::assertFalse($context->hasSnippetChanged('snippet1', 'content1'));
    }
}
