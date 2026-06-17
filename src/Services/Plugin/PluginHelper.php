<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services\Plugin;

use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\Command\ConsoleCommand;
use Symfony\Component\Console\Output\OutputInterface;

class PluginHelper
{
    public function __construct(
        private readonly PluginLoader $pluginLoader,
        private readonly ProcessHelper $processHelper,
        private readonly PluginManagementPlanner $planner,
    ) {
    }

    public function installPlugins(OutputInterface $output, bool $skipAssetsInstall = false): void
    {
        $this->execute($this->planner->planInstall($this->pluginLoader->load($output), $this->additionalParameters($skipAssetsInstall)));
    }

    public function updatePlugins(OutputInterface $output, bool $skipAssetsInstall = false): void
    {
        $this->execute($this->planner->planUpdate($this->pluginLoader->load($output), $this->additionalParameters($skipAssetsInstall)));
    }

    public function deactivatePlugins(OutputInterface $output, bool $skipAssetsInstall = false): void
    {
        $this->execute($this->planner->planDeactivate($this->pluginLoader->load($output), $this->additionalParameters($skipAssetsInstall)));
    }

    public function removePlugins(OutputInterface $output, bool $skipAssetsInstall = false): void
    {
        $this->execute($this->planner->planUninstall($this->pluginLoader->load($output), $this->additionalParameters($skipAssetsInstall)));
    }

    /**
     * @return list<string>
     */
    private function additionalParameters(bool $skipAssetsInstall): array
    {
        return $skipAssetsInstall ? ['--skip-asset-build'] : [];
    }

    /**
     * @param list<ConsoleCommand> $commands
     */
    private function execute(array $commands): void
    {
        foreach ($commands as $command) {
            $this->processHelper->console($command->toArgs());
        }
    }
}
