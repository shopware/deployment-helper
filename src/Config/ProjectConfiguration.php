<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

class ProjectConfiguration
{
    public ProjectHooks $hooks;

    public ProjectExtensionManagement $extensionManagement;

    public ProjectMaintenance $maintenance;

    public ProjectStaging $staging;

    public ProjectStore $store;

    /**
     * @var array<string, \Shopware\Deployment\Struct\OneTimeTask>
     */
    public array $oneTimeTasks = [];

    public bool $alwaysClearCache = false;

    public function __construct()
    {
        $this->hooks = new ProjectHooks();
        $this->extensionManagement = new ProjectExtensionManagement();
        $this->maintenance = new ProjectMaintenance();
        $this->staging = new ProjectStaging();
        $this->store = new ProjectStore();
    }
}
