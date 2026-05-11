<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectStaging;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Integration\StagingSetupSubscriber;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\NullOutput;

#[CoversClass(StagingSetupSubscriber::class)]
class StagingSetupSubscriberTest extends TestCase
{
    private ProjectConfiguration&MockObject $projectConfiguration;
    private ProcessHelper&MockObject $processHelper;
    private StagingSetupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->projectConfiguration = $this->createMock(ProjectConfiguration::class);
        $this->processHelper = $this->createMock(ProcessHelper::class);
        $this->subscriber = new StagingSetupSubscriber($this->projectConfiguration, $this->processHelper);
    }

    public function testInvokeWithStagingEnabled(): void
    {
        $this->projectConfiguration->staging = new ProjectStaging();
        $this->projectConfiguration->staging->enabled = true;

        $this->processHelper
            ->expects($this->once())
            ->method('console')
            ->with(['system:setup:staging', '--no-interaction', '--force']);

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    public function testInvokeWithStagingDisabled(): void
    {
        $this->projectConfiguration->staging = new ProjectStaging();
        $this->projectConfiguration->staging->enabled = false;

        $this->processHelper
            ->expects($this->never())
            ->method('console');

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }
}
