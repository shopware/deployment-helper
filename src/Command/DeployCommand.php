<?php declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\UpgradeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('deploy', description: 'Install or Update Shopware')]
class DeployCommand extends Command
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly InstallationManager $installationManager,
        private readonly UpgradeManager $upgradeManager,
        private readonly HookExecutor $hookExecutor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $installed = $this->state->isInstalled();

        $this->hookExecutor->execute(HookExecutor::PRE);

        if ($installed) {
            $this->upgradeManager->run($output);
        } else {
            $this->installationManager->run($output);
        }

        $this->hookExecutor->execute(HookExecutor::POST);

        return Command::SUCCESS;
    }
}
