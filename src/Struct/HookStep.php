<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

readonly class HookStep
{
    public function __construct(
        public string $script,
        public string $title = '',
    ) {
    }
}
