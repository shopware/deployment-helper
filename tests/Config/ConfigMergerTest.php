<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ConfigMerger;
use Symfony\Component\Yaml\Tag\TaggedValue;

#[CoversClass(ConfigMerger::class)]
class ConfigMergerTest extends TestCase
{
    public function testScalarOverride(): void
    {
        $base = ['url' => 'https://prod.example.com', 'name' => 'prod'];
        $override = ['url' => 'https://local.example.com'];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame('https://local.example.com', $result['url']);
        static::assertSame('prod', $result['name']);
    }

    public function testDeepMergeMaps(): void
    {
        $base = ['deployment' => ['hooks' => ['pre' => 'echo base', 'post' => 'echo post']]];
        $override = ['deployment' => ['hooks' => ['pre' => 'echo override']]];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame('echo override', $result['deployment']['hooks']['pre']);
        static::assertSame('echo post', $result['deployment']['hooks']['post']);
    }

    public function testAppendLists(): void
    {
        $base = ['items' => ['a', 'b']];
        $override = ['items' => ['c']];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['a', 'b', 'c'], $result['items']);
    }

    public function testResetList(): void
    {
        $base = ['items' => ['a', 'b', 'c']];
        $override = ['items' => new TaggedValue('reset', ['x'])];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['x'], $result['items']);
    }

    public function testResetEmptyList(): void
    {
        $base = ['items' => ['a', 'b', 'c']];
        $override = ['items' => new TaggedValue('reset', [])];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame([], $result['items']);
    }

    public function testResetMap(): void
    {
        $base = ['config' => ['key1' => 'val1', 'key2' => 'val2']];
        $override = ['config' => new TaggedValue('reset', ['key3' => 'val3'])];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['key3' => 'val3'], $result['config']);
    }

    public function testResetScalarRemovesField(): void
    {
        $base = ['url' => 'https://prod.example.com', 'name' => 'prod'];
        $override = ['url' => new TaggedValue('reset', null)];

        $result = ConfigMerger::merge($base, $override);

        static::assertNull($result['url']);
        static::assertSame('prod', $result['name']);
    }

    public function testOverrideMap(): void
    {
        $base = ['deployment' => ['hooks' => ['pre' => 'echo base', 'post' => 'echo post']]];
        $override = ['deployment' => ['hooks' => new TaggedValue('override', ['pre' => 'echo only-this'])]];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['pre' => 'echo only-this'], $result['deployment']['hooks']);
    }

    public function testOverrideList(): void
    {
        $base = ['items' => ['a', 'b', 'c']];
        $override = ['items' => new TaggedValue('override', ['x'])];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['x'], $result['items']);
    }

    public function testNewKeysAdded(): void
    {
        $base = ['existing' => 'value'];
        $override = ['new_key' => 'new_value'];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame('value', $result['existing']);
        static::assertSame('new_value', $result['new_key']);
    }

    public function testNestedTaggedValue(): void
    {
        $base = [
            'deployment' => [
                'extension-management' => [
                    'exclude' => ['Name', 'OtherPlugin'],
                    'enabled' => true,
                ],
            ],
        ];

        $override = [
            'deployment' => [
                'extension-management' => [
                    'exclude' => new TaggedValue('reset', ['OnlyThisPlugin']),
                ],
            ],
        ];

        $result = ConfigMerger::merge($base, $override);

        static::assertSame(['OnlyThisPlugin'], $result['deployment']['extension-management']['exclude']);
        static::assertTrue($result['deployment']['extension-management']['enabled']);
    }
}
