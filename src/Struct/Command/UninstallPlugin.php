<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct\Command;

final class UninstallPlugin implements ConsoleCommand
{
    /**
     * @param list<string> $additionalParameters
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $keepUserData = false,
        public readonly array $additionalParameters = [],
    ) {
    }

    public function toArgs(): array
    {
        return [
            'plugin:uninstall',
            $this->name,
            ...($this->keepUserData ? ['--keep-user-data'] : []),
            ...$this->additionalParameters,
        ];
    }
}
