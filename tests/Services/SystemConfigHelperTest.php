<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\SystemConfigHelper;

#[CoversClass(SystemConfigHelper::class)]
class SystemConfigHelperTest extends TestCase
{
    public function testGet(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())
            ->method('consoleOutput')
            ->with(['system:config:get', 'key', '--format=json'])
            ->willReturn("{\"key\":\"value\"}\n");

        $systemConfigHelper = new SystemConfigHelper($processHelper);

        static::assertSame('value', $systemConfigHelper->get('key'));
    }

    public function testGetInt(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->method('consoleOutput')->willReturn("{\"key\":5}\n");

        $systemConfigHelper = new SystemConfigHelper($processHelper);

        static::assertSame('5', $systemConfigHelper->get('key'));
    }

    public function testGetArray(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->method('consoleOutput')->willReturn("{\"key\":{\"key\":\"value\"}}\n");

        $systemConfigHelper = new SystemConfigHelper($processHelper);

        static::expectException(\UnexpectedValueException::class);
        $systemConfigHelper->get('key');
    }

    public function testGetNull(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->method('consoleOutput')->willReturn("{\"key\":null}\n");

        $systemConfigHelper = new SystemConfigHelper($processHelper);

        static::assertNull($systemConfigHelper->get('key'));
    }

    public function testSet(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())
            ->method('console')
            ->with(['system:config:set', 'key', 'value']);

        $systemConfigHelper = new SystemConfigHelper($processHelper);
        $systemConfigHelper->set('key', 'value');
    }

    public function testDelete(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())
            ->method('console')
            ->with(['system:config:set', 'key', 'null', '--json']);

        $systemConfigHelper = new SystemConfigHelper($processHelper);
        $systemConfigHelper->delete('key');
    }
}
