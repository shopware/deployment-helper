<?php

declare(strict_types=1);

use Shopware\Deployment\Application;
use Shopware\Deployment\Config\ConfigFactory;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\DependencyInjection\MySQLFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->set('event_dispatcher', Symfony\Component\EventDispatcher\EventDispatcher::class);
    $services->alias(EventDispatcherInterface::class, 'event_dispatcher');

    $services->set(Doctrine\DBAL\Connection::class)
        ->factory([MySQLFactory::class, 'createAndRetry']);

    $services->load('Shopware\\Deployment\\', '../../')
        ->exclude('../../{Application.php,ApplicationOutput.php,Struct,Resources}');

    $services->set(Application::class)
        ->synthetic();

    $services->set(ProjectConfiguration::class)
        ->factory([ConfigFactory::class, 'create'])
        ->args([
            '%kernel.project_dir%',
            service(Application::class),
        ]);
};
