<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct\Command;

/**
 * Installs one or more plugins in a single `plugin:install` call. Multiple plugins are only
 * batched together when none of them depend on another plugin, so the install order is irrelevant.
 */
final class InstallPlugins implements ConsoleCommand
{
    /**
     * @param list<string> $names
     * @param list<string> $additionalParameters
     */
    public function __construct(
        public readonly array $names,
        public readonly bool $activate = false,
        public readonly array $additionalParameters = [],
    ) {
    }

    public function toArgs(): array
    {
        return [
            'plugin:install',
            ...$this->names,
            ...($this->activate ? ['--activate'] : []),
            ...$this->additionalParameters,
        ];
    }
}
