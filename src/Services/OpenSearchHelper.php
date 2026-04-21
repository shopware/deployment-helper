<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\EnvironmentHelper;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class OpenSearchHelper
{
    public const DEFAULT_INDEX_ENTITY = 'product';
    public const DEFAULT_STOREFRONT_INDEX_PREFIX = 'sw';
    public const SHOP_INDEX_ACTION_NONE = 'none';
    public const SHOP_INDEX_ACTION_REINDEX = 'reindex';
    public const SHOP_INDEX_ACTION_UPDATE_MAPPING = 'update_mapping';

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    public function ensureShopIndexExists(): bool
    {
        $baseUri = $this->getConfiguredBaseUri();
        if ($baseUri === null) {
            return false;
        }

        $alias = $this->getConfiguredAliasName();
        $aliasResponse = $this->client->request('HEAD', $baseUri . '/_alias/' . rawurlencode($alias));
        $aliasStatusCode = $aliasResponse->getStatusCode();

        if ($aliasStatusCode === 200) {
            return false;
        }

        if ($aliasStatusCode !== 404) {
            throw new \RuntimeException(\sprintf('Could not inspect OpenSearch alias "%s"', $alias));
        }

        $index = $this->resolvePhysicalIndexName($alias);

        $createResponse = $this->client->request('PUT', $baseUri . '/' . rawurlencode($index));
        $createStatusCode = $createResponse->getStatusCode();

        if (!\in_array($createStatusCode, [200, 201], true)) {
            $content = $createResponse->getContent(false);

            if ($createStatusCode === 400 && str_contains($content, 'resource_already_exists_exception')) {
                $content = '';
            } else {
                throw new \RuntimeException(\sprintf('Could not create OpenSearch index "%s": %s', $index, $content));
            }
        }

        $putAliasResponse = $this->client->request('PUT', $baseUri . '/' . rawurlencode($index) . '/_alias/' . rawurlencode($alias));
        $putAliasStatusCode = $putAliasResponse->getStatusCode();

        if (\in_array($putAliasStatusCode, [200, 201], true)) {
            return true;
        }

        throw new \RuntimeException(\sprintf('Could not create OpenSearch alias "%s" for index "%s"', $alias, $index));
    }

    public function hasShopIndex(): bool
    {
        $baseUri = $this->getConfiguredBaseUri();
        if ($baseUri === null) {
            return false;
        }

        $alias = $this->getConfiguredAliasName();
        $aliasResponse = $this->client->request('HEAD', $baseUri . '/_alias/' . rawurlencode($alias));
        $aliasStatusCode = $aliasResponse->getStatusCode();

        if ($aliasStatusCode === 200) {
            return true;
        }

        if ($aliasStatusCode === 404) {
            return false;
        }

        throw new \RuntimeException(\sprintf('Could not inspect OpenSearch alias "%s"', $alias));
    }

    public function removeShopIndexAliasTargets(): void
    {
        $baseUri = $this->getConfiguredBaseUri();
        if ($baseUri === null) {
            return;
        }

        $alias = $this->getConfiguredAliasName();
        $aliasResponse = $this->client->request('GET', $baseUri . '/_alias/' . rawurlencode($alias));
        $aliasStatusCode = $aliasResponse->getStatusCode();

        if ($aliasStatusCode === 404) {
            return;
        }

        if ($aliasStatusCode >= 400) {
            throw new \RuntimeException(\sprintf('Could not fetch OpenSearch alias targets for "%s"', $alias));
        }

        $indices = $aliasResponse->toArray(false);

        foreach (array_keys($indices) as $index) {
            $deleteResponse = $this->client->request('DELETE', $baseUri . '/' . rawurlencode((string) $index));
            $deleteStatusCode = $deleteResponse->getStatusCode();

            if (!\in_array($deleteStatusCode, [200, 202], true)) {
                throw new \RuntimeException(\sprintf('Could not delete temporary OpenSearch index "%s"', $index));
            }
        }
    }

    public function switchShopAliasToNewestMappedIndex(): bool
    {
        $baseUri = $this->getConfiguredBaseUri();
        if ($baseUri === null) {
            return false;
        }

        $alias = $this->getConfiguredAliasName();
        $mappingResponse = $this->client->request('GET', $baseUri . '/' . rawurlencode($alias) . '_*/_mapping');
        $mappingStatusCode = $mappingResponse->getStatusCode();

        if ($mappingStatusCode === 404) {
            return false;
        }

        if ($mappingStatusCode >= 400) {
            throw new \RuntimeException(\sprintf('Could not inspect OpenSearch mappings for alias "%s"', $alias));
        }

        $mappings = $mappingResponse->toArray(false);

        $targetIndex = $this->findNewestMappedIndex($alias, $mappings);
        if ($targetIndex === null) {
            return false;
        }

        $aliasResponse = $this->client->request('GET', $baseUri . '/_alias/' . rawurlencode($alias));
        $aliasStatusCode = $aliasResponse->getStatusCode();

        $currentIndices = [];
        if ($aliasStatusCode === 200) {
            $currentIndices = $aliasResponse->toArray(false);
        } elseif ($aliasStatusCode !== 404) {
            throw new \RuntimeException(\sprintf('Could not inspect OpenSearch alias "%s"', $alias));
        }

        if (\array_key_exists($targetIndex, $currentIndices) && \count($currentIndices) === 1) {
            return false;
        }

        $actions = [];
        foreach (array_keys($currentIndices) as $index) {
            $actions[] = ['remove' => ['index' => (string) $index, 'alias' => $alias]];
        }
        $actions[] = ['add' => ['index' => $targetIndex, 'alias' => $alias]];

        $updateResponse = $this->client->request('POST', $baseUri . '/_aliases', [
            'json' => ['actions' => $actions],
        ]);

        if (\in_array($updateResponse->getStatusCode(), [200, 201], true)) {
            return true;
        }

        throw new \RuntimeException(\sprintf('Could not switch OpenSearch alias "%s" to index "%s"', $alias, $targetIndex));
    }

    public function getConfiguredBaseUri(): ?string
    {
        foreach ([
            'SHOPWARE_DEPLOYMENT_OPENSEARCH_URL',
            'OPENSEARCH_URL',
            'ELASTICSEARCH_URL',
            'SHOPWARE_ES_HOSTS',
        ] as $variableName) {
            $value = EnvironmentHelper::getVariable($variableName);
            if ($value === null || trim($value) === '') {
                continue;
            }

            $candidate = trim($value);

            if ($variableName === 'SHOPWARE_ES_HOSTS') {
                $candidate = $this->extractFirstHost($candidate);
            }

            if (preg_match('#^https?://#', $candidate) !== 1) {
                $candidate = 'http://' . $candidate;
            }

            return rtrim($candidate, '/');
        }

        return null;
    }

    public function getConfiguredAliasName(): string
    {
        return $this->resolveAliasName();
    }

    public function determineShopIndexAction(): string
    {
        return $this->prepareShopIndex();
    }

    public function prepareShopIndex(): string
    {
        $baseUri = $this->getConfiguredBaseUri();
        if ($baseUri === null) {
            return self::SHOP_INDEX_ACTION_NONE;
        }

        $alias = $this->getConfiguredAliasName();
        $indices = $this->fetchAliasTargets($baseUri, $alias);

        if ($indices === null) {
            if ($this->indexExists($baseUri, $alias)) {
                $this->deleteIndices($baseUri, [$alias]);
            }

            return self::SHOP_INDEX_ACTION_REINDEX;
        }

        if (\count($indices) !== 1) {
            $this->deleteIndices($baseUri, array_keys($indices));

            return self::SHOP_INDEX_ACTION_REINDEX;
        }

        $settingsResponse = $this->client->request('GET', $baseUri . '/' . rawurlencode($alias) . '/_settings');
        $settingsStatusCode = $settingsResponse->getStatusCode();

        if ($settingsStatusCode === 404) {
            $this->deleteIndices($baseUri, array_keys($indices));

            return self::SHOP_INDEX_ACTION_REINDEX;
        }

        if ($settingsStatusCode >= 400) {
            throw new \RuntimeException(\sprintf('Could not inspect OpenSearch settings for "%s"', $alias));
        }

        $settings = $settingsResponse->toArray(false);
        if (!$this->hasRequiredAnalysisSettings($settings)) {
            $this->deleteIndices($baseUri, array_keys($indices));

            return self::SHOP_INDEX_ACTION_REINDEX;
        }

        return self::SHOP_INDEX_ACTION_UPDATE_MAPPING;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAliasTargets(string $baseUri, string $alias): ?array
    {
        $aliasResponse = $this->client->request('GET', $baseUri . '/_alias/' . rawurlencode($alias));
        $aliasStatusCode = $aliasResponse->getStatusCode();

        if ($aliasStatusCode === 404) {
            return null;
        }

        if ($aliasStatusCode >= 400) {
            throw new \RuntimeException(\sprintf('Could not inspect OpenSearch alias "%s"', $alias));
        }

        return $aliasResponse->toArray(false);
    }

    private function indexExists(string $baseUri, string $index): bool
    {
        $response = $this->client->request('HEAD', $baseUri . '/' . rawurlencode($index));
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            return true;
        }

        if ($statusCode === 404) {
            return false;
        }

        throw new \RuntimeException(\sprintf('Could not inspect OpenSearch index "%s"', $index));
    }

    /**
     * @param list<string> $indices
     */
    private function deleteIndices(string $baseUri, array $indices): void
    {
        foreach ($indices as $index) {
            $deleteResponse = $this->client->request('DELETE', $baseUri . '/' . rawurlencode($index));
            $deleteStatusCode = $deleteResponse->getStatusCode();

            if (!\in_array($deleteStatusCode, [200, 202, 404], true)) {
                throw new \RuntimeException(\sprintf('Could not delete OpenSearch index "%s"', $index));
            }
        }
    }

    private function extractFirstHost(string $hosts): string
    {
        $decoded = json_decode($hosts, true);
        if (\is_array($decoded) && isset($decoded[0]) && \is_string($decoded[0])) {
            return trim($decoded[0]);
        }

        $firstHost = strtok($hosts, ',');
        if ($firstHost === false) {
            return $hosts;
        }

        return trim($firstHost);
    }

    private function resolveAliasName(): string
    {
        $prefix = EnvironmentHelper::getVariable('SHOPWARE_ES_INDEX_PREFIX', self::DEFAULT_STOREFRONT_INDEX_PREFIX);
        $entity = EnvironmentHelper::getVariable('SHOPWARE_DEPLOYMENT_OPENSEARCH_INDEX_ENTITY', self::DEFAULT_INDEX_ENTITY);

        $prefix = trim((string) $prefix);
        $entity = trim((string) $entity);

        if ($prefix === '') {
            $prefix = self::DEFAULT_STOREFRONT_INDEX_PREFIX;
        }

        if ($entity === '') {
            $entity = self::DEFAULT_INDEX_ENTITY;
        }

        return $prefix . '_' . $entity;
    }

    private function resolvePhysicalIndexName(string $alias): string
    {
        $time = EnvironmentHelper::getVariable('PHPUNIT_OPEN_SEARCH_HELPER_TIME');

        return $alias . '_' . (int) ($time ?? time());
    }

    /**
     * @param array<string, mixed> $mappings
     */
    private function findNewestMappedIndex(string $alias, array $mappings): ?string
    {
        $candidates = [];

        foreach ($mappings as $index => $mapping) {
            if (!\is_string($index) || !str_starts_with($index, $alias . '_')) {
                continue;
            }

            $properties = $mapping['mappings']['properties'] ?? null;
            if (!\is_array($properties) || $properties === []) {
                continue;
            }

            $timestamp = (int) substr($index, \strlen($alias) + 1);
            $candidates[$index] = $timestamp;
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function hasRequiredAnalysisSettings(array $settings): bool
    {
        $requiredNormalizers = ['sw_lowercase_normalizer'];
        $requiredAnalyzers = ['sw_whitespace_analyzer', 'sw_ngram_analyzer', 'sw_english_analyzer', 'sw_german_analyzer'];
        $requiredFilters = ['sw_ngram_filter', 'sw_english_stop_filter', 'sw_german_stop_filter'];

        foreach ($settings as $indexSettings) {
            if (!\is_array($indexSettings)) {
                continue;
            }

            $analysis = $indexSettings['settings']['index']['analysis'] ?? $indexSettings['settings']['analysis'] ?? null;
            if (!\is_array($analysis)) {
                continue;
            }

            $normalizers = $analysis['normalizer'] ?? null;
            $analyzers = $analysis['analyzer'] ?? null;
            $filters = $analysis['filter'] ?? null;

            if (!\is_array($normalizers) || !\is_array($analyzers) || !\is_array($filters)) {
                continue;
            }

            if ($this->hasAllKeys($normalizers, $requiredNormalizers)
                && $this->hasAllKeys($analyzers, $requiredAnalyzers)
                && $this->hasAllKeys($filters, $requiredFilters)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $requiredKeys
     */
    private function hasAllKeys(array $values, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $requiredKey) {
            if (!\array_key_exists($requiredKey, $values)) {
                return false;
            }
        }

        return true;
    }
}
