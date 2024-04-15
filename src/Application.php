<?php

declare(strict_types=1);

namespace Shopware\Deployment;

use Composer\InstalledVersions;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

class Application extends SymfonyApplication
{
    private ContainerBuilder $container;

    public function __construct()
    {
        parent::__construct('Shopware Deployment Helper', '__VERSION__');
        $this->container = $this->createContainer();
        // @phpstan-ignore-next-line
        $this->setDispatcher($this->container->get('event_dispatcher'));
        // @phpstan-ignore-next-line
        $this->setCommandLoader($this->container->get('console.command_loader'));
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

        // @phpstan-ignore-next-line
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });

        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->addCompilerPass(new RegisterListenersPass());

        $projectDir = $this->getProjectDir();
        $container->setParameter('kernel.project_dir', $projectDir);
        InstalledVersions::reload(include $projectDir . '/vendor/composer/installed.php');

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.xml');
        $container->compile();

        return $container;
    }

    /**
     * @codeCoverageIgnore
     */
    private function getProjectDir(): string
    {
        if ($root = EnvironmentHelper::getVariable('PROJECT_ROOT')) {
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
}
