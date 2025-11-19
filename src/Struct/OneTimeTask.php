<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

readonly class OneTimeTask
{
    public function __construct(
        public string $id,
        public string $script,
        public OneTimeTaskWhen $when = OneTimeTaskWhen::LAST,
    ) {
    }
}
