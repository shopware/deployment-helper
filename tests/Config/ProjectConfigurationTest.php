<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Shopware\Deployment\Config\ProjectHooks;

#[CoversClass(ProjectConfiguration::class)]
#[CoversClass(ProjectExtensionManagement::class)]
#[CoversClass(ProjectHooks::class)]
class ProjectConfigurationTest extends TestCase
{
    public function testExtensionManaged(): void
    {
        $config = new ProjectConfiguration();

        static::assertTrue($config->isExtensionManaged('some-extension'));

        $config->extensionManagement->excluded = ['some-extension'];
        static::assertFalse($config->isExtensionManaged('some-extension'));
        static::assertTrue($config->isExtensionManaged('some-extension2'));

        $config->extensionManagement->enabled = false;

        static::assertFalse($config->isExtensionManaged('other-extension'));
    }
}
