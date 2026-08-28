<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Application;
use Shopware\Deployment\ApplicationOutput;
use Shopware\Deployment\Command\DumpEnvCommand;
use Shopware\Deployment\Command\RunCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(Application::class)]
class ApplicationTest extends TestCase
{
    #[Env('PROJECT_ROOT', __DIR__ . '/..')]
    public function testCanBoot(): void
    {
        $app = new Application();
        static::assertTrue($app->getContainer()->has(RunCommand::class));
    }

    #[Env('PROJECT_ROOT', __DIR__ . '/..')]
    #[Env('DEV_MODE', '1')]
    public function testWithDevMode(): void
    {
        $app = new Application();
        static::assertTrue($app->getContainer()->has(RunCommand::class));
        static::assertFileExists(\dirname(__DIR__) . '/var/cache/container.xml');
    }

    #[Env('PROJECT_ROOT', __DIR__ . '/..')]
    public function testDumpEnvCommandIsRegisteredAndHidden(): void
    {
        $app = new Application();

        static::assertTrue($app->getContainer()->has(DumpEnvCommand::class));
        static::assertTrue($app->has('dump-env'));
        static::assertTrue($app->get('dump-env')->isHidden());
    }

    #[Env('PROJECT_ROOT', __DIR__ . '/..')]
    public function testDumpEnvOutputIsNotPrefixed(): void
    {
        $app = new Application();
        $app->setAutoExit(false);

        $inner = new BufferedOutput();
        $exitCode = $app->run(new ArrayInput(['command' => 'dump-env']), new ApplicationOutput($inner));
        $display = $inner->fetch();

        static::assertSame(0, $exitCode);
        static::assertJson($display);
        static::assertStringStartsWith('{', $display);
        static::assertStringNotContainsString('[deployment-helper]', $display);
    }
}
