<?php

declare(strict_types=1);

namespace Shopware\Deployment\Command;

use Shopware\Deployment\Services\DotenvLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dump-env',
    description: 'Dump parsed environment variables as JSON',
    hidden: true,
)]
class DumpEnvCommand extends Command
{
    public function __construct(
        private readonly DotenvLoader $dotenvLoader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = json_encode(
            $this->dotenvLoader->parse(),
            \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );

        $output->writeln($json, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

        return Command::SUCCESS;
    }
}
