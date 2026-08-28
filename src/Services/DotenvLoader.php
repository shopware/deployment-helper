<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Helper\EnvironmentHelper;
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
        if (!$this->hasEnvFiles()) {
            return;
        }

        (new Dotenv())->bootEnv($this->projectDir . '/.env');
    }

    /**
     * Parses the Symfony dotenv file cascade and returns the resolved values.
     *
     * Existing process environment variables are not included unless they are
     * also defined in a dotenv file. The current $_ENV / $_SERVER state is
     * restored afterwards.
     *
     * @return array<string, string>
     */
    public function parse(): array
    {
        if (!$this->hasEnvFiles()) {
            return [];
        }

        $server = $_SERVER;
        $env = $_ENV;
        unset($_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_VARS']);

        try {
            (new Dotenv())->bootEnv($this->projectDir . '/.env', overrideExistingVars: true);

            $names = EnvironmentHelper::getVariable('SYMFONY_DOTENV_VARS', '');
            $values = [];

            foreach (explode(',', $names) as $name) {
                if ($name === '') {
                    continue;
                }

                $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
                if (\is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value)) {
                    $values[$name] = (string) $value;
                }
            }

            ksort($values);

            return $values;
        } finally {
            $_SERVER = $server;
            $_ENV = $env;
        }
    }

    private function hasEnvFiles(): bool
    {
        return file_exists($this->projectDir . '/.env')
            || file_exists($this->projectDir . '/.env.dist')
            || file_exists($this->projectDir . '/.env.local.php');
    }
}
