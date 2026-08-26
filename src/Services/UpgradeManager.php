<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\Plugin\PluginHelper;
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
            $shopLocale = EnvironmentHelper::getVariable('INSTALL_LOCALE', 'en-GB');
            $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . UrlHelper::normalizeSalesChannelUrl($salesChannelUrl), '--isoCode=' . $shopLocale]);
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
            $this->compileThemes($output);
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

    private function compileThemes(OutputInterface $output): void
    {
        if (!$this->configuration->themeCompile->parallel) {
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        $shopwareVersion = $this->state->getCurrentVersion();

        // Parallel fan-out needs theme:compile --only (Shopware 6.5.6+).
        if (!$this->supportsThemeCompileOnly($shopwareVersion)) {
            $output->writeln(\sprintf(
                'Parallel theme compile requires Shopware 6.5.6+ for --only (current: %s); falling back to serial compile',
                $shopwareVersion,
            ));
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        $assignments = $this->state->getActiveStorefrontThemeAssignments();

        if (\count($assignments) <= 1) {
            // Nothing to parallelize - fall back to the regular command.
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        // Shared plugin/theme assets live under theme/{themeId}. One sales channel per
        // theme must compile without --keep-assets so that directory is populated; any
        // further channels that reuse the same theme can safely keep existing assets.
        $seedSalesChannelIds = [];
        $parallelSalesChannelIds = [];
        $seenThemes = [];
        foreach ($assignments as $assignment) {
            if (!isset($seenThemes[$assignment['themeId']])) {
                $seenThemes[$assignment['themeId']] = true;
                $seedSalesChannelIds[] = $assignment['salesChannelId'];
            } else {
                $parallelSalesChannelIds[] = $assignment['salesChannelId'];
            }
        }

        if ($parallelSalesChannelIds === []) {
            // Every sales channel has a distinct theme - no shared-asset work to skip,
            // so the regular single-process compile is simpler and avoids temp-file races.
            $this->processHelper->console(['theme:compile', '--active-only']);

            return;
        }

        $workers = min($this->resolveThemeCompileWorkers(), \count($parallelSalesChannelIds));
        $output->writeln(\sprintf(
            'Compiling themes in parallel for %d sales channels (%d theme seed(s), %d workers)',
            \count($assignments),
            \count($seedSalesChannelIds),
            $workers,
        ));

        $baseArgs = $this->themeCompileBaseArgs($shopwareVersion);

        // Seed compiles write theme/{themeId} assets; run them serially so seeds for
        // different themes do not race on shared SCSS temp files.
        foreach ($seedSalesChannelIds as $salesChannelId) {
            $this->processHelper->console([...$baseArgs, '--only=' . $salesChannelId]);
        }

        $commands = [];
        foreach ($parallelSalesChannelIds as $salesChannelId) {
            // --keep-assets skips getAssetCopyFiles() (theme/{themeId}); per-channel CSS
            // and theme-prefix JS still compile into the sales-channel theme path.
            // --keep-assets itself exists since Shopware 6.4 and needs no version gate.
            $commands[] = [...$baseArgs, '--keep-assets', '--only=' . $salesChannelId];
        }

        $this->processHelper->consoleParallel($commands, $workers);
    }

    /**
     * @return list<string>
     */
    private function themeCompileBaseArgs(string $shopwareVersion): array
    {
        $args = ['theme:compile'];

        // --sync is available from Shopware 6.6.1 and forces synchronous compile when
        // theme.compile_async is enabled. Older cores reject the unknown option.
        if ($this->supportsThemeCompileSync($shopwareVersion)) {
            $args[] = '--sync';
        }

        return $args;
    }

    /**
     * theme:compile --only was introduced in Shopware 6.5.6.
     */
    private function supportsThemeCompileOnly(string $version): bool
    {
        return $this->shopwareVersionAtLeast($version, '6.5.6', defaultWhenUnparseable: true);
    }

    /**
     * theme:compile --sync was introduced in Shopware 6.6.1.
     */
    private function supportsThemeCompileSync(string $version): bool
    {
        // Unknown / dev versions: prefer --sync; async compile without it can
        // silently enqueue work and make the deploy appear successful too early.
        return $this->shopwareVersionAtLeast($version, '6.6.1', defaultWhenUnparseable: true);
    }

    private function shopwareVersionAtLeast(string $version, string $minimum, bool $defaultWhenUnparseable): bool
    {
        if (preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches) !== 1) {
            return $defaultWhenUnparseable;
        }

        return version_compare($matches[1], $minimum, '>=');
    }

    private function resolveThemeCompileWorkers(): int
    {
        $configured = $this->configuration->themeCompile->workers;
        if ($configured !== null) {
            return max(1, $configured);
        }

        return $this->detectCpuCount();
    }

    private function detectCpuCount(): int
    {
        if (\function_exists('shell_exec')) {
            foreach (['nproc 2>/dev/null', 'getconf _NPROCESSORS_ONLN 2>/dev/null', 'sysctl -n hw.ncpu 2>/dev/null'] as $cmd) {
                $value = @shell_exec($cmd);
                if (\is_string($value) && ctype_digit(trim($value))) {
                    return max(1, (int) trim($value));
                }
            }
        }

        return 4;
    }
}
