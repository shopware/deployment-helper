<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration\Fastly;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Integration\Fastly\FastlyAPIClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(FastlyAPIClient::class)]
class FastlyAPIClientTest extends TestCase
{
    public function testListSnippets(): void
    {
        $expectedSnippets = [
            ['name' => 'snippet1', 'type' => 'recv', 'content' => 'content1', 'priority' => 10, 'updated_at' => '2023-01-01'],
            ['name' => 'snippet2', 'type' => 'recv', 'content' => 'content2', 'priority' => 20, 'updated_at' => '2023-01-02'],
        ];

        $client = new FastlyAPIClient(new MockHttpClient([
            new MockResponse(json_encode($expectedSnippets, \JSON_THROW_ON_ERROR)),
        ]));

        $client->setApiKey('test_api_key');

        $snippets = $client->listSnippets('service123', 1);

        static::assertEquals($expectedSnippets, $snippets);
    }

    public function testCreateSnippet(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('POST', '/service/service123/version/1/snippet', [
                'json' => [
                    'name' => 'new_snippet',
                    'type' => 'recv',
                    'content' => 'content',
                    'dynamic' => 0,
                    'priority' => 10,
                ],
            ]);

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api_key');

        $client->createSnippet('service123', 1, 'new_snippet', 'recv', 'content', 10);
    }

    public function testUpdateSnippet(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('PUT', '/service/service123/version/1/snippet/snippet1', [
                'json' => [
                    'type' => 'recv',
                    'content' => 'new_content',
                    'priority' => 20,
                ],
            ]);

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api_key');

        $client->updateSnippet('service123', 1, 'snippet1', 'recv', 'new_content', 20);
    }

    public function testDeleteSnippet(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('DELETE', '/service/service123/version/1/snippet/snippet1');

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api_key');

        $client->deleteSnippet('service123', 1, 'snippet1');
    }

    public function testCloneLive(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['number' => 2]);

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('PUT', '/service/service123/version/1/clone')
            ->willReturn($response);

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api');

        static::assertSame(2, $client->cloneServiceVersion('service123', 1));
    }

    public function testActivateVersion(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('PUT', '/service/service123/version/2/activate');

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api');

        $client->activateServiceVersion('service123', 2);
    }

    public function testGetCurrentActiveVersion(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['number' => 1]);

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('GET', '/service/service123/version/active')
            ->willReturn($response);

        $httpClientMock->method('withOptions')->willReturn($httpClientMock);

        $client = new FastlyAPIClient($httpClientMock);

        $client->setApiKey('test_api');

        static::assertSame(1, $client->getCurrentlyActiveVersion('service123'));
    }
}
