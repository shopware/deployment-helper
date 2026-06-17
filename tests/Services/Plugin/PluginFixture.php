<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services\Plugin;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Builds a throwaway project directory for {@see \Shopware\Deployment\Services\Plugin\PluginLoader}
 * tests: it writes each plugin's `composer.json` (and, when replaces are declared, a project
 * `composer.lock`) into a temporary directory and derives the matching `plugin:list --json` output
 * from the same declarations, so a dependency scenario is described in one place instead of being
 * split between the test and committed fixture files.
 */
final class PluginFixture
{
    /**
     * @var list<array{name: string, composerName: string, path: string, version: string, composerJsonName: string, requires: list<string>}>
     */
    private array $plugins = [];

    /**
     * @var array<string, list<string>>
     */
    private array $replaces = [];

    private ?string $dir = null;

    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Declares a plugin. `composerName` is the name reported by `plugin:list` (and used as the
     * dependency-graph key); `composerJsonName` is the name inside the written composer.json, which
     * only differs from `composerName` when the plugin is resolved through a composer `replace`.
     *
     * @param list<string> $requires composer package names this plugin requires
     */
    public function plugin(string $name, string $composerName, array $requires = [], ?string $composerJsonName = null, string $version = '1.0.0'): self
    {
        $this->plugins[] = [
            'name' => $name,
            'composerName' => $composerName,
            'path' => $name,
            'version' => $version,
            'composerJsonName' => $composerJsonName ?? $composerName,
            'requires' => $requires,
        ];

        return $this;
    }

    /**
     * Adds a `replace` entry to the generated project composer.lock, mapping the replaced package
     * names to the replacing package.
     *
     * @param list<string> $replaced
     */
    public function lockReplace(string $package, array $replaced): self
    {
        $this->replaces[$package] = $replaced;

        return $this;
    }

    /**
     * Writes the fixture to a temporary directory and returns this instance for fluent access to
     * {@see dir()} and {@see pluginListJson()}.
     */
    public function write(string $tempDir): self
    {
        $this->dir = $tempDir;

        foreach ($this->plugins as $plugin) {
            $composerJson = ['name' => $plugin['composerJsonName'], 'version' => $plugin['version']];

            if ($plugin['requires'] !== []) {
                $composerJson['require'] = array_fill_keys($plugin['requires'], '1.0.0');
            }

            $this->filesystem->dumpFile(
                Path::join($tempDir, $plugin['path'], 'composer.json'),
                (string) json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
            );
        }

        if ($this->replaces !== []) {
            $packages = [];

            foreach ($this->replaces as $package => $replaced) {
                $packages[] = ['name' => $package, 'replace' => array_fill_keys($replaced, 'self.version')];
            }

            $this->filesystem->dumpFile(
                Path::join($tempDir, 'composer.lock'),
                (string) json_encode(['packages' => $packages], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
            );
        }

        return $this;
    }

    public function dir(): string
    {
        \assert($this->dir !== null, 'PluginFixture::write() must be called before dir()');

        return $this->dir;
    }

    /**
     * The `plugin:list --json` payload matching the declared plugins.
     */
    public function pluginListJson(): string
    {
        $data = array_map(static fn (array $plugin): array => [
            'name' => $plugin['name'],
            'composerName' => $plugin['composerName'],
            'path' => $plugin['path'],
            'version' => $plugin['version'],
        ], $this->plugins);

        return (string) json_encode($data, \JSON_THROW_ON_ERROR);
    }

    /**
     * The declared plugin as it appears in the `PluginLoader::all()` result (the `plugin:list`
     * shape, keyed for convenient assertions on resolved order).
     *
     * @return array{name: string, composerName: string, path: string, version: string}
     */
    public function entry(string $name): array
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin['name'] === $name) {
                return [
                    'name' => $plugin['name'],
                    'composerName' => $plugin['composerName'],
                    'path' => $plugin['path'],
                    'version' => $plugin['version'],
                ];
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unknown plugin "%s"', $name));
    }
}
