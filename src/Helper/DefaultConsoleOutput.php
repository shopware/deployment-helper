<?php

declare(strict_types=1);

namespace Shopware\Deployment\Helper;

class DefaultConsoleOutput implements ConsoleOutputInterface
{
    public function writeStdout(string $message): void
    {
        fwrite(\STDOUT, $message);
    }

    public function writeStderr(string $message): void
    {
        fwrite(\STDERR, $message);
    }
}
