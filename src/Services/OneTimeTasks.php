<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class OneTimeTasks
{
    public function __construct(
        private readonly ProcessHelper $processHelper,
        private readonly Connection $connection,
        private readonly ProjectConfiguration $configuration,
    ) {}

    public function execute(OutputInterface $output): void
    {
        $executed = $this->getExecutedTasks();

        foreach ($this->configuration->oneTimeTasks as $id => $script) {
            if (isset($executed[$id])) {
                continue;
            }

            $output->writeln('Running one-time task ' . $id);

            $this->processHelper->runAndTail($script);

            $this->markAsRun($id);
        }
    }

    /**
     * @return array<string, string>[]
     */
    private function getExecutedTasks(): array
    {
        try {
            return $this->connection->fetchAllAssociativeIndexed('SELECT id, created_at FROM one_time_tasks');
        } catch (\Throwable) {
            $this->connection->executeStatement('CREATE TABLE one_time_tasks (id VARCHAR(255) PRIMARY KEY, created_at DATETIME NOT NULL)');

            return [];
        }
    }

    private function markAsRun(string $id): void
    {
        $this->connection->executeStatement('INSERT INTO one_time_tasks (id, created_at) VALUES (:id, :created_at)', [
            'id' => $id,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}
