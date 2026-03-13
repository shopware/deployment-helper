<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

use Symfony\Component\Yaml\Tag\TaggedValue;

class ConfigMerger
{
    /**
     * Deep merges the override config on top of the base config.
     *
     * Supports custom YAML tags:
     * - !reset: Clears the field. For maps/slices, replaces with the tagged value. For scalars, removes the field.
     * - !override: Fully replaces the field instead of deep-merging.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if ($value instanceof TaggedValue) {
                $base[$key] = self::resolveTaggedValue($value);

                continue;
            }

            if (\is_array($value) && isset($base[$key]) && \is_array($base[$key])) {
                if (array_is_list($value) && array_is_list($base[$key])) {
                    // Append lists
                    $base[$key] = array_merge($base[$key], $value);
                } else {
                    // Deep merge maps
                    $base[$key] = self::merge($base[$key], $value);
                }

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private static function resolveTaggedValue(TaggedValue $taggedValue): mixed
    {
        return $taggedValue->getValue();
    }
}
