<?php declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\UpgradeManager;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('run', description: 'Install or Update Shopware')]
class RunCommand extends Command
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly InstallationManager $installationManager,
        private readonly UpgradeManager $upgradeManager,
        private readonly HookExecutor $hookExecutor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-theme-compile', null, InputOption::VALUE_OPTIONAL, 'Skip theme compile (should be used when the Theme has been compiled before in the CI/CD)', false);
        $this->addOption('skip-asset-install', null, InputOption::VALUE_OPTIONAL, 'Skip asset install (should be used when the Assets has been copied before in the CI/CD)', false);
        $this->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Set script execution timeout (is seconds). Set to null to disable timeout', 60);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeout = $input->getOption('timeout');

        $config = new RunConfiguration(
            skipThemeCompile: (bool) $input->getOption('skip-theme-compile'),
            skipAssetInstall: (bool) $input->getOption('skip-asset-install'),
            timeout: $timeout ? (float) $timeout : null,
        );

        $installed = $this->state->isInstalled();

        $this->hookExecutor->execute(HookExecutor::PRE);

        if ($installed) {
            $this->upgradeManager->run($config, $output);
        } else {
            $this->installationManager->run($config, $output);
        }

        $this->hookExecutor->execute(HookExecutor::POST);

        return Command::SUCCESS;
    }
}
