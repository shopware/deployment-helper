<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct\Command;

interface ConsoleCommand
{
    /**
     * The console arguments to execute, e.g. ['plugin:install', 'MyPlugin', '--activate'].
     *
     * @return list<string>
     */
    public function toArgs(): array;
}
