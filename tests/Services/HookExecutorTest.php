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
use Shopware\Deployment\Struct\HookStep;

#[CoversClass(HookExecutor::class)]
#[CoversClass(ProjectHooks::class)]
#[CoversClass(HookStep::class)]
class HookExecutorTest extends TestCase
{
    public function testDoesNotWhenEmpty(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $executor = new HookExecutor($processHelper, $configuration);

        $processHelper->expects($this->never())->method('runAndTail');

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

        $processHelper->expects($this->once())->method('runAndTail')->with('Hello World', '');

        $executor->execute($hookName);
    }

    public function testRunsMultipleStepsInOrder(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $configuration->hooks = new ProjectHooks(post: [
            new HookStep('echo first', 'First step'),
            new HookStep('echo second', 'Second step'),
        ]);

        $executor = new HookExecutor($processHelper, $configuration);

        $calls = [];
        $processHelper
            ->expects($this->exactly(2))
            ->method('runAndTail')
            ->willReturnCallback(static function (string $script, string $title) use (&$calls): void {
                $calls[] = [$script, $title];
            });

        $executor->execute(HookExecutor::HOOK_POST);

        static::assertSame([
            ['echo first', 'First step'],
            ['echo second', 'Second step'],
        ], $calls);
    }

    public function testSkipsStepsWithEmptyScript(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $configuration = new ProjectConfiguration();

        $configuration->hooks = new ProjectHooks(post: [
            new HookStep('', 'Empty step'),
            new HookStep('echo run', 'Real step'),
        ]);

        $executor = new HookExecutor($processHelper, $configuration);

        $processHelper->expects($this->once())->method('runAndTail')->with('echo run', 'Real step');

        $executor->execute(HookExecutor::HOOK_POST);
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
