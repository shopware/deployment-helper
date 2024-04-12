<?php

declare(strict_types=1);

namespace Shopware\Deployment;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
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
        $this->setDispatcher($this->container->get('event_dispatcher'));
        $this->setCommandLoader($this->container->get('console.command_loader'));
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsCommand::class, function (ChildDefinition $definition) {
            $definition->addTag('console.command');
        });
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new \LogicException(sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->addCompilerPass(new RegisterListenersPass());

        $container->setParameter('kernel.project_dir', $this->getProjectDir());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.xml');
        $container->compile();

        return $container;
    }

    private function getProjectDir(): string
    {
        $dir = __DIR__;
        while (!file_exists($dir . '/bin/console')) {
            if ($dir === \dirname($dir)) {
                return __DIR__;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }
}
