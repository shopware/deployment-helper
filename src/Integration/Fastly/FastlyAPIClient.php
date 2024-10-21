<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration\Fastly;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type Snippet array{name: string, type: string, content: string, priority: int, updated_at: string}
 */
class FastlyAPIClient
{
    private HttpClientInterface $client;
    private HttpClientInterface $baseClient;

    public function __construct(?HttpClientInterface $baseClient = null)
    {
        $this->baseClient = $baseClient ?? HttpClient::createForBaseUri('https://api.fastly.com');
    }

    public function setApiKey(string $apiKey): void
    {
        $this->client = $this->baseClient->withOptions([
            'headers' => [
                'Fastly-Key' => $apiKey,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @return Snippet[]
     */
    public function listSnippets(string $serviceId, int $version): array
    {
        $snippets = $this->client->request('GET', \sprintf('/service/%s/version/%d/snippet', $serviceId, $version))->toArray();

        return $snippets;
    }

    public function createSnippet(string $serviceId, int $version, string $name, string $type, string $content, int $priority): void
    {
        $this->client->request('POST', \sprintf('/service/%s/version/%d/snippet', $serviceId, $version), [
            'json' => [
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'dynamic' => 0,
                'priority' => $priority,
            ],
        ]);
    }

    public function updateSnippet(string $serviceId, int $version, string $name, string $type, string $content, int $priority): void
    {
        $this->client->request('PUT', \sprintf('/service/%s/version/%d/snippet/%s', $serviceId, $version, $name), [
            'json' => [
                'type' => $type,
                'content' => $content,
                'priority' => $priority,
            ],
        ]);
    }

    public function deleteSnippet(string $serviceId, int $version, string $name): void
    {
        $this->client->request('DELETE', \sprintf('/service/%s/version/%d/snippet/%s', $serviceId, $version, $name))->toArray();
    }

    public function cloneServiceVersion(string $serviceId, int $version): int
    {
        $response = $this->client->request('PUT', \sprintf('/service/%s/version/%d/clone', $serviceId, $version));

        $data = $response->toArray();

        return $data['number'];
    }

    public function activateServiceVersion(string $serviceId, int $version): void
    {
        $this->client->request('PUT', \sprintf('/service/%s/version/%d/activate', $serviceId, $version))->toArray();
    }

    public function getCurrentlyActiveVersion(string $serviceId): int
    {
        $response = $this->client->request('GET', \sprintf('/service/%s/version/active', $serviceId));

        $data = $response->toArray();

        return $data['number'];
    }
}
