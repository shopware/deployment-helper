<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;

readonly class DotenvLoader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function load(): void
    {
        if (!file_exists($this->projectDir . '/.env') && !file_exists($this->projectDir . '/.env.dist') && !file_exists($this->projectDir . '/.env.local.php')) {
            return;
        }

        (new Dotenv())->bootEnv($this->projectDir . '/.env');
    }
}
