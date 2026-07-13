<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

use Shopware\Deployment\Struct\HookStep;

class ProjectHooks
{
    /**
     * @var list<HookStep>
     */
    public array $pre;

    /**
     * @var list<HookStep>
     */
    public array $post;

    /**
     * @var list<HookStep>
     */
    public array $preInstall;

    /**
     * @var list<HookStep>
     */
    public array $postInstall;

    /**
     * @var list<HookStep>
     */
    public array $preUpdate;

    /**
     * @var list<HookStep>
     */
    public array $postUpdate;

    /**
     * @param string|list<HookStep> $pre
     * @param string|list<HookStep> $post
     * @param string|list<HookStep> $preInstall
     * @param string|list<HookStep> $postInstall
     * @param string|list<HookStep> $preUpdate
     * @param string|list<HookStep> $postUpdate
     */
    public function __construct(
        string|array $pre = [],
        string|array $post = [],
        string|array $preInstall = [],
        string|array $postInstall = [],
        string|array $preUpdate = [],
        string|array $postUpdate = [],
    ) {
        $this->pre = self::normalize($pre);
        $this->post = self::normalize($post);
        $this->preInstall = self::normalize($preInstall);
        $this->postInstall = self::normalize($postInstall);
        $this->preUpdate = self::normalize($preUpdate);
        $this->postUpdate = self::normalize($postUpdate);
    }

    /**
     * @param string|list<HookStep> $value
     *
     * @return list<HookStep>
     */
    private static function normalize(string|array $value): array
    {
        if (\is_string($value)) {
            return $value === '' ? [] : [new HookStep($value)];
        }

        return $value;
    }
}
