<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;

class HookExecutor
{
    public const PRE = 'pre';
    public const POST = 'post';
    public const PRE_INSTALL = 'preInstall';
    public const POST_INSTALL = 'postInstall';
    public const PRE_UPDATE = 'preUpdate';
    public const POST_UPDATE = 'postUpdate';

    public function __construct(
        private ProcessHelper $processHelper,
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

        $this->processHelper->runAndTail($code);
    }
}
