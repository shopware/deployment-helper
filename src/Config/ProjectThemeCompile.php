<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

class ProjectThemeCompile
{
    public bool $parallel = false;

    public bool $forceThemeCompileSync = false;

    public ?int $workers = null;
}
