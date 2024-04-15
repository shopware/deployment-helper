<?php declare(strict_types=1);

namespace Shopware\Deployment\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

/**
 * @codeCoverageIgnore
 */
class ProcessHelper
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * @param list<string> $args
     */
    public function run(array $args): void
    {
        $process = new PhpSubprocess($args, $this->projectDir);
        $process->setPty(true);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
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
    public function console(array $args): void
    {
        $this->run(['bin/console', '-n', ...$args]);
    }

    public function runAndTail(string $code): void
    {
        $process = new Process(['sh', '-c', $code], $this->projectDir);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . $code . ' failed');
        }
    }

    public function getPluginList(): string
    {
        return (new PhpSubprocess(['bin/console', 'plugin:list', '--json'], $this->projectDir))->mustRun()->getOutput();
    }
}
