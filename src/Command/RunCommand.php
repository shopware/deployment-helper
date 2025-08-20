<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Services\UpgradeManager;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand('run', description: 'Install or Update Shopware')]
class RunCommand extends Command
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly InstallationManager $installationManager,
        private readonly UpgradeManager $upgradeManager,
        private readonly HookExecutor $hookExecutor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TrackingService $trackingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-theme-compile', null, InputOption::VALUE_NONE, 'Skip theme compile (Should be used when the theme has been compiled before in the CI/CD)');
        $this->addOption('skip-asset-install', null, InputOption::VALUE_NONE, 'Deprecated - use --skip-assets-install instead');
        $this->addOption('skip-assets-install', null, InputOption::VALUE_NONE, 'Skip asset install (Should be used when the assets have been copied before in the CI/CD)');
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Set script execution timeout (in seconds). Set to null to disable timeout', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->trackingService->track('php_version', [
            'php_version' => \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION,
        ]);

        try {
            $this->trackingService->track('mysql_version', [
                'mysql_version' => $this->state->getMySqlVersion(),
            ]);
        } catch (\Throwable) {
        }

        $timeout = $input->getOption('timeout');

        $config = new RunConfiguration(
            skipThemeCompile: (bool) $input->getOption('skip-theme-compile'),
            skipAssetsInstall: ((bool) $input->getOption('skip-asset-install') || (bool) $input->getOption('skip-assets-install')),
            timeout: (float) (is_numeric($timeout) ? $timeout : EnvironmentHelper::getVariable('SHOPWARE_DEPLOYMENT_TIMEOUT', '300')),
            forceReinstallation: EnvironmentHelper::getVariable('SHOPWARE_DEPLOYMENT_FORCE_REINSTALL', '0') === '1',
        );

        $installed = $this->state->isInstalled();

        if ($config->forceReinstallation && $this->state->getPreviousVersion() === 'unknown') {
            $installed = false;
        }

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE);

        if ($installed) {
            $this->upgradeManager->run($config, $output);
        } else {
            $this->installationManager->run($config, $output);
        }

        $this->eventDispatcher->dispatch(new PostDeploy($config, $output));

        $this->hookExecutor->execute(HookExecutor::HOOK_POST);

        return Command::SUCCESS;
    }
}
