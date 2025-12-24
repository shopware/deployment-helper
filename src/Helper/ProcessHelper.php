<?php

declare(strict_types=1);

namespace Shopware\Deployment\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\InvalidArgumentException;
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
        private ?float $timeout = null,
        private readonly ConsoleOutputInterface $output = new DefaultConsoleOutput(),
    ) {
        $this->timeout = $this->validateTimeout($timeout ?? (float) EnvironmentHelper::getVariable('SHOPWARE_DEPLOYMENT_TIMEOUT', '60'));
    }

    /**
     * @param list<string> $command
     */
    public function shell(array $command): void
    {
        $process = new Process($command, $this->projectDir);
        $process->setTimeout($this->timeout);

        $startTime = $this->printPreStart($command);

        if ($this->output instanceof DefaultConsoleOutput && \function_exists('stream_isatty') && stream_isatty(\STDOUT)) {
            $process->setTty(true);
        }

        $process->run($this->output(...));

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . implode(' ', $command) . ' failed');
        }

        $this->printPostStart($command, $startTime);
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): void
    {
        $completeCmd = ['php', ...$args];

        $process = new PhpSubprocess($args, $this->projectDir);
        $process->setTimeout($this->timeout);

        $startTime = $this->printPreStart($completeCmd);

        if ($this->output instanceof DefaultConsoleOutput && \function_exists('stream_isatty') && stream_isatty(\STDOUT)) {
            $process->setTty(true);
        }

        $process->run($this->output(...));

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . implode(' ', $args) . ' failed');
        }

        $this->printPostStart($completeCmd, $startTime);
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
        $originalCode = $code;
        $code = $this->replaceVariables($code);

        $start = $this->printPreStart([$originalCode]);

        $process = new Process(['sh', '-c', $code], $this->projectDir);
        $process->setTimeout($this->timeout);

        if ($this->output instanceof DefaultConsoleOutput && \function_exists('stream_isatty') && stream_isatty(\STDOUT)) {
            $process->setTty(true);
        }

        $process->run($this->output(...));

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . $originalCode . ' failed');
        }

        $this->printPostStart([$originalCode], $start);
    }

    public function getPluginList(): string
    {
        return (new PhpSubprocess(['bin/console', 'plugin:list', '--json'], $this->projectDir))->mustRun()->getOutput();
    }

    /**
     * Sets the process timeout (max. runtime) in seconds.
     *
     * To disable the timeout, set this value to null.
     *
     * @return $this
     *
     * @throws InvalidArgumentException if the timeout is negative
     */
    public function setTimeout(?float $timeout): static
    {
        $this->timeout = $this->validateTimeout($timeout);

        return $this;
    }

    private function output(string $type, string $buffer): void
    {
        if ($type === Process::ERR) {
            $this->output->writeStderr($buffer);
        } else {
            $this->output->writeStdout($buffer);
        }
    }

    /**
     * @param array<string> $cmd
     */
    private function printPreStart(array $cmd): float
    {
        $cmdString = implode(' ', $cmd);
        $startTime = microtime(true);

        $this->output->writeStdout(\PHP_EOL);
        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout("============== [deployment-helper] ==============\n");
        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout(\sprintf("Start: %s\n", $cmdString));
        $this->output->writeStdout(\sprintf("Time limit: %s seconds\n", $this->timeout));
        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout(\PHP_EOL);

        return $startTime;
    }

    /**
     * @param array<string> $cmd
     */
    private function printPostStart(array $cmd, float $startTime): void
    {
        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout("============== [deployment-helper] ==============\n");
        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout(\sprintf("End: %s\n", implode(' ', $cmd)));
        $this->output->writeStdout(\sprintf(
            "> Time: %sms\n",
            number_format((microtime(true) - $startTime) * 1000, 2, '.', ''),
        ));

        $this->output->writeStdout("=================================================\n");
        $this->output->writeStdout(\PHP_EOL);
    }

    /**
     * Validates and returns the filtered timeout.
     *
     * @see Process::validateTimeout
     *
     * @throws InvalidArgumentException if the given timeout is a negative number
     */
    private function validateTimeout(?float $timeout): ?float
    {
        $timeout = (float) $timeout;

        if ($timeout === 0.0) {
            $timeout = null;
        } elseif ($timeout < 0) {
            throw new InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
        }

        return $timeout;
    }

    /**
     * Replaces placeholders in hook/script commands with actual values.
     *
     * Supported placeholders:
     * - %php.bin%: The path to the PHP binary that started the current process
     */
    private function replaceVariables(string $code): string
    {
        return str_replace('%php.bin%', escapeshellarg(\PHP_BINARY), $code);
    }
}
