<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\OneTimeTaskWhen;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeManager
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly ProcessHelper $processHelper,
        private readonly PluginHelper $pluginHelper,
        private readonly AppHelper $appHelper,
        private readonly HookExecutor $hookExecutor,
        private readonly OneTimeTasks $oneTimeTasks,
        private readonly ProjectConfiguration $configuration,
        private readonly AccountService $accountService,
        private readonly TrackingService $trackingService,
    ) {
    }

    public function run(RunConfiguration $configuration, OutputInterface $output): void
    {
        $this->processHelper->setTimeout($configuration->timeout);

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE_UPDATE);

        // Execute one-time tasks that should run before the update
        $this->oneTimeTasks->execute($output, OneTimeTaskWhen::BEFORE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->enableMaintenanceMode();

            $output->writeln('Maintenance mode is enabled, clearing cache to make sure it is visible');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }

        $output->writeln('Shopware is installed, running update tools');

        $this->processHelper->console(['messenger:setup-transports']);

        $previousVersion = $this->state->getPreviousVersion();
        $currentVersion = $this->state->getCurrentVersion();
        if ($previousVersion !== $currentVersion) {
            $output->writeln(\sprintf('Updating Shopware from %s to %s', $previousVersion, $currentVersion));

            $additionalUpdateParameters = [];

            if ($configuration->skipAssetsInstall) {
                $additionalUpdateParameters[] = '--skip-asset-build';
            }

            $took = microtime(true);

            $this->processHelper->console(['system:update:finish', ...$additionalUpdateParameters]);

            $this->state->setVersion($currentVersion);

            $this->trackingService->track('upgrade', [
                'took' => microtime(true) - $took,
                'previous_shopware_version' => $previousVersion,
            ]);
        }

        $salesChannelUrl = EnvironmentHelper::getVariable('SALES_CHANNEL_URL');

        if ($salesChannelUrl !== null && $this->state->isStorefrontInstalled() && !$this->state->isSalesChannelExisting($salesChannelUrl)) {
            $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . UrlHelper::normalizeSalesChannelUrl($salesChannelUrl)]);
        }

        $this->processHelper->console(['plugin:refresh']);

        if ($this->state->isStorefrontInstalled()) {
            $this->processHelper->console(['theme:refresh']);
        }

        $this->processHelper->console(['scheduled-task:register']);
        $this->processHelper->console(['messenger:stop-workers']);

        $this->pluginHelper->installPlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->updatePlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->deactivatePlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->removePlugins($output, $configuration->skipAssetsInstall);

        if ($this->configuration->store->licenseDomain !== '') {
            $this->accountService->refresh(new SymfonyStyle(new ArgvInput([]), $output), $currentVersion, $this->configuration->store->licenseDomain);
        }

        $this->appHelper->installApps();
        $this->appHelper->updateApps();
        $this->appHelper->deactivateApps();
        $this->appHelper->removeApps();

        if (!$configuration->skipThemeCompile) {
            $took = microtime(true);
            $this->compileThemes($configuration, $output);
            $this->trackingService->track('theme_compiled', ['took' => microtime(true) - $took]);
        }

        // Execute one-time tasks that should run after the update
        $this->oneTimeTasks->execute($output, OneTimeTaskWhen::AFTER);

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_UPDATE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->disableMaintenanceMode();

            $output->writeln('Maintenance mode is disabled, clearing cache to make sure the storefront is visible again');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }
    }

    private function compileThemes(RunConfiguration $configuration, OutputInterface $output): void
    {
        if (!$configuration->parallelThemeCompile) {
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        $salesChannelIds = $this->state->getActiveStorefrontSalesChannelIds();

        if (\count($salesChannelIds) <= 1) {
            // Nothing to parallelize - fall back to the regular command.
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        $workers = $configuration->themeCompileWorkers ?? self::detectCpuCount($output);
        $output->writeln(\sprintf(
            'Compiling themes in parallel for %d sales channels with %d workers',
            \count($salesChannelIds),
            $workers,
        ));

        // First sales channel runs serially so it can write the shared theme assets
        // (CSS plus JS bundles) without racing the parallel workers.
        $first = array_shift($salesChannelIds);
        $this->processHelper->console(['theme:compile', '--sync', '--sales-channel-id=' . $first]);

        $commands = [];
        foreach ($salesChannelIds as $id) {
            // --keep-assets skips the shared JS bundle copy, leaving only per-channel CSS.
            $commands[] = ['theme:compile', '--sync', '--keep-assets', '--sales-channel-id=' . $id];
        }

        $this->processHelper->consoleParallel($commands, $workers);
    }

    private const DEFAULT_WORKERS = 4;

    private static function detectCpuCount(OutputInterface $output): int
    {
        if (\function_exists('shell_exec')) {
            foreach (['nproc 2>/dev/null', 'getconf _NPROCESSORS_ONLN 2>/dev/null', 'sysctl -n hw.ncpu 2>/dev/null'] as $cmd) {
                $value = @shell_exec($cmd);
                if (\is_string($value) && ctype_digit(trim($value))) {
                    return max(1, (int) trim($value));
                }
            }
        }

        $output->writeln(\sprintf(
            '<comment>Could not auto-detect CPU count, falling back to %d workers. Set --theme-compile-workers or SHOPWARE_DEPLOYMENT_THEME_COMPILE_WORKERS to override.</comment>',
            self::DEFAULT_WORKERS,
        ));

        return self::DEFAULT_WORKERS;
    }
}
