<?php declare(strict_types=1);

namespace Shopware\Deployment\Config;

class ProjectExtensionManagement
{
    public bool $enabled = true;

    /**
     * @var array<string>
     */
    public array $excluded = [];
}
