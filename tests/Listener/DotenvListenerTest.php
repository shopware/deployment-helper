<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Listener;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\DotenvLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[CoversClass(DotenvLoader::class)]
class DotenvListenerTest extends TestCase
{
    public function testNoFileDoesNothing(): void
    {
        $before = $_SERVER;
        $listener = new DotenvLoader('/tmp');
        $listener->load();
        static::assertSame($before, $_SERVER);
    }

    #[BackupGlobals(true)]
    public function testFileExists(): void
    {
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('test', true));
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env', 'FOO=bar');

        $listener = new DotenvLoader($tmpDir);
        $listener->load();
        static::assertArrayHasKey('FOO', $_SERVER);
    }

    public function testParseWithoutFilesReturnsEmptyArray(): void
    {
        $loader = new DotenvLoader('/tmp');

        static::assertSame([], $loader->parse());
    }

    #[BackupGlobals(true)]
    public function testParseReturnsResolvedDotenvValues(): void
    {
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('test', true));
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env', "FOO=bar\nBAZ=\${FOO}\nQUX=https://example.com/path");

        $_ENV['FOO'] = 'from-process';
        $_SERVER['FOO'] = 'from-process';

        $beforeServer = $_SERVER;
        $beforeEnv = $_ENV;

        $loader = new DotenvLoader($tmpDir);
        $values = $loader->parse();

        static::assertSame('bar', $values['FOO']);
        static::assertSame('bar', $values['BAZ']);
        static::assertSame('https://example.com/path', $values['QUX']);
        static::assertSame($beforeServer, $_SERVER);
        static::assertSame($beforeEnv, $_ENV);
    }

    #[BackupGlobals(true)]
    public function testParsePrefersLocalOverride(): void
    {
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('test', true));
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env', "APP_ENV=dev\nFOO=from-env");
        $fs->dumpFile($tmpDir . '/.env.local', 'FOO=from-local');

        $loader = new DotenvLoader($tmpDir);
        $values = $loader->parse();

        static::assertSame('from-local', $values['FOO']);
    }

    #[BackupGlobals(true)]
    public function testParseReadsEnvDistWhenEnvIsMissing(): void
    {
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('test', true));
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env.dist', 'FOO=from-dist');

        $loader = new DotenvLoader($tmpDir);
        $values = $loader->parse();

        static::assertSame('from-dist', $values['FOO']);
    }
}
