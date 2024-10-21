<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\FastlySnippetDeployCommand;
use Shopware\Deployment\Integration\Fastly\FastlyServiceUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(FastlySnippetDeployCommand::class)]
class FastlySnippetDeployCommandTest extends TestCase
{
    public function testRunCommandWithoutEnv(): void
    {
        $updater = $this->createMock(FastlyServiceUpdater::class);
        $updater
            ->expects($this->never())
            ->method('__invoke');

        $command = new FastlySnippetDeployCommand($updater);
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertEquals(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set.', $tester->getDisplay());
    }

    #[Env('FASTLY_API_TOKEN', 'apiToken')]
    #[Env('FASTLY_SERVICE_ID', 'serviceId')]
    public function testRunCommandWithEnv(): void
    {
        $updater = $this->createMock(FastlyServiceUpdater::class);
        $updater
            ->expects($this->once())
            ->method('__invoke');

        $command = new FastlySnippetDeployCommand($updater);
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertEquals(Command::SUCCESS, $tester->getStatusCode());
    }
}
