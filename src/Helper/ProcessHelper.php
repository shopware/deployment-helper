<?php

namespace Shopware\Deployment\Helper;

use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

class ProcessHelper
{
    /**
     * @param list<string> $args
     */
    public static function run(array $args): void
    {
        $process = new PhpSubprocess($args);
        $process->setPty(true);
        $process->run(function (string $type, string $buffer): void {
            if (Process::ERR === $type) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . implode(' ', $args) . ' failed');
        }
    }

    /**
     * @param list<string> $args
     */
    public static function console(array $args): void
    {
        self::run(['bin/console', '-n', ...$args]);
    }
}
