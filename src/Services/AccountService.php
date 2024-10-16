<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AccountService
{
    public const CORE_STORE_LICENSE_HOST = 'core.store.licenseHost';
    public const CORE_STORE_SHOP_SECRET = 'core.store.shopSecret';

    private HttpClientInterface $client;

    public function __construct(private SystemConfigHelper $systemConfigHelper, private ProcessHelper $processHelper, ?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::createForBaseUri('https://api.shopware.com');
    }

    public function refresh(SymfonyStyle $output, string $shopwareVersion, string $licenseDomain): void
    {
        if (str_contains($shopwareVersion, 'dev')) {
            $shopwareVersion = '___VERSION___';
        }

        $changed = $this->setLicenseDomain($licenseDomain);

        if ($changed) {
            $output->info(\sprintf("Updated license domain to %s\n", $licenseDomain));
        }

        $email = EnvironmentHelper::getVariable('SHOPWARE_STORE_ACCOUNT_EMAIL', '');
        $password = EnvironmentHelper::getVariable('SHOPWARE_STORE_ACCOUNT_PASSWORD', '');

        if ($email === '' || $password === '') {
            $output->warning('No store account credentials found, skipping store account login verification and login if needed. Set SHOPWARE_STORE_ACCOUNT_EMAIL and SHOPWARE_STORE_ACCOUNT_PASSWORD to refresh the store account on deployment');
        } elseif ($this->refreshShopToken($shopwareVersion, $licenseDomain, $email, $password)) {
            $output->info('Refreshed global shop token to communicate to store.shopware.com');
            $changed = true;
        }

        if ($changed) {
            $this->processHelper->console(['cache:pool:invalidate-tags', '-p', 'cache.object', 'system-config']);
        }
    }

    private function setLicenseDomain(string $licenseDomain): bool
    {
        $existingRecord = $this->systemConfigHelper->get(self::CORE_STORE_LICENSE_HOST);

        if ($existingRecord === $licenseDomain) {
            return false;
        }

        $this->systemConfigHelper->set(self::CORE_STORE_LICENSE_HOST, $licenseDomain);

        return true;
    }

    private function refreshShopToken(string $shopwareVersion, string $licenseDomain, string $email, string $password): bool
    {
        $secret = $this->systemConfigHelper->get(self::CORE_STORE_SHOP_SECRET);
        if ($secret !== null && $this->isShopSecretStillValid($secret, $shopwareVersion, $licenseDomain)) {
            return false;
        }

        $response = $this->client->request('POST', '/swplatform/login', [
            'query' => [
                'shopwareVersion' => $shopwareVersion,
                'domain' => $licenseDomain,
                'language' => 'en-GB',
            ],
            'json' => [
                'shopwareId' => $email,
                'password' => $password,
                'shopwareUserId' => bin2hex(random_bytes(16)),
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['shopSecret']) || !\is_string($data['shopSecret'])) {
            throw new \RuntimeException('Got invalid response from Shopware API: ' . json_encode($data, \JSON_THROW_ON_ERROR));
        }

        $this->systemConfigHelper->set(self::CORE_STORE_SHOP_SECRET, $data['shopSecret']);

        return true;
    }

    private function isShopSecretStillValid(string $secret, string $shopwareVersion, string $licenseDomain): bool
    {
        $response = $this->client->request('POST', '/swplatform/pluginupdates', [
            'query' => [
                'shopwareVersion' => $shopwareVersion,
                'domain' => $licenseDomain,
                'language' => 'en-GB',
            ],
            'json' => [
                'plugins' => [],
            ],
            'headers' => [
                'X-Shopware-Shop-Secret' => $secret,
            ],
        ]);

        return $response->getStatusCode() === 200;
    }
}
