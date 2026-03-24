<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration;

use Doctrine\DBAL\Connection;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Services\SystemConfigHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostDeploy::class, method: '__invoke')]
readonly class UsageDataConsentSubscriber
{
    public function __construct(
        private SystemConfigHelper $systemConfigHelper,
        private Connection $connection,
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
        $this->updateInConsentTable($consent);
    }

    private function updateInConsentTable(string $status): void
    {
        // requested is not a valid state in the new system
        if ($status === 'requested') {
            return;
        }

        try {
            $this->connection->createSchemaManager()->introspectTableByUnquotedName('consent_state');
        } catch (\Exception) {
            // consent system is not used in this version
            return;
        }

        $currentState = $this->connection->fetchAssociative('SELECT * FROM `consent_state` WHERE `name` = "backend_data"');

        if ($currentState === false) {
            $this->insertBackendConsent($status);

            return;
        }

        $this->updateConsent($status, $currentState);
    }

    private function insertBackendConsent(string $status): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `consent_state` (`id`, `name`, `identifier`, `state`, `actor`, `updated_at`)
            VALUES (:id, "backend_data", "system", :state, "deployment-helper", NOW())',
            [
                'id' => random_bytes(16),
                'state' => $status === 'accepted' ? 'accepted' : 'declined',
            ]
        );
    }

    private function updateConsent(string $status, mixed $currentState): void
    {
        if ($status === $currentState['state'] || ($status === 'revoked' && $currentState['state'] === 'declined')) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `consent_state`
            SET `state` = :state, `actor` = "deployment-helper", `updated_at` = NOW()
            WHERE `name` = "backend_data"',
            [
                'state' => $status,
            ]
        );
    }
}
