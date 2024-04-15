<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Listener;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Listener\DotenvListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[CoversClass(DotenvListener::class)]
class DotenvListenerTest extends TestCase
{
    public function testNoFileDoesNothing(): void
    {
        $before = $_SERVER;
        $listener = new DotenvListener('/tmp');
        $listener(new ConsoleCommandEvent($this->createMock(Command::class), $this->createMock(InputInterface::class), $this->createMock(OutputInterface::class)));
        static::assertSame($before, $_SERVER);
    }

    #[BackupGlobals(enabled: true)]
    public function testFileExists(): void
    {
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('test', true));
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env', 'FOO=bar');

        $listener = new DotenvListener($tmpDir);
        $listener(new ConsoleCommandEvent($this->createMock(Command::class), $this->createMock(InputInterface::class), $this->createMock(OutputInterface::class)));
        static::assertArrayHasKey('FOO', $_SERVER);
    }
}
