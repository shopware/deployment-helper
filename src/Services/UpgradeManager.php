<?php

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class UpgradeManager
{
    public function __construct(
        private ShopwareState $state,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function run(OutputInterface $output): void
    {
        $output->writeln('Shopware is installed, running upgrade tools');

        if ($this->state->getPreviousVersion() !== $this->state->getCurrentVersion()) {
            $output->writeln(sprintf('Updating Shopware from %s to %s', $this->state->getPreviousVersion(), $this->state->getCurrentVersion()));
            ProcessHelper::console(['system:update:finish']);
            $this->state->setVersion($this->state->getCurrentVersion());
        }

        ProcessHelper::console(['plugin:refresh']);

        PluginHelper::installPlugins($this->projectDir);
        PluginHelper::updatePlugins($this->projectDir);

        ProcessHelper::console(['theme:compile', '--active-only']);
    }
}
