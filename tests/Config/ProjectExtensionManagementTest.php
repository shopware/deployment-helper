<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectExtensionManagement;

#[CoversClass(ProjectExtensionManagement::class)]
class ProjectExtensionManagementTest extends TestCase
{
    private ProjectExtensionManagement $management;

    protected function setUp(): void
    {
        $this->management = new ProjectExtensionManagement();
    }

    public function testDefaultEnabled(): void
    {
        self::assertTrue($this->management->enabled);
    }

    public function testDefaultOverridesEmpty(): void
    {
        self::assertEmpty($this->management->overrides);
    }

    /**
     * @param array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}> $overrides
     */
    #[DataProvider('provideCanExtensionBeInstalledCases')]
    public function testCanExtensionBeInstalled(bool $enabled, array $overrides, string $extensionName, bool $expected): void
    {
        $this->management->enabled = $enabled;
        $this->management->overrides = $overrides;

        self::assertSame($expected, $this->management->canExtensionBeInstalled($extensionName));
    }

    /**
     * @return array<string, array{bool, array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}>, string, bool}>
     */
    public static function provideCanExtensionBeInstalledCases(): array
    {
        return [
            'disabled_management' => [false, [], 'test', false],
            'no_override' => [true, [], 'test', true],
            'ignore_state' => [true, ['test' => ['state' => 'ignore']], 'test', false],
            'remove_state' => [true, ['test' => ['state' => 'remove']], 'test', false],
            'inactive_state' => [true, ['test' => ['state' => 'inactive']], 'test', true],
            'unknown_extension' => [true, ['other' => ['state' => 'ignore']], 'test', true],
        ];
    }

    /**
     * @param array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}> $overrides
     */
    #[DataProvider('provideCanExtensionBeActivatedCases')]
    public function testCanExtensionBeActivated(bool $enabled, array $overrides, string $extensionName, bool $expected): void
    {
        $this->management->enabled = $enabled;
        $this->management->overrides = $overrides;

        self::assertSame($expected, $this->management->canExtensionBeActivated($extensionName));
    }

    /**
     * @return array<string, array{bool, array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}>, string, bool}>
     */
    public static function provideCanExtensionBeActivatedCases(): array
    {
        return [
            'disabled_management' => [false, [], 'test', false],
            'no_override' => [true, [], 'test', true],
            'ignore_state' => [true, ['test' => ['state' => 'ignore']], 'test', false],
            'remove_state' => [true, ['test' => ['state' => 'remove']], 'test', false],
            'inactive_state' => [true, ['test' => ['state' => 'inactive']], 'test', false],
            'unknown_extension' => [true, ['other' => ['state' => 'ignore']], 'test', true],
        ];
    }

    /**
     * @param array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}> $overrides
     */
    #[DataProvider('provideCanExtensionBeRemovedCases')]
    public function testCanExtensionBeRemoved(bool $enabled, array $overrides, string $extensionName, bool $expected): void
    {
        $this->management->enabled = $enabled;
        $this->management->overrides = $overrides;

        self::assertSame($expected, $this->management->canExtensionBeRemoved($extensionName));
    }

    /**
     * @return array<string, array{bool, array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}>, string, bool}>
     */
    public static function provideCanExtensionBeRemovedCases(): array
    {
        return [
            'disabled_management' => [false, [], 'test', false],
            'no_override' => [true, [], 'test', false],
            'ignore_state' => [true, ['test' => ['state' => 'ignore']], 'test', false],
            'remove_state' => [true, ['test' => ['state' => 'remove']], 'test', true],
            'inactive_state' => [true, ['test' => ['state' => 'inactive']], 'test', false],
            'unknown_extension' => [true, ['other' => ['state' => 'remove']], 'test', false],
        ];
    }

    /**
     * @param array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}> $overrides
     */
    #[DataProvider('provideCanExtensionBeDeactivatedCases')]
    public function testCanExtensionBeDeactivated(bool $enabled, array $overrides, string $extensionName, bool $expected): void
    {
        $this->management->enabled = $enabled;
        $this->management->overrides = $overrides;

        self::assertSame($expected, $this->management->canExtensionBeDeactivated($extensionName));
    }

    /**
     * @return array<string, array{bool, array<string, array{state: 'ignore'|'inactive'|'remove', keepUserData?: bool}>, string, bool}>
     */
    public static function provideCanExtensionBeDeactivatedCases(): array
    {
        return [
            'disabled_management' => [false, [], 'test', false],
            'no_override' => [true, [], 'test', false],
            'ignore_state' => [true, ['test' => ['state' => 'ignore']], 'test', false],
            'remove_state' => [true, ['test' => ['state' => 'remove']], 'test', false],
            'inactive_state' => [true, ['test' => ['state' => 'inactive']], 'test', true],
            'unknown_extension' => [true, ['other' => ['state' => 'inactive']], 'test', false],
        ];
    }

    public function testAllowedStatesConstant(): void
    {
        /** @var array<string> $expectedStates */
        $expectedStates = ['ignore', 'inactive', 'remove'];
        self::assertSame($expectedStates, ProjectExtensionManagement::ALLOWED_STATES);
    }
}
