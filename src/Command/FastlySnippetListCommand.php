<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Integration\Fastly\FastlyAPIClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fastly:snippet:list',
    description: 'List all Fastly snippets'
)]
class FastlySnippetListCommand extends Command
{
    public function __construct(private readonly FastlyAPIClient $fastlyAPIClient)
    {
        parent::__construct();
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

        $snippets = $this->fastlyAPIClient->listSnippets($serviceId, $currentlyActiveVersion);

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'Priority', 'Last change']);

        foreach ($snippets as $snippet) {
            $table->addRow([$snippet['name'], $snippet['type'], $snippet['priority'], $snippet['updated_at']]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
