<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\TestUtil;

use Shopware\Deployment\Helper\ConsoleOutputInterface;

class BufferedConsoleOutput implements ConsoleOutputInterface
{
    private string $stdout = '';
    private string $stderr = '';

    public function writeStdout(string $message): void
    {
        $this->stdout .= $message;
    }

    public function writeStderr(string $message): void
    {
        $this->stderr .= $message;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }
}
