<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\TestUtil;

use Shopware\Deployment\Services\SystemConfigHelper;

class StaticSystemConfigHelper extends SystemConfigHelper
{
    /**
     * @param array<string, string> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function get(string $key): ?string
    {
        return $this->config[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->config[$key] = $value;
    }
}
