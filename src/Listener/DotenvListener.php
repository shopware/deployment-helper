<?php declare(strict_types=1);

namespace Shopware\Deployment\Listener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('console.command')]
readonly class DotenvListener
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    public function __invoke(ConsoleCommandEvent $event): void
    {
        if (!file_exists($this->projectDir . '/.env') && !file_exists($this->projectDir . '/.env.dist') && !file_exists($this->projectDir . '/.env.local.php')) {
            return;
        }

        (new Dotenv())->bootEnv($this->projectDir . '/.env');
    }
}
