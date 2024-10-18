<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Integration\Fastly\FastlyAPIClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fastly:snippet:remove',
    description: 'Remove a Fastly snippet'
)]
class FastlySnippetRemoveCommand extends Command
{
    public function __construct(private readonly FastlyAPIClient $fastlyAPIClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('snippetName', InputArgument::REQUIRED, 'The name of the snippet to remove');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiToken = EnvironmentHelper::getVariable('FASTLY_API_TOKEN', '');
        $serviceId = EnvironmentHelper::getVariable('FASTLY_SERVICE_ID', '');

        if ($apiToken === '' || $serviceId === '') {
            $output->writeln('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set.');

            return self::FAILURE;
        }

        $this->fastlyAPIClient->setApiKey($apiToken);

        $currentlyActiveVersion = $this->fastlyAPIClient->getCurrentlyActiveVersion($serviceId);

        $snippetName = $input->getArgument('snippetName');

        $newVersionId = $this->fastlyAPIClient->cloneServiceVersion($serviceId, $currentlyActiveVersion);

        $this->fastlyAPIClient->deleteSnippet($serviceId, $newVersionId, $snippetName);

        $this->fastlyAPIClient->activateServiceVersion($serviceId, $newVersionId);

        $output->writeln('Snippet removed.');

        return self::SUCCESS;
    }
}
