<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Integration\PlatformSHSubscriber;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(PlatformSHSubscriber::class)]
class PlatformSHSubscriberTest extends TestCase
{
    public function testDoesNothing(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
            ->method('run');

        $subscriber = new PlatformSHSubscriber($processHelper, 'test');

        $subscriber(new PostDeploy(new RunConfiguration(), new NullOutput()));
    }

    #[Env('PLATFORM_ROUTES', '1')]
    public function testIsPlatformSH(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $subscriber = new PlatformSHSubscriber($processHelper, 'test');

        $output = $this->createMock(OutputInterface::class);
        $output->expects(static::once())->method('writeLn');

        if (\PHP_OS === 'Linux') {
            $processHelper
                 ->expects(static::once())
                 ->method('shell');
        }

        $subscriber(new PostDeploy(new RunConfiguration(), $output));
    }

    #[Env('PLATFORM_ROUTES', '1')]
    #[Env('PLATFORM_REGISTRY_NUMBER', '1')]
    public function testDedicatedWithLocalVarCache(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $subscriber = new PlatformSHSubscriber($processHelper, 'test');

        $output = $this->createMock(OutputInterface::class);
        $output->expects(static::once())->method('writeLn');

        if (\PHP_OS === 'Linux') {
            $processHelper->expects(static::exactly(2))->method('shell');
        } else {
            $processHelper->expects(static::once())->method('shell');
        }

        $processHelper->expects(static::once())->method('console')->with(['cache:clear']);

        $subscriber(new PostDeploy(new RunConfiguration(), $output));
    }
}
