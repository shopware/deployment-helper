<?php

declare(strict_types=1);

namespace Shopware\Deployment\Helper;

readonly class EnvironmentHelper
{
    /**
     * Reads an env var first from $_SERVER then from $_ENV super globals
     * The caller needs to take care of casting the return value to the appropriate type.
     */
    public static function getVariable(string $key, ?string $default = null): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;

        return $value !== null ? (string) $value : $default;
    }

    public static function hasVariable(string $key): bool
    {
        return \array_key_exists($key, $_SERVER) || \array_key_exists($key, $_ENV);
    }
}
