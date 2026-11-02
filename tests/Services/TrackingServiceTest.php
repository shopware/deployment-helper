<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Tests\TestUtil\StaticSystemConfigHelper;
use Symfony\Component\Console\Output\BufferedOutput;
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

        $id = $systemConfigHelper->get(TrackingService::TELEMETRY_ID);
        static::assertNotNull($id);
        static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
    }

    public function testTrackUsesExistingIdFromConfig(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $systemConfigHelper->set(TrackingService::TELEMETRY_ID, 'existing-id-123');
        $shopwareState = $this->createMock(ShopwareState::class);
        $shopwareState->method('getCurrentVersion')->willReturn('6.6.0.0');

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->track('test_event');

        static::assertSame('existing-id-123', $systemConfigHelper->get(TrackingService::TELEMETRY_ID));
    }

    public function testPersistIdDoesNothingWhenNoIdGenerated(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->persistId();

        static::assertNull($systemConfigHelper->get(TrackingService::TELEMETRY_ID));
    }

    public function testTrackMigratesLegacyKey(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $systemConfigHelper->set(TrackingService::LEGACY_DEPLOYMENT_HELPER_ID, 'legacy-id-456');
        $shopwareState = $this->createMock(ShopwareState::class);
        $shopwareState->method('getCurrentVersion')->willReturn('6.6.0.0');

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $service->track('test_event');

        static::assertSame('legacy-id-456', $systemConfigHelper->get(TrackingService::TELEMETRY_ID));
        static::assertNull($systemConfigHelper->get(TrackingService::LEGACY_DEPLOYMENT_HELPER_ID));
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

    public function testShowHintPrintsTelemetryNotice(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $output = new BufferedOutput();
        $service->showHint($output);

        $content = $output->fetch();
        static::assertStringContainsString('Shopware collects anonymous telemetry', $content);
        static::assertStringContainsString('https://developer.shopware.com/docs/resources/references/telemetry.html', $content);
    }

    #[Env('DO_NOT_TRACK')]
    public function testShowHintIsSuppressedByDoNotTrack(): void
    {
        $systemConfigHelper = new StaticSystemConfigHelper();
        $shopwareState = $this->createMock(ShopwareState::class);

        $service = new TrackingService($systemConfigHelper, $shopwareState);
        $output = new BufferedOutput();
        $service->showHint($output);

        static::assertSame('', $output->fetch());
    }

    /**
     * @param array<string, string> $environment
     */
    #[BackupGlobals(true)]
    #[DataProvider('provideDetectRuntimeCases')]
    public function testDetectRuntime(array $environment, bool $dockerEnvFileExists, string $expected): void
    {
        foreach (['CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'BUILDKITE', 'CIRCLECI', 'DRONE', 'TEAMCITY_VERSION', 'TF_BUILD', 'PLATFORM_ROUTES', 'KUBERNETES_SERVICE_HOST', 'container'] as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }

        foreach ($environment as $name => $value) {
            $_SERVER[$name] = $value;
        }

        // Any existing file stands in for /.dockerenv
        $dockerEnvFile = $dockerEnvFileExists ? __FILE__ : __DIR__ . '/missing-dockerenv';

        static::assertSame($expected, TrackingService::detectRuntime($dockerEnvFile));
    }

    /**
     * @return array<string, array{array<string, string>, bool, string}>
     */
    public static function provideDetectRuntimeCases(): array
    {
        return [
            'plain host' => [[], false, 'cli'],
            'CI=true' => [['CI' => 'true'], false, 'ci'],
            'CI=false' => [['CI' => 'false'], false, 'cli'],
            'Jenkins without CI' => [['JENKINS_URL' => 'https://jenkins.example.com'], false, 'ci'],
            'Platform.sh' => [['PLATFORM_ROUTES' => 'e30='], false, 'platformsh'],
            'Kubernetes' => [['KUBERNETES_SERVICE_HOST' => '10.0.0.1'], false, 'kubernetes'],
            'Docker' => [[], true, 'docker'],
            'Podman' => [['container' => 'podman'], false, 'docker'],
            'CI job in a container' => [['CI' => 'true'], true, 'ci'],
            'Kubernetes pod with a Docker marker' => [['KUBERNETES_SERVICE_HOST' => '10.0.0.1'], true, 'kubernetes'],
        ];
    }
}
