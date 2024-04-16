<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\OneTimeTaskMarkCommand;
use Shopware\Deployment\Services\OneTimeTasks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OneTimeTaskMarkCommand::class)]
class OneTimeTaskMarkCommandTest extends TestCase
{
    public function testMark(): void
    {
        $taskService = $this->createMock(OneTimeTasks::class);
        $taskService
            ->expects(static::once())
            ->method('markAsRun')
            ->with('test');

        $cmd = new OneTimeTaskMarkCommand($taskService);
        $tester = new CommandTester($cmd);
        $tester->execute(['id' => 'test']);

        $tester->assertCommandIsSuccessful();
    }

    public function testMarkAgain(): void
    {
        $taskService = $this->createMock(OneTimeTasks::class);
        $taskService
            ->expects(static::once())
            ->method('markAsRun')
            ->willThrowException(new \Exception('Task already marked as run'));

        $cmd = new OneTimeTaskMarkCommand($taskService);

        $tester = new CommandTester($cmd);
        $tester->execute(['id' => 'test']);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
    }
}
