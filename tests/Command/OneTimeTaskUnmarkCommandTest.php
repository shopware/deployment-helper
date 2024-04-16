<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\OneTimeTaskUnmarkCommand;
use Shopware\Deployment\Services\OneTimeTasks;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OneTimeTaskUnmarkCommand::class)]
class OneTimeTaskUnmarkCommandTest extends TestCase
{
    public function testUnmark(): void
    {
        $taskService = $this->createMock(OneTimeTasks::class);
        $taskService
            ->expects(static::once())
            ->method('remove')
            ->with('test');

        $cmd = new OneTimeTaskUnmarkCommand($taskService);
        $tester = new CommandTester($cmd);
        $tester->execute(['id' => 'test']);

        $tester->assertCommandIsSuccessful();
    }
}
