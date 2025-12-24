<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Tests\TestUtil\StaticSystemConfigHelper;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(TrackingService::class)]
class TrackingServiceTest extends TestCase
{
    public function testTrackGeneratesAndPersistsIdWhenNotSet(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);
        $shopwareState->method('getCurrentVersion')->willReturn('6.6.0.0');

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->track('test_event');

        $id = $systemConfigHelper->get('core.deployment_helper.id');
        static::assertNotNull($id);
        static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
    }

    public function testTrackUsesExistingIdFromConfig(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $systemConfigHelper->set('core.deployment_helper.id', 'existing-id-123');
        $shopwareState = $this->createMock(ShopwareState::class);
        $shopwareState->method('getCurrentVersion')->willReturn('6.6.0.0');

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->track('test_event');

        static::assertSame('existing-id-123', $systemConfigHelper->get('core.deployment_helper.id'));
    }

    public function testPersistIdDoesNothingWhenNoIdGenerated(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->persistId();

        static::assertNull($systemConfigHelper->get('core.deployment_helper.id'));
    }

    #[Env('DO_NOT_TRACK')]
    #[DoesNotPerformAssertions]
    public function testTrackWithDoNotTrackEnv(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->track('test_event');
    }
}
