<?php declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\OneTimeTasks;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'one-time-task:list',
    description: 'List all executed one-time tasks',
)]
class OneTimeTaskListCommand extends Command
{
    public function __construct(private readonly OneTimeTasks $oneTimeTasks)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list = $this->oneTimeTasks->getExecutedTasks();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Executed at']);

        foreach ($list as $id => $data) {
            $table->addRow([$id, $data['created_at']]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
