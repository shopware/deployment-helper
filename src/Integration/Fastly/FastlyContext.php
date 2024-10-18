<?php

declare(strict_types=1);

namespace Shopware\Deployment\Integration\Fastly;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-import-type Snippet from FastlyAPIClient
 */
#[Exclude]
class FastlyContext
{
    /**
     * @param Snippet[] $snippets
     */
    public function __construct(
        public array $snippets,
        public string $serviceId,
        public int $currentlyActiveVersion,
        public ?int $createdVersion = null)
    {
    }

    public function hasSnippet(string $snippetName): bool
    {
        foreach ($this->snippets as $snippet) {
            if ($snippet['name'] === $snippetName) {
                return true;
            }
        }

        return false;
    }

    public function hasSnippetChanged(string $snippetName, string $content): bool
    {
        foreach ($this->snippets as $snippet) {
            if ($snippet['name'] === $snippetName && $snippet['content'] !== $content) {
                return true;
            }
        }

        return false;
    }
}
