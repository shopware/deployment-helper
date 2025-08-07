<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration;

use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Services\SystemConfigHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostDeploy::class, method: '__invoke')]
readonly class UsageDataConsentSubscriber
{
    public function __construct(
        private SystemConfigHelper $systemConfigHelper,
    ) {
    }

    public function __invoke(PostDeploy $event): void
    {
        $consent = EnvironmentHelper::getVariable('SHOPWARE_USAGE_DATA_CONSENT', '');

        if ($consent === '') {
            return;
        }

        if (!\in_array($consent, ['requested', 'accepted', 'revoked'], true)) {
            throw new \InvalidArgumentException('Invalid value for SHOPWARE_USAGE_DATA_CONSENT. Must be one of: requested, accepted, revoked');
        }

        $this->systemConfigHelper->set('core.usageData.consentState', $consent);
    }
}
