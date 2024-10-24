<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration;

use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(PostDeploy::class, '__invoke')]
class PlatformSHSubscriber
{
    public function __construct(
        private readonly ProcessHelper $processHelper,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(PostDeploy $event): void
    {
        if (EnvironmentHelper::getVariable('PLATFORM_ROUTES', '') === '') {
            return;
        }

        $event->output->writeln('<info>Detected Platform.SH environment, running additional commands</info>');

        /*
         * This env "PLATFORM_REGISTRY_NUMBER" is set when Platform.sh provisioned local disk var/cache.
         * Therefore the Cache folder is not automatically empty after each deploy, so we have to clear it manually.
         */
        if (EnvironmentHelper::getVariable('PLATFORM_REGISTRY_NUMBER', '') !== '') {
            $cacheDir = EnvironmentHelper::getVariable('APP_CACHE_DIR', $this->projectDir . '/var/cache');
            $appEnv = EnvironmentHelper::getVariable('APP_ENV', 'prod');

            $cmd = \sprintf('rm -Rf %s/var/cache/%s_*/*.*', $cacheDir, $appEnv);

            $this->processHelper->shell(['sh', '-c', $cmd]);

            $this->processHelper->console(['cache:clear']);
        }

        if (\PHP_OS === 'Linux') {
            $this->processHelper->shell(['pkill', '-f', '-USR2', '-u', 'web', 'php-fpm']);
        }
    }
}
