<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\FastlySnippetListCommand;
use Shopware\Deployment\Integration\Fastly\FastlyAPIClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(FastlySnippetListCommand::class)]
class FastlySnippetListCommandTest extends TestCase
{
    public function testRunCommandWithoutEnv(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->never())
            ->method('setApiKey');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('getCurrentlyActiveVersion');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('listSnippets');

        $command = new FastlySnippetListCommand($fastlyAPIClient);
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertEquals(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set.', $tester->getDisplay());
    }

    #[Env('FASTLY_API_TOKEN', 'apiToken')]
    #[Env('FASTLY_SERVICE_ID', 'serviceId')]
    public function testRunCommandWithEnv(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('apiToken');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('serviceId', 1)
            ->willReturn([
                ['name' => 'name', 'type' => 'type', 'priority' => 'priority', 'updated_at' => 'updated_at'],
            ]);

        $command = new FastlySnippetListCommand($fastlyAPIClient);
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertEquals(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('name', $tester->getDisplay());
        static::assertStringContainsString('type', $tester->getDisplay());
        static::assertStringContainsString('priority', $tester->getDisplay());
        static::assertStringContainsString('updated_at', $tester->getDisplay());
    }
}
