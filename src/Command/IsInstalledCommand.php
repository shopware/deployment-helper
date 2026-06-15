<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\ShopwareState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('is-installed', description: 'Check if Shopware is installed', hidden: true)]
class IsInstalledCommand extends Command
{
    public function __construct(
        private readonly ShopwareState $state,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->state->isInstalled()) {
            $output->writeln('Shopware is installed');

            return Command::SUCCESS;
        }

        $output->writeln('Shopware is not installed');

        return Command::FAILURE;
    }
}
