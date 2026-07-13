<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectHooks;
use Shopware\Deployment\Config\ProjectMaintenance;
use Shopware\Deployment\Config\ProjectStore;
use Shopware\Deployment\Struct\HookStep;

#[CoversClass(ProjectConfiguration::class)]
#[CoversClass(ProjectHooks::class)]
#[CoversClass(ProjectMaintenance::class)]
#[CoversClass(ProjectStore::class)]
#[CoversClass(HookStep::class)]
class ProjectConfigurationTest extends TestCase
{
    public function testConstructor(): void
    {
        $config = new ProjectConfiguration();

        static::assertEmpty($config->hooks->pre);
        static::assertEmpty($config->hooks->post);
        static::assertEmpty($config->hooks->preInstall);
        static::assertEmpty($config->hooks->postInstall);
        static::assertEmpty($config->hooks->preUpdate);
        static::assertEmpty($config->hooks->postUpdate);

        static::assertTrue($config->extensionManagement->enabled);
        static::assertEmpty($config->extensionManagement->overrides);

        static::assertEmpty($config->store->licenseDomain);
    }

    public function testHooksNormalizeStringIntoSingleUntitledStep(): void
    {
        $hooks = new ProjectHooks(pre: 'echo "hi"');

        static::assertCount(1, $hooks->pre);
        static::assertSame('echo "hi"', $hooks->pre[0]->script);
        static::assertSame('', $hooks->pre[0]->title);
    }

    public function testHooksNormalizeEmptyStringIntoNoSteps(): void
    {
        $hooks = new ProjectHooks(pre: '');

        static::assertSame([], $hooks->pre);
    }

    public function testHooksKeepStepListAsIs(): void
    {
        $steps = [new HookStep('echo "a"', 'Step A'), new HookStep('echo "b"', 'Step B')];

        $hooks = new ProjectHooks(post: $steps);

        static::assertSame($steps, $hooks->post);
    }
}
