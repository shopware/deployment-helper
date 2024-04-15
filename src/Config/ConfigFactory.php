<?php declare(strict_types=1);

namespace Shopware\Deployment\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class ConfigFactory
{
    public static function create(string $projectDir): ProjectConfiguration
    {
        $file = Path::join($projectDir, '.shopware-project.yml');

        if (!file_exists($file)) {
            return new ProjectConfiguration();
        }

        $projectConfiguration = new ProjectConfiguration();
        $config = Yaml::parseFile($file);

        if (isset($config['deployment']) && is_array($config['deployment'])) {
            self::fillConfig($projectConfiguration, $config['deployment']);
        }

        return $projectConfiguration;
    }

    /**
     * @param array<string, mixed> $deployment
     */
    private static function fillConfig(ProjectConfiguration $projectConfiguration, array $deployment): void
    {
        if (isset($deployment['hooks']) && is_array($deployment['hooks'])) {
            self::fillHooks($projectConfiguration->hooks, $deployment['hooks']);
        }

        if (isset($deployment['extension-management']) && is_array($deployment['extension-management'])) {
            self::fillExtensionManagement($projectConfiguration->extensionManagement, $deployment['extension-management']);
        }

        if (isset($deployment['one-time-tasks']) && is_array($deployment['one-time-tasks'])) {
            foreach ($deployment['one-time-tasks'] as $task) {
                if (isset($task['id']) && is_string($task['id']) && isset($task['script']) && is_string($task['script'])) {
                    $projectConfiguration->oneTimeTasks[$task['id']] = $task['script'];
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function fillHooks(ProjectHooks $hooks, array $config): void
    {
        if (isset($config['pre']) && is_string($config['pre'])) {
            $hooks->pre = $config['pre'];
        }

        if (isset($config['post']) && is_string($config['post'])) {
            $hooks->post = $config['post'];
        }

        if (isset($config['pre-install']) && is_string($config['pre-install'])) {
            $hooks->preInstall = $config['pre-install'];
        }

        if (isset($config['post-install']) && is_string($config['post-install'])) {
            $hooks->postInstall = $config['post-install'];
        }

        if (isset($config['pre-update']) && is_string($config['pre-update'])) {
            $hooks->preUpdate = $config['pre-update'];
        }

        if (isset($config['post-update']) && is_string($config['post-update'])) {
            $hooks->postUpdate = $config['post-update'];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fillExtensionManagement(ProjectExtensionManagement $extensionManagement, array $config): void
    {
        if (isset($config['enabled']) && is_bool($config['enabled'])) {
            $extensionManagement->enabled = $config['enabled'];
        }

        if (isset($config['exclude']) && is_array($config['exclude'])) {
            $extensionManagement->excluded = $config['exclude'];
        }
    }
}
