<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\OpenSearchHelper;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;
use Zalas\PHPUnit\Globals\Attribute\Putenv;
use Zalas\PHPUnit\Globals\Attribute\Server;

#[CoversClass(OpenSearchHelper::class)]
class OpenSearchHelperTest extends TestCase
{
    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Server('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Putenv('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Env('OPENSEARCH_URL', unset: true)]
    #[Server('OPENSEARCH_URL', unset: true)]
    #[Putenv('OPENSEARCH_URL', unset: true)]
    #[Env('ELASTICSEARCH_URL', unset: true)]
    #[Server('ELASTICSEARCH_URL', unset: true)]
    #[Putenv('ELASTICSEARCH_URL', unset: true)]
    #[Env('SHOPWARE_ES_HOSTS', unset: true)]
    #[Server('SHOPWARE_ES_HOSTS', unset: true)]
    #[Putenv('SHOPWARE_ES_HOSTS', unset: true)]
    public function testSkipsWhenNoOpenSearchConfigurationExists(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(static::never())->method('request');

        $helper = new OpenSearchHelper($client);

        static::assertFalse($helper->ensureShopIndexExists());
        static::assertFalse($helper->hasShopIndex());
        static::assertFalse($helper->switchShopAliasToNewestMappedIndex());
        $helper->removeShopIndexAliasTargets();
        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_NONE, $helper->determineShopIndexAction());
    }

    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', 'search.internal:9200/')]
    public function testNormalizesConfiguredBaseUri(): void
    {
        $helper = new OpenSearchHelper($this->createMock(HttpClientInterface::class));

        static::assertSame('http://search.internal:9200', $helper->getConfiguredBaseUri());
    }

    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Server('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Putenv('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Env('OPENSEARCH_URL', unset: true)]
    #[Server('OPENSEARCH_URL', unset: true)]
    #[Putenv('OPENSEARCH_URL', unset: true)]
    #[Env('ELASTICSEARCH_URL', unset: true)]
    #[Server('ELASTICSEARCH_URL', unset: true)]
    #[Putenv('ELASTICSEARCH_URL', unset: true)]
    #[Env('SHOPWARE_ES_HOSTS', 'opensearch:9200,backup:9200')]
    public function testUsesFirstCommaSeparatedShopwareHost(): void
    {
        $helper = new OpenSearchHelper($this->createMock(HttpClientInterface::class));

        static::assertSame('http://opensearch:9200', $helper->getConfiguredBaseUri());
    }

    #[Env('SHOPWARE_ES_INDEX_PREFIX', '')]
    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_INDEX_ENTITY', '')]
    public function testUsesDefaultAliasPartsWhenConfigurationIsEmpty(): void
    {
        $helper = new OpenSearchHelper($this->createMock(HttpClientInterface::class));

        static::assertSame('sw_product', $helper->getConfiguredAliasName());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionReturnsReindexWhenAliasIsMissing(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $indexResponse = $this->createMock(ResponseInterface::class);
        $indexResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $indexResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $aliasResponse,
                    ['HEAD', 'http://localhost:9200/tenant-shop_product'] => $indexResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionDeletesConcreteIndexWhenAliasNameIsOccupied(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $indexExistsResponse = $this->createMock(ResponseInterface::class);
        $indexExistsResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $deleteResponse = $this->createMock(ResponseInterface::class);
        $deleteResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $indexExistsResponse, $deleteResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $aliasResponse,
                    ['HEAD', 'http://localhost:9200/tenant-shop_product'] => $indexExistsResponse,
                    ['DELETE', 'http://localhost:9200/tenant-shop_product'] => $deleteResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionReturnsReindexWhenRequiredAnalyzersAreMissing(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $aliasResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => ['aliases' => ['tenant-shop_product' => []]],
        ]);

        $settingsResponse = $this->createMock(ResponseInterface::class);
        $settingsResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $settingsResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => [
                'settings' => [
                    'index' => [
                        'analysis' => [
                            'normalizer' => ['sw_lowercase_normalizer' => ['type' => 'custom']],
                            'analyzer' => ['sw_ngram_analyzer' => ['type' => 'custom']],
                            'filter' => ['sw_ngram_filter' => ['type' => 'ngram']],
                        ],
                    ],
                ],
            ],
        ]);

        $deleteResponse = $this->createMock(ResponseInterface::class);
        $deleteResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $settingsResponse, $deleteResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $aliasResponse,
                    ['GET', 'http://localhost:9200/tenant-shop_product/_settings'] => $settingsResponse,
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_123'] => $deleteResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionDeletesAliasTargetsWhenAliasHasMultipleIndices(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $aliasResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => ['aliases' => ['tenant-shop_product' => []]],
            'tenant-shop_product_456' => ['aliases' => ['tenant-shop_product' => []]],
        ]);

        $deleteFirstResponse = $this->createMock(ResponseInterface::class);
        $deleteFirstResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $deleteSecondResponse = $this->createMock(ResponseInterface::class);
        $deleteSecondResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $deleteFirstResponse, $deleteSecondResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $aliasResponse,
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_123'] => $deleteFirstResponse,
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_456'] => $deleteSecondResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionReturnsUpdateMappingWhenSettingsAreReady(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $aliasResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => ['aliases' => ['tenant-shop_product' => []]],
        ]);

        $settingsResponse = $this->createMock(ResponseInterface::class);
        $settingsResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $settingsResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => [
                'settings' => [
                    'index' => [
                        'analysis' => [
                            'normalizer' => ['sw_lowercase_normalizer' => ['type' => 'custom']],
                            'analyzer' => [
                                'sw_whitespace_analyzer' => ['type' => 'custom'],
                                'sw_ngram_analyzer' => ['type' => 'custom'],
                                'sw_english_analyzer' => ['type' => 'custom'],
                                'sw_german_analyzer' => ['type' => 'custom'],
                            ],
                            'filter' => [
                                'sw_ngram_filter' => ['type' => 'ngram'],
                                'sw_english_stop_filter' => ['type' => 'stop'],
                                'sw_german_stop_filter' => ['type' => 'stop'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $settingsResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ($url) {
                    'http://localhost:9200/_alias/tenant-shop_product' => $aliasResponse,
                    'http://localhost:9200/tenant-shop_product/_settings' => $settingsResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_UPDATE_MAPPING, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetermineShopIndexActionReturnsUpdateMappingWhenAliasSettingsAreReadyAndMappingsAlreadyExist(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $aliasResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => ['aliases' => ['tenant-shop_product' => []]],
        ]);

        $settingsResponse = $this->createMock(ResponseInterface::class);
        $settingsResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $settingsResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_123' => [
                'settings' => [
                    'index' => [
                        'analysis' => [
                            'normalizer' => ['sw_lowercase_normalizer' => ['type' => 'custom']],
                            'analyzer' => [
                                'sw_whitespace_analyzer' => ['type' => 'custom'],
                                'sw_ngram_analyzer' => ['type' => 'custom'],
                                'sw_english_analyzer' => ['type' => 'custom'],
                                'sw_german_analyzer' => ['type' => 'custom'],
                            ],
                            'filter' => [
                                'sw_ngram_filter' => ['type' => 'ngram'],
                                'sw_english_stop_filter' => ['type' => 'stop'],
                                'sw_german_stop_filter' => ['type' => 'stop'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $settingsResponse): ResponseInterface {
                static::assertSame([], $options);

                return match ($url) {
                    'http://localhost:9200/_alias/tenant-shop_product' => $aliasResponse,
                    'http://localhost:9200/tenant-shop_product/_settings' => $settingsResponse,
                    default => throw new \RuntimeException('Unexpected request: ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        static::assertSame(OpenSearchHelper::SHOP_INDEX_ACTION_UPDATE_MAPPING, $helper->determineShopIndexAction());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSkipsCreationWhenShopAliasAlreadyExists(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('HEAD', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($aliasResponse);

        $helper = new OpenSearchHelper($client);

        static::assertFalse($helper->ensureShopIndexExists());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetectsWhenShopAliasExists(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('HEAD', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($aliasResponse);

        $helper = new OpenSearchHelper($client);

        static::assertTrue($helper->hasShopIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testDetectsWhenShopAliasIsMissing(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('HEAD', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($aliasResponse);

        $helper = new OpenSearchHelper($client);

        static::assertFalse($helper->hasShopIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testThrowsWhenShopAliasCannotBeInspected(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('HEAD', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($this->createResponse(500));

        $helper = new OpenSearchHelper($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not inspect OpenSearch alias "tenant-shop_product"');

        $helper->hasShopIndex();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testCreatesMissingShopAliasUsingShopwareIndexNamePattern(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $putAliasResponse = $this->createMock(ResponseInterface::class);
        $putAliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $createResponse, $putAliasResponse): ResponseInterface {
                if ($method === 'HEAD') {
                    static::assertSame('http://localhost:9200/_alias/tenant-shop_product', $url);
                    static::assertSame([], $options);

                    return $aliasResponse;
                }

                if ($url === 'http://localhost:9200/tenant-shop_product_1704067200') {
                    static::assertSame('PUT', $method);
                    static::assertSame([], $options);

                    return $createResponse;
                }

                static::assertSame('PUT', $method);
                static::assertSame('http://localhost:9200/tenant-shop_product_1704067200/_alias/tenant-shop_product', $url);
                static::assertSame([], $options);

                return $putAliasResponse;
            });

        $helper = new OpenSearchHelper($client);

        $time = time();
        try {
            $this->setTimeForTest(1704067200);
            static::assertTrue($helper->ensureShopIndexExists());
        } finally {
            $this->setTimeForTest($time);
        }
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testCreatesAliasWhenPhysicalIndexAlreadyExistsConcurrently(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['HEAD', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(404),
                    ['PUT', 'http://localhost:9200/tenant-shop_product_1704067200'] => $this->createResponse(400, null, 'resource_already_exists_exception'),
                    ['PUT', 'http://localhost:9200/tenant-shop_product_1704067200/_alias/tenant-shop_product'] => $this->createResponse(200),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        $time = time();
        try {
            $this->setTimeForTest(1704067200);
            static::assertTrue($helper->ensureShopIndexExists());
        } finally {
            $this->setTimeForTest($time);
        }
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testThrowsWhenMissingShopIndexCannotBeCreated(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['HEAD', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(404),
                    ['PUT', 'http://localhost:9200/tenant-shop_product_1704067200'] => $this->createResponse(500, null, 'boom'),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not create OpenSearch index "tenant-shop_product_1704067200": boom');

        $time = time();
        try {
            $this->setTimeForTest(1704067200);
            $helper->ensureShopIndexExists();
        } finally {
            $this->setTimeForTest($time);
        }
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testThrowsWhenMissingShopAliasCannotBeCreated(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['HEAD', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(404),
                    ['PUT', 'http://localhost:9200/tenant-shop_product_1704067200'] => $this->createResponse(200),
                    ['PUT', 'http://localhost:9200/tenant-shop_product_1704067200/_alias/tenant-shop_product'] => $this->createResponse(500),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $helper = new OpenSearchHelper($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not create OpenSearch alias "tenant-shop_product" for index "tenant-shop_product_1704067200"');

        $time = time();
        try {
            $this->setTimeForTest(1704067200);
            $helper->ensureShopIndexExists();
        } finally {
            $this->setTimeForTest($time);
        }
    }

    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Server('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Putenv('SHOPWARE_DEPLOYMENT_OPENSEARCH_URL', unset: true)]
    #[Env('OPENSEARCH_URL', unset: true)]
    #[Server('OPENSEARCH_URL', unset: true)]
    #[Putenv('OPENSEARCH_URL', unset: true)]
    #[Env('ELASTICSEARCH_URL', unset: true)]
    #[Server('ELASTICSEARCH_URL', unset: true)]
    #[Putenv('ELASTICSEARCH_URL', unset: true)]
    #[Env('SHOPWARE_ES_HOSTS', '["opensearch:9200","backup:9200"]')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_INDEX_ENTITY', 'category')]
    public function testUsesFirstShopwareHostAndConfiguredEntity(): void
    {
        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(404);

        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->expects(static::once())->method('getStatusCode')->willReturn(201);

        $putAliasResponse = $this->createMock(ResponseInterface::class);
        $putAliasResponse->expects(static::once())->method('getStatusCode')->willReturn(201);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($aliasResponse, $createResponse, $putAliasResponse): ResponseInterface {
                if ($method === 'HEAD') {
                    static::assertSame('http://opensearch:9200/_alias/tenant-shop_category', $url);
                    static::assertSame([], $options);

                    return $aliasResponse;
                }

                if ($url === 'http://opensearch:9200/tenant-shop_category_1704067200') {
                    static::assertSame('PUT', $method);
                    static::assertSame([], $options);

                    return $createResponse;
                }

                static::assertSame('http://opensearch:9200/tenant-shop_category_1704067200/_alias/tenant-shop_category', $url);
                static::assertSame([], $options);

                return $putAliasResponse;
            });

        $helper = new OpenSearchHelper($client);

        $time = time();
        try {
            $this->setTimeForTest(1704067200);
            static::assertTrue($helper->ensureShopIndexExists());
        } finally {
            $this->setTimeForTest($time);
        }
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchesAliasToNewestMappedIndex(): void
    {
        $mappingResponse = $this->createMock(ResponseInterface::class);
        $mappingResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $mappingResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_100' => ['mappings' => []],
            'tenant-shop_product_200' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
            'tenant-shop_product_300' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
        ]);

        $aliasResponse = $this->createMock(ResponseInterface::class);
        $aliasResponse->expects(static::once())->method('getStatusCode')->willReturn(200);
        $aliasResponse->expects(static::once())->method('toArray')->with(false)->willReturn([
            'tenant-shop_product_100' => ['aliases' => ['tenant-shop_product' => []]],
        ]);

        $updateResponse = $this->createMock(ResponseInterface::class);
        $updateResponse->expects(static::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options = []) use ($mappingResponse, $aliasResponse, $updateResponse): ResponseInterface {
                if ($url === 'http://localhost:9200/tenant-shop_product_*/_mapping') {
                    static::assertSame('GET', $method);
                    static::assertSame([], $options);

                    return $mappingResponse;
                }

                if ($url === 'http://localhost:9200/_alias/tenant-shop_product') {
                    static::assertSame('GET', $method);
                    static::assertSame([], $options);

                    return $aliasResponse;
                }

                static::assertSame('POST', $method);
                static::assertSame('http://localhost:9200/_aliases', $url);
                static::assertSame([
                    'json' => [
                        'actions' => [
                            ['remove' => ['index' => 'tenant-shop_product_100', 'alias' => 'tenant-shop_product']],
                            ['add' => ['index' => 'tenant-shop_product_300', 'alias' => 'tenant-shop_product']],
                        ],
                    ],
                ], $options);

                return $updateResponse;
            });

        $helper = new OpenSearchHelper($client);

        static::assertTrue($helper->switchShopAliasToNewestMappedIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testRemovesAllShopAliasTargets(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['aliases' => ['tenant-shop_product' => []]],
                        'tenant-shop_product_200' => ['aliases' => ['tenant-shop_product' => []]],
                    ]),
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_100'] => $this->createResponse(200),
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_200'] => $this->createResponse(202),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        (new OpenSearchHelper($client))->removeShopIndexAliasTargets();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSkipsRemovingAliasTargetsWhenAliasIsMissing(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('GET', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($this->createResponse(404));

        (new OpenSearchHelper($client))->removeShopIndexAliasTargets();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testThrowsWhenAliasTargetsCannotBeFetched(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('GET', 'http://localhost:9200/_alias/tenant-shop_product')
            ->willReturn($this->createResponse(500));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not fetch OpenSearch alias targets for "tenant-shop_product"');

        (new OpenSearchHelper($client))->removeShopIndexAliasTargets();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testThrowsWhenAliasTargetCannotBeDeleted(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['aliases' => ['tenant-shop_product' => []]],
                    ]),
                    ['DELETE', 'http://localhost:9200/tenant-shop_product_100'] => $this->createResponse(500),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not delete temporary OpenSearch index "tenant-shop_product_100"');

        (new OpenSearchHelper($client))->removeShopIndexAliasTargets();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchReturnsFalseWhenMappingsAreMissing(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('GET', 'http://localhost:9200/tenant-shop_product_*/_mapping')
            ->willReturn($this->createResponse(404));

        static::assertFalse((new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchReturnsFalseWhenNoMappedIndexHasProperties(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('GET', 'http://localhost:9200/tenant-shop_product_*/_mapping')
            ->willReturn($this->createResponse(200, [
                'other_100' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
                'tenant-shop_product_100' => ['mappings' => []],
            ]));

        static::assertFalse((new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchReturnsFalseWhenAliasAlreadyTargetsNewestMappedIndex(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/tenant-shop_product_*/_mapping'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
                    ]),
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['aliases' => ['tenant-shop_product' => []]],
                    ]),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        static::assertFalse((new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchCreatesAliasWhenItDoesNotExistYet(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                if ($url === 'http://localhost:9200/tenant-shop_product_*/_mapping') {
                    static::assertSame('GET', $method);
                    static::assertSame([], $options);

                    return $this->createResponse(200, [
                        'tenant-shop_product_100' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
                    ]);
                }

                if ($url === 'http://localhost:9200/_alias/tenant-shop_product') {
                    static::assertSame('GET', $method);
                    static::assertSame([], $options);

                    return $this->createResponse(404);
                }

                static::assertSame('POST', $method);
                static::assertSame('http://localhost:9200/_aliases', $url);
                static::assertSame([
                    'json' => [
                        'actions' => [
                            ['add' => ['index' => 'tenant-shop_product_100', 'alias' => 'tenant-shop_product']],
                        ],
                    ],
                ], $options);

                return $this->createResponse(201);
            });

        static::assertTrue((new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex());
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchThrowsWhenMappingsCannotBeInspected(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::once())
            ->method('request')
            ->with('GET', 'http://localhost:9200/tenant-shop_product_*/_mapping')
            ->willReturn($this->createResponse(500));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not inspect OpenSearch mappings for alias "tenant-shop_product"');

        (new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchThrowsWhenAliasCannotBeInspected(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame([], $options);

                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/tenant-shop_product_*/_mapping'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
                    ]),
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(500),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not inspect OpenSearch alias "tenant-shop_product"');

        (new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex();
    }

    #[Env('OPENSEARCH_URL', 'http://localhost:9200')]
    #[Env('SHOPWARE_ES_INDEX_PREFIX', 'tenant-shop')]
    public function testSwitchThrowsWhenAliasUpdateFails(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(static::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                return match ([$method, $url]) {
                    ['GET', 'http://localhost:9200/tenant-shop_product_*/_mapping'] => $this->createResponse(200, [
                        'tenant-shop_product_100' => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
                    ]),
                    ['GET', 'http://localhost:9200/_alias/tenant-shop_product'] => $this->createResponse(404),
                    ['POST', 'http://localhost:9200/_aliases'] => $this->createResponse(500),
                    default => throw new \RuntimeException('Unexpected request: ' . $method . ' ' . $url),
                };
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not switch OpenSearch alias "tenant-shop_product" to index "tenant-shop_product_100"');

        (new OpenSearchHelper($client))->switchShopAliasToNewestMappedIndex();
    }

    /**
     * @param array<string, mixed>|null $array
     */
    private function createResponse(int $statusCode, ?array $array = null, ?string $content = null): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        if ($array !== null) {
            $response->expects(static::once())->method('toArray')->with(false)->willReturn($array);
        }

        if ($content !== null) {
            $response->expects(static::once())->method('getContent')->with(false)->willReturn($content);
        }

        return $response;
    }

    private function setTimeForTest(int $time): void
    {
        putenv('PHPUNIT_OPEN_SEARCH_HELPER_TIME=' . $time);
        $_SERVER['PHPUNIT_OPEN_SEARCH_HELPER_TIME'] = (string) $time;
        $_ENV['PHPUNIT_OPEN_SEARCH_HELPER_TIME'] = (string) $time;
    }
}
