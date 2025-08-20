<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\EnvironmentHelper;

class TrackingService
{
    private const DEPLOYMENT_HELPER_ID = 'core.deployment_helper.id';

    private const DEFAULT_TRACKING_DOMAIN = 'udp.usage.shopware.io';

    /**
     * @var array<string, string>
     */
    private array $defaultTags;

    private string $id;

    private \Socket|false $socket;

    private string $domain;

    public function __construct(
        private readonly SystemConfigHelper $systemConfigHelper,
        private readonly ShopwareState $shopwareState,
    ) {
        $this->socket = @socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        $this->domain = EnvironmentHelper::getVariable('SHOPWARE_TRACKING_DOMAIN', self::DEFAULT_TRACKING_DOMAIN);
    }

    /**
     * @param array<string, string|int|float> $tags
     */
    public function track(string $eventName, array $tags = []): void
    {
        if (EnvironmentHelper::hasVariable('DO_NOT_TRACK')) {
            return;
        }

        $tags += $this->getTags();
        $id = $this->getId();

        if ($this->socket === false) {
            return;
        }

        $payload = json_encode([
            'event' => 'deployment_helper.' . $eventName,
            'tags' => $tags,
            'user_id' => $id,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR);

        @socket_sendto($this->socket, $payload, \strlen($payload), 0, $this->domain, 9000);
    }

    public function persistId(): void
    {
        if (isset($this->id)) {
            $this->systemConfigHelper->set(self::DEPLOYMENT_HELPER_ID, $this->id);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getTags(): array
    {
        if (isset($this->defaultTags)) {
            return $this->defaultTags;
        }

        $this->defaultTags = [
            'shopware_version' => $this->shopwareState->getCurrentVersion(),
        ];

        return $this->defaultTags;
    }

    private function getId(): string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        try {
            $id = $this->systemConfigHelper->get(self::DEPLOYMENT_HELPER_ID);
        } catch (\Throwable) {
            $this->id = $id = bin2hex(random_bytes(16));
        }

        if ($id === null) {
            $this->id = $id = bin2hex(random_bytes(16));
            $this->systemConfigHelper->set(self::DEPLOYMENT_HELPER_ID, $id);
        }

        return $this->id = $id;
    }
}
