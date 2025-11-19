<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\OneTimeTasks;
use Shopware\Deployment\Struct\OneTimeTask;
use Shopware\Deployment\Struct\OneTimeTaskWhen;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(OneTimeTasks::class)]
class OneTimeTasksTest extends TestCase
{
    public function testNoTasks(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('writeln');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('runAndTail');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);

        $configuration = new ProjectConfiguration();

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::LAST);
    }

    public function testNoTasksNoTable(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('writeln');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('runAndTail');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeQuery')->with('SELECT 1 FROM one_time_tasks LIMIT 1');
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);

        $configuration = new ProjectConfiguration();

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::FIRST);
    }

    public function testTask(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('Running one-time task test');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('runAndTail')->with('echo "test"');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);

        $connection->expects($this->once())->method('executeStatement')->with('INSERT INTO one_time_tasks (id, created_at) VALUES (:id, :created_at)', [
            'id' => 'test',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $configuration = new ProjectConfiguration();
        $configuration->oneTimeTasks = [
            'test' => new OneTimeTask('test', 'echo "test"', OneTimeTaskWhen::LAST),
        ];

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::LAST);
    }

    public function testTaskAlreadyExecuted(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('writeln');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('runAndTail');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn(['test' => ['created_at' => '2021-01-01 00:00:00']]);

        $configuration = new ProjectConfiguration();
        $configuration->oneTimeTasks = [
            'test' => new OneTimeTask('test', 'echo "test"', OneTimeTaskWhen::LAST),
        ];

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::LAST);
    }

    public function testRemove(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with('DELETE FROM one_time_tasks WHERE id = ?', ['test']);

        $processHelper = $this->createMock(ProcessHelper::class);

        $tasks = new OneTimeTasks($processHelper, $connection, new ProjectConfiguration());
        $tasks->remove('test');
    }

    public function testTaskWithWhenFirst(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('Running one-time task first-task');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('runAndTail')->with('echo "first"');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);
        $connection->expects($this->once())->method('executeStatement');

        $configuration = new ProjectConfiguration();
        $configuration->oneTimeTasks = [
            'first-task' => new OneTimeTask('first-task', 'echo "first"', OneTimeTaskWhen::FIRST),
            'last-task' => new OneTimeTask('last-task', 'echo "last"', OneTimeTaskWhen::LAST),
        ];

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::FIRST);
    }

    public function testTaskWithWhenLast(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('Running one-time task last-task');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('runAndTail')->with('echo "last"');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);
        $connection->expects($this->once())->method('executeStatement');

        $configuration = new ProjectConfiguration();
        $configuration->oneTimeTasks = [
            'first-task' => new OneTimeTask('first-task', 'echo "first"', OneTimeTaskWhen::FIRST),
            'last-task' => new OneTimeTask('last-task', 'echo "last"', OneTimeTaskWhen::LAST),
        ];

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::LAST);
    }

    public function testTaskWithoutWhenFilterExecutesAll(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->exactly(1))->method('writeln');

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->exactly(1))->method('runAndTail');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociativeIndexed')->willReturn([]);
        $connection->expects($this->exactly(1))->method('executeStatement');

        $configuration = new ProjectConfiguration();
        $configuration->oneTimeTasks = [
            'first-task' => new OneTimeTask('first-task', 'echo "first"', OneTimeTaskWhen::FIRST),
            'last-task' => new OneTimeTask('last-task', 'echo "last"', OneTimeTaskWhen::LAST),
        ];

        $tasks = new OneTimeTasks($processHelper, $connection, $configuration);
        $tasks->execute($output, OneTimeTaskWhen::FIRST);
    }
}
