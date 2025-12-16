<?php

declare(strict_types=1);

namespace Shopware\Deployment;

use Composer\InstalledVersions;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Services\DotenvLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\Filesystem\Filesystem;

class Application extends SymfonyApplication
{
    private ContainerBuilder $container;
    public ?string $projectConfigFile = null;

    public function __construct()
    {
        parent::__construct('Shopware Deployment Helper', InstalledVersions::getPrettyVersion('shopware/deployment-helper') ?? 'development');
        $this->container = $this->createContainer();
        $this->setDispatcher($this->container->get('event_dispatcher'));
        $this->setCommandLoader($this->container->get('console.command_loader'));

        $this->getDefinition()->addOption(new InputOption('project-config', null, InputOption::VALUE_REQUIRED, 'Path to .shopware-project.yaml'));
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsCommand::class, function (ChildDefinition $definition): void {
            $definition->addTag('console.command');
        });

        $container->registerAttributeForAutoconfiguration(AsEventListener::class, function (ChildDefinition $definition): void {
            $definition->addTag('kernel.event_listener');
        });

        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->addCompilerPass(new RegisterListenersPass());

        $projectDir = $this->getProjectDir();
        $container->setParameter('kernel.project_dir', $projectDir);
        InstalledVersions::reload(include $projectDir . '/vendor/composer/installed.php');
        (new DotenvLoader($projectDir))->load();

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.php');
        $container->compile();
        $container->set(self::class, $this);

        if (EnvironmentHelper::hasVariable('DEV_MODE')) {
            (new Filesystem())->dumpFile(\dirname(__DIR__) . '/var/cache/container.xml', (new XmlDumper($container))->dump());
        }

        return $container;
    }

    /**
     * @codeCoverageIgnore
     */
    private function getProjectDir(): string
    {
        $root = EnvironmentHelper::getVariable('PROJECT_ROOT');
        if (\is_string($root) && $root !== '') {
            return $root;
        }

        $dir = __DIR__;
        while (!file_exists($dir . '/bin/console')) {
            if ($dir === '/') {
                throw new \RuntimeException('Could not find project root');
            }

            if ($dir === \dirname($dir)) {
                return __DIR__;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->projectConfigFile = $input->getParameterOption(['--project-config'], null, true);

        return parent::doRun($input, $output);
    }
}
