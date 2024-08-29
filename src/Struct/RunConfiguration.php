<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

readonly class RunConfiguration
{
    public function __construct(
        public bool $skipThemeCompile = false,
        public bool $skipAssetsInstall = false,
        public ?float $timeout = 60,
    ) {
    }
}
