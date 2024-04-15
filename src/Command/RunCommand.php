<?php declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\ShopwareState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('run', description: 'Run the deployment helper.')]
class RunCommand extends Command
{
    public function __construct(private ShopwareState $state, private InstallationManager $installationManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $projectDir */
        $projectDir = getcwd();

        $installed = $this->state->isInstalled();

        if ($installed) {
            $output->writeln('Shopware is installed');

            if ($this->state->getPreviousVersion() !== $this->state->getCurrentVersion()) {
                $output->writeln(sprintf('Updating Shopware from %s to %s', $this->state->getPreviousVersion(), $this->state->getCurrentVersion()));
                ProcessHelper::console(['system:update:finish']);
                $this->state->setVersion($this->state->getCurrentVersion());
            }

            ProcessHelper::console(['plugin:refresh']);

            PluginHelper::installPlugins($projectDir);
            PluginHelper::updatePlugins($projectDir);

            ProcessHelper::console(['theme:compile', '--active-only']);
        } else {
            $this->installationManager->run($output);
        }

        return Command::SUCCESS;
    }
}
