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

    #[BackupGlobals(enabled: true)]
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
}
