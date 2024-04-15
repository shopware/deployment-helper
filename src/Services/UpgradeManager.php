<?php

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class UpgradeManager
{
    public function __construct(
        private ShopwareState $state,
        private ProcessHelper $processHelper,
        private PluginHelper $pluginHelper,
    ) {}

    public function run(OutputInterface $output): void
    {
        $output->writeln('Shopware is installed, running upgrade tools');

        if ($this->state->getPreviousVersion() !== $this->state->getCurrentVersion()) {
            $output->writeln(sprintf('Updating Shopware from %s to %s', $this->state->getPreviousVersion(), $this->state->getCurrentVersion()));
            $this->processHelper->console(['system:update:finish']);
            $this->state->setVersion($this->state->getCurrentVersion());
        }

        $this->processHelper->console(['plugin:refresh']);

        $this->pluginHelper->installPlugins();
        $this->pluginHelper->updatePlugins();

        $this->processHelper->console(['theme:compile', '--active-only']);
    }
}
