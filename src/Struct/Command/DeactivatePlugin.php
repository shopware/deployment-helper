<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct\Command;

final class DeactivatePlugin implements ConsoleCommand
{
    /**
     * @param list<string> $additionalParameters
     */
    public function __construct(
        public readonly string $name,
        public readonly array $additionalParameters = [],
    ) {
    }

    public function toArgs(): array
    {
        return ['plugin:deactivate', $this->name, ...$this->additionalParameters];
    }
}
