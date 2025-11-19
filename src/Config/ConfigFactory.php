<?php

declare(strict_types=1);

namespace Shopware\Deployment\Config;

use Shopware\Deployment\Application;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Struct\OneTimeTask;
use Shopware\Deployment\Struct\OneTimeTaskWhen;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class ConfigFactory
{
    public static function create(string $projectDir, Application $application): ProjectConfiguration
    {
        $file = EnvironmentHelper::getVariable('SHOPWARE_PROJECT_CONFIG_FILE', $application->projectConfigFile);

        if ($file === null) {
            $file = Path::join($projectDir, '.shopware-project.yml');

            if (!file_exists($file)) {
                $file = Path::join($projectDir, '.shopware-project.yaml');
            }
        } else {
            // Handle relative paths by joining with project directory
            if (!Path::isAbsolute($file)) {
                $file = Path::join($projectDir, $file);
            }
        }

        if (!file_exists($file)) {
            return self::fillLicenseDomain(new ProjectConfiguration());
        }

        $projectConfiguration = new ProjectConfiguration();
        $config = Yaml::parseFile($file);

        if (isset($config['deployment']) && \is_array($config['deployment'])) {
            self::fillConfig($projectConfiguration, $config['deployment']);
        }

        return self::fillLicenseDomain($projectConfiguration);
    }

    /**
     * @param array<string, mixed> $deployment
     */
    private static function fillConfig(ProjectConfiguration $projectConfiguration, array $deployment): void
    {
        if (isset($deployment['maintenance']) && \is_array($deployment['maintenance'])) {
            if (isset($deployment['maintenance']['enabled']) && \is_bool($deployment['maintenance']['enabled'])) {
                $projectConfiguration->maintenance->enabled = $deployment['maintenance']['enabled'];
            }
        }

        if (isset($deployment['cache']) && \is_array($deployment['cache'])) {
            if (isset($deployment['cache']['always_clear']) && \is_bool($deployment['cache']['always_clear'])) {
                $projectConfiguration->alwaysClearCache = $deployment['cache']['always_clear'];
            }
        }

        if (isset($deployment['hooks']) && \is_array($deployment['hooks'])) {
            self::fillHooks($projectConfiguration->hooks, $deployment['hooks']);
        }

        if (isset($deployment['extension-management']) && \is_array($deployment['extension-management'])) {
            self::fillExtensionManagement($projectConfiguration->extensionManagement, $deployment['extension-management']);
        }

        if (isset($deployment['store']) && \is_array($deployment['store'])) {
            if (isset($deployment['store']['license-domain']) && \is_string($deployment['store']['license-domain'])) {
                $projectConfiguration->store->licenseDomain = $deployment['store']['license-domain'];
            }
        }

        if (isset($deployment['one-time-tasks']) && \is_array($deployment['one-time-tasks'])) {
            foreach ($deployment['one-time-tasks'] as $task) {
                if (isset($task['id'], $task['script']) && \is_string($task['id']) && \is_string($task['script'])) {
                    $when = OneTimeTaskWhen::LAST;
                    if (isset($task['when']) && \is_string($task['when'])) {
                        $when = OneTimeTaskWhen::from($task['when']);
                    }

                    $projectConfiguration->oneTimeTasks[$task['id']] = new OneTimeTask(
                        $task['id'],
                        $task['script'],
                        $when
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function fillHooks(ProjectHooks $hooks, array $config): void
    {
        if (isset($config['pre']) && \is_string($config['pre'])) {
            $hooks->pre = $config['pre'];
        }

        if (isset($config['post']) && \is_string($config['post'])) {
            $hooks->post = $config['post'];
        }

        if (isset($config['pre-install']) && \is_string($config['pre-install'])) {
            $hooks->preInstall = $config['pre-install'];
        }

        if (isset($config['post-install']) && \is_string($config['post-install'])) {
            $hooks->postInstall = $config['post-install'];
        }

        if (isset($config['pre-update']) && \is_string($config['pre-update'])) {
            $hooks->preUpdate = $config['pre-update'];
        }

        if (isset($config['post-update']) && \is_string($config['post-update'])) {
            $hooks->postUpdate = $config['post-update'];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fillExtensionManagement(ProjectExtensionManagement $extensionManagement, array $config): void
    {
        if (isset($config['enabled']) && \is_bool($config['enabled'])) {
            $extensionManagement->enabled = $config['enabled'];
        }

        if (isset($config['exclude']) && \is_array($config['exclude'])) {
            foreach ($config['exclude'] as $excludeExtension) {
                $extensionManagement->overrides[(string) $excludeExtension] = ['state' => 'ignore'];
            }
        }

        if (isset($config['forceUpdates']) && \is_array($config['forceUpdates'])) {
            @trigger_error('The config key "forceUpdates" is deprecated, use "force-update" instead.', \E_USER_DEPRECATED);
            foreach ($config['forceUpdates'] as $forceUpdateExtension) {
                $extensionManagement->forceUpdates[] = (string) $forceUpdateExtension;
            }
        }

        if (isset($config['force-update']) && \is_array($config['force-update'])) {
            foreach ($config['force-update'] as $forceUpdateExtension) {
                $extensionManagement->forceUpdates[] = (string) $forceUpdateExtension;
            }
        }

        if (isset($config['overrides']) && \is_array($config['overrides'])) {
            foreach ($config['overrides'] as $extension => $override) {
                if (isset($override['state']) && \is_string($override['state']) && \in_array($override['state'], ProjectExtensionManagement::ALLOWED_STATES, true)) {
                    $keepUserData = \array_key_exists('keepUserData', $override) && \is_bool($override['keepUserData']) && $override['keepUserData'];

                    $extensionManagement->overrides[(string) $extension] = ['state' => $override['state'], 'keepUserData' => $keepUserData];
                }
            }
        }
    }

    public static function fillLicenseDomain(ProjectConfiguration $projectConfiguration): ProjectConfiguration
    {
        if (EnvironmentHelper::hasVariable('SHOPWARE_STORE_LICENSE_DOMAIN')) {
            $projectConfiguration->store->licenseDomain = EnvironmentHelper::getVariable('SHOPWARE_STORE_LICENSE_DOMAIN', '');
        }

        return $projectConfiguration;
    }
}
