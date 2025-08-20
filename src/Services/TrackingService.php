<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\EnvironmentHelper;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackingService
{
    private const API_ENDPOINT = 'https://usage.shopware.io';

    private const DEPLOYMENT_HELPER_ID = 'core.deployment_helper.id';

    /**
     * @var array<string, string>
     */
    private array $defaultTags;

    private string $id;

    private HttpClientInterface $client;

    /**
     * @var list<\Symfony\Contracts\HttpClient\ResponseInterface>
     */
    private array $responses = [];

    public function __construct(
        private readonly SystemConfigHelper $systemConfigHelper,
        private readonly ShopwareState $shopwareState,
    ) {
        $this->client = HttpClient::create([
            'base_uri' => EnvironmentHelper::getVariable('SHOPWARE_TRACKING_ENDPOINT', self::API_ENDPOINT),
        ]);

        register_shutdown_function([$this, 'shutdown']);
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

        $this->responses[] = $this->client->request('PUT', '/track', [
            'json' => [
                'event' => 'deployment_helper.' . $eventName,
                'tags' => $tags,
                'user_id' => $id,
                'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
            ],
            'timeout' => 0.8,
            'max_duration' => 0.8,
            'headers' => [
                'User-Agent' => 'shopware-deployment-helper',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function persistId(): void
    {
        $this->systemConfigHelper->set(self::DEPLOYMENT_HELPER_ID, $this->id);
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

    private function shutdown(): void
    {
        usleep(100);
        foreach ($this->responses as $response) {
            $response->cancel();
        }
    }
}
