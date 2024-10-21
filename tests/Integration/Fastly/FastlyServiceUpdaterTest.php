<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration\Fastly;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Integration\Fastly\FastlyAPIClient;
use Shopware\Deployment\Integration\Fastly\FastlyServiceUpdater;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(FastlyServiceUpdater::class)]
#[CoversClass(PostDeploy::class)]
class FastlyServiceUpdaterTest extends TestCase
{
    public function testDoesNothingWithoutConfigFolder(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->never())
            ->method('setApiKey');

        $updater = new FastlyServiceUpdater(__DIR__, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));
    }

    public function testDoesNothingWithoutFastlyApiToken(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->never())
            ->method('setApiKey');

        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('fastly-test-', true);

        $fs->mkdir($tmpDir . '/config/fastly');

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    #[Env('FASTLY_API_TOKEN', 'API_TOKEN')]
    #[Env('FASTLY_SERVICE_ID', 'SERVICE_ID')]
    public function testCreateSnippet(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('API_TOKEN');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('SERVICE_ID', 1)
            ->willReturn([]);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('createSnippet')
            ->with('SERVICE_ID', 0, 'shopware_deliver', 'deliver', 'TEST');

        $fs = new Filesystem();
        $tmpDir = $this->createProjectRoot();

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    #[Env('FASTLY_API_TOKEN', 'API_TOKEN')]
    #[Env('FASTLY_SERVICE_ID', 'SERVICE_ID')]
    public function testCreateSnippetWithPriority(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('API_TOKEN');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('SERVICE_ID', 1)
            ->willReturn([]);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('createSnippet')
            ->with('SERVICE_ID', 0, 'shopware_deliver_test', 'deliver', 'TEST', 5);

        $fs = new Filesystem();
        $tmpDir = $this->createProjectRoot('test.5.vcl');

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    #[Env('FASTLY_API_TOKEN', 'API_TOKEN')]
    #[Env('FASTLY_SERVICE_ID', 'SERVICE_ID')]
    public function testUpdateSnippet(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('API_TOKEN');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('SERVICE_ID', 1)
            ->willReturn([
                ['name' => 'shopware_deliver', 'priority' => 0, 'content' => 'OLD'],
            ]);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('updateSnippet')
            ->with('SERVICE_ID', 0, 'shopware_deliver', 'deliver', 'TEST');

        $fs = new Filesystem();
        $tmpDir = $this->createProjectRoot();

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    #[Env('FASTLY_API_TOKEN', 'API_TOKEN')]
    #[Env('FASTLY_SERVICE_ID', 'SERVICE_ID')]
    public function testContentNotChangedDoesNothing(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('API_TOKEN');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('SERVICE_ID', 1)
            ->willReturn([
                ['name' => 'shopware_deliver', 'priority' => 0, 'content' => 'TEST'],
            ]);

        $fastlyAPIClient
            ->expects($this->never())
            ->method('updateSnippet');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('createSnippet');

        $fs = new Filesystem();
        $tmpDir = $this->createProjectRoot();

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    #[Env('FASTLY_API_TOKEN', 'API_TOKEN')]
    #[Env('FASTLY_SERVICE_ID', 'SERVICE_ID')]
    public function testDoesNothingWithUnknownFiles(): void
    {
        $fs = new Filesystem();
        $tmpDir = $this->createProjectRoot('unknown.txt');

        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('API_TOKEN');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('listSnippets')
            ->with('SERVICE_ID', 1)
            ->willReturn([
                ['name' => 'shopware_deliver', 'priority' => 0, 'content' => 'TEST'],
            ]);

        $fastlyAPIClient
            ->expects($this->never())
            ->method('updateSnippet');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('createSnippet');

        $updater = new FastlyServiceUpdater($tmpDir, $fastlyAPIClient);

        $updater(new PostDeploy(new RunConfiguration(), new NullOutput()));

        $fs->remove($tmpDir);
    }

    private function createProjectRoot(string $fileName = 'default.vcl'): string
    {
        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('fastly-test-', true);

        $fs->mkdir($tmpDir . '/config/fastly');

        $fs->dumpFile($tmpDir . '/config/fastly/deliver/' . $fileName, 'TEST');

        return $tmpDir;
    }
}
