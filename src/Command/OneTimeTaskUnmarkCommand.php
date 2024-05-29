<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\OneTimeTasks;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'one-time-task:unmark',
    description: 'Unmark an one-time task as run without executing it',
)]
class OneTimeTaskUnmarkCommand extends Command
{
    public function __construct(private readonly OneTimeTasks $oneTimeTasks)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'The ID of the one-time task');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->oneTimeTasks->remove((string) $input->getArgument('id'));

        $io->success('One-time task mark has been removed, this script will be executed on next deployment.');

        return Command::SUCCESS;
    }
}
