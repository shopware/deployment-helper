<?php declare(strict_types=1);

namespace Shopware\Deployment\Struct;

class RunConfiguration
{
    public function __construct(public readonly bool $skipThemeCompile = false, public readonly bool $skipAssetInstall = false) {}
}
