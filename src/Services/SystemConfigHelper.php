<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\ProcessHelper;

class SystemConfigHelper
{
    public function __construct(private readonly ProcessHelper $processHelper)
    {
    }

    public function get(string $key): ?string
    {
        $data = json_decode($this->processHelper->consoleOutput(['system:config:get', $key, '--format=json']), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data) || !\array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];

        if (\is_array($value)) {
            throw new \UnexpectedValueException('Expected string, got array');
        }

        return (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $this->processHelper->console(['system:config:set', $key, $value]);
    }

    public function delete(string $key): void
    {
        $this->processHelper->console(['system:config:set', $key, 'null', '--json']);
    }
}
