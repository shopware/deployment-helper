<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\HookExecutor;

#[CoversClass(HookExecutor::class)]
class HookExecutorTest extends TestCase
{
    public function testDoesNotWhenEmpty(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $executor = new HookExecutor($processHelper, $configuration);

        $processHelper->expects($this->never())->method('run');

        $executor->execute(HookExecutor::HOOK_PRE);
    }

    public function testRuns(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();
        $configuration->hooks->pre = 'echo "Hello World"';

        $executor = new HookExecutor($processHelper, $configuration);

        $processHelper->expects($this->once())->method('runAndTail');

        $executor->execute(HookExecutor::HOOK_PRE);
    }
}
