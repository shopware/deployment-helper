<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectHooks;
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

    public static function hookProvider(): \Generator
    {
        yield [HookExecutor::HOOK_PRE, new ProjectHooks(pre: 'Hello World')];
        yield [HookExecutor::HOOK_POST, new ProjectHooks(post: 'Hello World')];
        yield [HookExecutor::HOOK_PRE_INSTALL, new ProjectHooks(preInstall: 'Hello World')];
        yield [HookExecutor::HOOK_POST_INSTALL, new ProjectHooks(postInstall: 'Hello World')];
        yield [HookExecutor::HOOK_PRE_UPDATE, new ProjectHooks(preUpdate: 'Hello World')];
        yield [HookExecutor::HOOK_POST_UPDATE, new ProjectHooks(postUpdate: 'Hello World')];
    }

    /**
     * @param HookExecutor::HOOK_* $hookName
     */
    #[DataProvider('hookProvider')]
    public function testRuns(string $hookName, ProjectHooks $config): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $configuration->hooks = $config;
        $executor = new HookExecutor($processHelper, $configuration);

        $processHelper->expects($this->once())->method('runAndTail')->with('Hello World');

        $executor->execute($hookName);
    }

    public function testThrowsOnUnknownHook(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $executor = new HookExecutor($processHelper, $configuration);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown hook name');

        // @phpstan-ignore-next-line
        $executor->execute('unknown');
    }
}
