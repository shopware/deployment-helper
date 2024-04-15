<?php

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

class HookExecutor
{
    public const PRE = 'pre';
    public const POST = 'post';
    public const PRE_INSTALL = 'preInstall';
    public const POST_INSTALL = 'postInstall';
    public const PRE_UPDATE = 'preUpdate';
    public const POST_UPDATE = 'postUpdate';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private ProjectConfiguration $configuration,
    ) {}

    /**
     * @param 'post'|'pre'|'preInstall'|'postInstall'|'preUpdate'|'postUpdate' $name
     */
    public function execute(string $name): void
    {
        $code = $this->configuration->hooks->{$name};

        if ($code === '') {
            return;
        }

        $this->run($code);
    }

    private function run(string $code): void
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
}
