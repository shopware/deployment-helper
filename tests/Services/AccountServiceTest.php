<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AccountService;
use Shopware\Deployment\Tests\TestUtil\StaticSystemConfigHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testUpdateOnlyLicenseDomain(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output
            ->expects($this->once())
            ->method('info')
            ->with(static::stringContains('Updated license domain to example.com'));

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console');

        $service = new AccountService($systemConfigHelper, $processHelper, $this->createMock(HttpClientInterface::class));
        $service->refresh($output, '6.4.0-dev', 'example.com');
    }

    public function testSameDomainDoesNothing(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('info');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $this->createMock(HttpClientInterface::class));
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
    }

    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_EMAIL', value: 'test@exaple.com')]
    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_PASSWORD', value: 'password')]
    public function testShopSecretAdd(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->once())->method('info')->with(static::stringContains('Refreshed global shop token to communicate to store.shopware.com'));

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"shopSecret":"new-shop-secret"}')
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');

        static::assertSame('new-shop-secret', $systemConfigHelper->get(AccountService::CORE_STORE_SHOP_SECRET));
    }

    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_EMAIL', value: 'test@exaple.com')]
    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_PASSWORD', value: 'password')]
    public function testShopSecretExistsNoUpdateRequired(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('info');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
            AccountService::CORE_STORE_SHOP_SECRET => 'new-shop',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{}')
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
    }

    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_EMAIL', value: 'test@exaple.com')]
    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_PASSWORD', value: 'password')]
    public function testShopSecretExistsButWrong(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->once())->method('info')->with(static::stringContains('Refreshed global shop token to communicate to store.shopware.com'));

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
            AccountService::CORE_STORE_SHOP_SECRET => 'old',
        ]);

        $mockHttpClient = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 401]),
            new MockResponse('{"shopSecret":"new-shop"}'),
        ]);

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');

        static::assertSame('new-shop', $systemConfigHelper->get(AccountService::CORE_STORE_SHOP_SECRET));
    }

    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_EMAIL', value: 'test@exaple.com')]
    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_PASSWORD', value: 'password')]
    public function testCredentialsWrong(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('info');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"message": "Invalid credentials"}', ['http_code' => 401])
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $this->expectException(ClientException::class);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
    }

    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_EMAIL', value: 'test@exaple.com')]
    #[Env(name: 'SHOPWARE_STORE_ACCOUNT_PASSWORD', value: 'password')]
    public function testLoginPayloadChanged(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('info');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"message": "Invalid credentials"}', ['http_code' => 200])
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Got invalid response from Shopware API: {"message":"Invalid credentials"}');
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    public function testStaticShopSecretNotSetAsInvalid(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->once())->method('warning');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"message": "Invalid credentials"}', ['http_code' => 500])
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    public function testStaticShopSecret(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('warning');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"message": "Invalid credentials"}', ['http_code' => 200])
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
        static::assertSame('some-key', $systemConfigHelper->get(AccountService::CORE_STORE_SHOP_SECRET));
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    public function testStaticShopSecretSameKeyDoesNothing(): void
    {
        $output = $this->createMock(SymfonyStyle::class);
        $output->expects($this->never())->method('warning');

        $systemConfigHelper = new StaticSystemConfigHelper([
            AccountService::CORE_STORE_LICENSE_HOST => 'old.example.com',
            AccountService::CORE_STORE_SHOP_SECRET => 'some-key',
        ]);

        $mockHttpClient = new MockHttpClient(
            new MockResponse('{"message": "Invalid credentials"}', ['http_code' => 200])
        );

        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($output, '6.4.0-dev', 'old.example.com');
        static::assertSame('some-key', $systemConfigHelper->get(AccountService::CORE_STORE_SHOP_SECRET));
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    public function testStoreApiUrlDefaultsToShopwareApi(): void
    {
        static::assertSame('api.shopware.com', $this->requestedHostFor([]));
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    #[Env('SHOPWARE_STORE_API_URI', 'https://env.example.com')]
    public function testStoreApiUrlFromEnvironment(): void
    {
        static::assertSame('env.example.com', $this->requestedHostFor([]));
    }

    #[Env('SHOPWARE_STORE_SHOP_SECRET', 'some-key')]
    #[Env('SHOPWARE_STORE_API_URI', 'https://env.example.com')]
    public function testStoreApiUrlFromSystemConfigWinsOverEnvironment(): void
    {
        static::assertSame(
            'config.example.com',
            $this->requestedHostFor([AccountService::CORE_STORE_API_URI => 'https://config.example.com']),
        );
    }

    /**
     * @param array<string, string> $config
     */
    private function requestedHostFor(array $config): string
    {
        $url = null;
        $mockHttpClient = new MockHttpClient(static function (string $method, string $requestUrl) use (&$url): MockResponse {
            $url = $requestUrl;

            return new MockResponse('{}', ['http_code' => 500]);
        });

        $systemConfigHelper = new StaticSystemConfigHelper($config + [AccountService::CORE_STORE_LICENSE_HOST => 'example.com']);
        $service = new AccountService($systemConfigHelper, $this->createMock(ProcessHelper::class), $mockHttpClient);
        $service->refresh($this->createMock(SymfonyStyle::class), '6.4.0-dev', 'example.com');

        static::assertIsString($url);

        return (string) parse_url($url, \PHP_URL_HOST);
    }
}
