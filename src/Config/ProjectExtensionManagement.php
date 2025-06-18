<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

/**
 * @phpstan-type ExtensionOverride array{state: self::LIFECYCLE_STATE_*, keepUserData?: bool}
 */
class ProjectExtensionManagement
{
    public const LIFECYCLE_STATE_INACTIVE = 'inactive';
    public const LIFECYCLE_STATE_REMOVE = 'remove';
    public const LIFECYCLE_STATE_IGNORE = 'ignore';

    public const ALLOWED_STATES = [self::LIFECYCLE_STATE_IGNORE, self::LIFECYCLE_STATE_INACTIVE, self::LIFECYCLE_STATE_REMOVE];

    public bool $enabled = true;

    /**
     * The following extensions should be force updated.
     *
     * @var string[]
     */
    public array $forceUpdates = [];

    /**
     * The following extensions should be inactive.
     *
     * @var array<string, ExtensionOverride>
     */
    public array $overrides = [];

    public function canExtensionBeInstalled(string $name): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $state = $this->getExtensionState($name);

        return !\in_array($state, [self::LIFECYCLE_STATE_REMOVE, self::LIFECYCLE_STATE_IGNORE], true);
    }

    public function shouldExtensionBeForceUpdated(string $name): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return \in_array($name, $this->forceUpdates, true);
    }

    public function canExtensionBeActivated(string $name): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $state = $this->getExtensionState($name);

        return !\in_array($state, [self::LIFECYCLE_STATE_INACTIVE, self::LIFECYCLE_STATE_REMOVE, self::LIFECYCLE_STATE_IGNORE], true);
    }

    public function canExtensionBeRemoved(string $name): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $state = $this->getExtensionState($name);

        return $state === self::LIFECYCLE_STATE_REMOVE;
    }

    public function canExtensionBeDeactivated(string $name): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->getExtensionState($name) === self::LIFECYCLE_STATE_INACTIVE;
    }

    /**
     * @return ProjectExtensionManagement::LIFECYCLE_STATE_*|null
     */
    private function getExtensionState(string $name): ?string
    {
        if (\array_key_exists($name, $this->overrides)) {
            return $this->overrides[$name]['state'];
        }

        return null;
    }
}
