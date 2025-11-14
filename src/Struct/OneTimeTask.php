<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

readonly class OneTimeTask
{
    public const WHEN_FIRST = 'first';
    public const WHEN_LAST = 'last';

    public function __construct(
        public string $id,
        public string $script,
        public string $when = self::WHEN_LAST,
    ) {
    }
}
