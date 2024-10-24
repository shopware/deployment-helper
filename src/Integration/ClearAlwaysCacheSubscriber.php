<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostDeploy::class, method: '__invoke')]
readonly class ClearAlwaysCacheSubscriber
{
    public function __construct(
        private ProjectConfiguration $projectConfiguration,
        private ProcessHelper $processHelper,
    ) {
    }

    public function __invoke(PostDeploy $event): void
    {
        if (!$this->projectConfiguration->alwaysClearCache) {
            return;
        }

        $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
    }
}
