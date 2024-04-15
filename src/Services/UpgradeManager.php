<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeManager
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly ProcessHelper $processHelper,
        private readonly PluginHelper $pluginHelper,
        private readonly AppHelper $appHelper,
        private readonly HookExecutor $hookExecutor,
        private readonly OneTimeTasks $oneTimeTasks,
    ) {}

    public function run(OutputInterface $output): void
    {
        $this->hookExecutor->execute(HookExecutor::PRE_UPDATE);

        $output->writeln('Shopware is installed, running update tools');

        if ($this->state->getPreviousVersion() !== $this->state->getCurrentVersion()) {
            $output->writeln(sprintf('Updating Shopware from %s to %s', $this->state->getPreviousVersion(), $this->state->getCurrentVersion()));
            $this->processHelper->console(['system:update:finish']);
            $this->state->setVersion($this->state->getCurrentVersion());
        }

        $this->processHelper->console(['plugin:refresh']);

        $this->pluginHelper->installPlugins();
        $this->pluginHelper->updatePlugins();
        $this->appHelper->installApps();
        $this->appHelper->updateApps();

        $this->processHelper->console(['theme:compile', '--active-only']);

        $this->oneTimeTasks->execute($output);

        $this->hookExecutor->execute(HookExecutor::POST_UPDATE);
    }
}
