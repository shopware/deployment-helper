<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

class ProjectConfiguration
{
    public ProjectHooks $hooks;

    public ProjectExtensionManagement $extensionManagement;

    public ProjectMaintenance $maintenance;

    /**
     * @var array<string, string>
     */
    public array $oneTimeTasks = [];

    public function __construct()
    {
        $this->hooks = new ProjectHooks();
        $this->extensionManagement = new ProjectExtensionManagement();
        $this->maintenance = new ProjectMaintenance();
    }

    public function isExtensionManaged(string $name): bool
    {
        if (!$this->extensionManagement->enabled) {
            return false;
        }

        if (\in_array($name, $this->extensionManagement->excluded, true)) {
            return false;
        }

        return true;
    }
}
