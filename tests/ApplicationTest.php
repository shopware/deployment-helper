<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Application;
use Shopware\Deployment\Command\RunCommand;

#[CoversClass(Application::class)]
class ApplicationTest extends TestCase
{
    #[BackupGlobals(true)]
    public function testCanBoot(): void
    {
        $_SERVER['PROJECT_ROOT'] = \dirname(__DIR__);
        $app = new Application();
        static::assertTrue($app->getContainer()->has(RunCommand::class));
    }
}
