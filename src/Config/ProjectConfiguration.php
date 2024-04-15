<?php

namespace Shopware\Deployment\Config;

class ProjectConfiguration
{
    public ProjectHooks $hooks;

    public ProjectExtensionManagement $extensionManagement;

    /**
     * @var array<string, string>
     */
    public array $oneTimeTasks = [];

    public function __construct()
    {
        $this->hooks = new ProjectHooks();
        $this->extensionManagement = new ProjectExtensionManagement();
    }

    public function isExtensionManaged(string $name): bool
    {
        if (!$this->extensionManagement->enabled) {
            return false;
        }

        if (in_array($name, $this->extensionManagement->excluded, true)) {
            return false;
        }

        return true;
    }
}
