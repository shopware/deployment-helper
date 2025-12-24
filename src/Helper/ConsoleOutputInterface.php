<?php

declare(strict_types=1);

namespace Shopware\Deployment\Helper;

interface ConsoleOutputInterface
{
    public function writeStdout(string $message): void;

    public function writeStderr(string $message): void;
}
