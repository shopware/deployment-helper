<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;

class HookExecutor
{
    public const HOOK_PRE = 'pre';
    public const HOOK_POST = 'post';
    public const HOOK_PRE_INSTALL = 'preInstall';
    public const HOOK_POST_INSTALL = 'postInstall';
    public const HOOK_PRE_UPDATE = 'preUpdate';
    public const HOOK_POST_UPDATE = 'postUpdate';

    public function __construct(
        private readonly ProcessHelper $processHelper,
        private readonly ProjectConfiguration $configuration,
    ) {
    }

    /**
     * @param self::HOOK_* $name
     */
    public function execute(string $name): void
    {
        $steps = match ($name) {
            self::HOOK_PRE => $this->configuration->hooks->pre,
            self::HOOK_POST => $this->configuration->hooks->post,
            self::HOOK_PRE_INSTALL => $this->configuration->hooks->preInstall,
            self::HOOK_POST_INSTALL => $this->configuration->hooks->postInstall,
            self::HOOK_PRE_UPDATE => $this->configuration->hooks->preUpdate,
            self::HOOK_POST_UPDATE => $this->configuration->hooks->postUpdate,
            default => throw new \RuntimeException('Unknown hook name'),
        };

        foreach ($steps as $step) {
            if ($step->script === '') {
                continue;
            }

            $this->processHelper->runAndTail($step->script, $step->title);
        }
    }
}
