<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\IsInstalledCommand;
use Shopware\Deployment\Services\ShopwareState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(IsInstalledCommand::class)]
class IsInstalledCommandTest extends TestCase
{
    public function testReturnsSuccessWhenInstalled(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('isInstalled')->willReturn(true);

        $tester = new CommandTester(new IsInstalledCommand($state));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Shopware is installed', $tester->getDisplay());
    }

    public function testReturnsFailureWhenNotInstalled(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('isInstalled')->willReturn(false);

        $tester = new CommandTester(new IsInstalledCommand($state));
        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Shopware is not installed', $tester->getDisplay());
    }
}
