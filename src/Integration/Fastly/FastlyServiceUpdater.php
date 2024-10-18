<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration\Fastly;

use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsEventListener(event: PostDeploy::class, method: '__invoke')]
class FastlyServiceUpdater
{
    private const FILE_NAME_REGEX = '/^(?<name>\w+)\.?(?<priority>\d+)?\.vcl$/m';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly FastlyAPIClient $fastlyAPIClient,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function __invoke(PostDeploy $event): void
    {
        $fastlyConfigPath = Path::join($this->projectDir, 'config', 'fastly');
        if (!$this->filesystem->exists($fastlyConfigPath)) {
            return;
        }

        $apiToken = EnvironmentHelper::getVariable('FASTLY_API_TOKEN', '');
        $serviceId = EnvironmentHelper::getVariable('FASTLY_SERVICE_ID', '');

        $io = new SymfonyStyle(new ArgvInput(), $event->output);

        if ($apiToken === '' || $serviceId === '') {
            $io->info('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set. Skipping Fastly service update.');

            return;
        }

        $this->fastlyAPIClient->setApiKey($apiToken);

        $currentlyActiveVersion = $this->fastlyAPIClient->getCurrentlyActiveVersion($serviceId);
        $context = new FastlyContext(
            $this->fastlyAPIClient->listSnippets($serviceId, $currentlyActiveVersion),
            $serviceId,
            $currentlyActiveVersion
        );

        $types = scandir($fastlyConfigPath, \SCANDIR_SORT_NONE);
        \assert($types !== false);

        $changes = [];

        foreach ($types as $type) {
            if ($type === '.' || $type === '..') {
                continue;
            }

            $config = scandir(Path::join($fastlyConfigPath, $type), \SCANDIR_SORT_NONE);
            \assert($config !== false);

            foreach ($config as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if ($this->updateFastlyService($context, $type, Path::join($fastlyConfigPath, $type, $file))) {
                    $changes[] = \sprintf('%s_%s', $type, $file);
                }
            }
        }

        if ($context->createdVersion !== null) {
            $this->fastlyAPIClient->activateServiceVersion($serviceId, $context->createdVersion);

            $io->success('Fastly service updated successfully with following snippets: ' . implode(', ', $changes));
        } else {
            $io->info('No changes detected in Fastly service configuration.');
        }
    }

    private function updateFastlyService(FastlyContext $context, string $type, string $configPath): bool
    {
        $configContent = (string) file_get_contents($configPath);

        if (preg_match_all(self::FILE_NAME_REGEX, basename($configPath), $matches, \PREG_SET_ORDER) !== 1) {
            return false;
        }

        $name = $matches[0]['name'];
        $priority = (int) ($matches[0]['priority'] ?? 0);

        if ($name === 'default') {
            $snippetName = 'shopware_' . $type;
        } else {
            $snippetName = 'shopware_' . $type . '.' . $priority;
        }

        if ($context->hasSnippet($snippetName)) {
            if ($context->hasSnippetChanged($snippetName, $configContent)) {
                $newVersionId = $this->getNewVersionId($context);
                $this->fastlyAPIClient->updateSnippet($context->serviceId, $newVersionId, $snippetName, $type, $configContent, $priority);

                return true;
            }
        } else {
            $newVersionId = $this->getNewVersionId($context);
            $this->fastlyAPIClient->createSnippet($context->serviceId, $newVersionId, $snippetName, $type, $configContent, $priority);

            return true;
        }

        return false;
    }

    private function getNewVersionId(FastlyContext $context): int
    {
        if ($context->createdVersion === null) {
            $response = $this->fastlyAPIClient->cloneServiceVersion($context->serviceId, $context->currentlyActiveVersion);
            $context->createdVersion = $response;
        }

        return $context->createdVersion;
    }
}
