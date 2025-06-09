<?php

declare(strict_types=1);

namespace Shopware\Deployment;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ApplicationOutput implements OutputInterface
{
    public function __construct(private OutputInterface $decorated)
    {
    }

    private function wrapMessage(string $message): string
    {
        return preg_replace('#(^|\n)(.)#m', '$1[deployment-helper] $2', $message) ?? $message;
    }

    /**
     * @param iterable<array-key, string> $messages
     *
     * @return iterable<int, string>
     */
    private function wrapMessages(iterable $messages): iterable
    {
        foreach ($messages as $message) {
            yield $this->wrapMessage($message);
        }
    }

    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        if (\is_string($messages)) {
            $messages = $this->wrapMessage($messages);
        } else {
            $messages = $this->wrapMessages($messages);
        }

        $this->decorated->write($messages, $newline, $options);
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        if (\is_string($messages)) {
            $messages = $this->wrapMessage($messages);
        } else {
            $messages = $this->wrapMessages($messages);
        }

        $this->decorated->writeln($messages, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->decorated->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->decorated->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->decorated->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->decorated->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->decorated->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->decorated->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->decorated->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->decorated->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->decorated->setFormatter($formatter);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->decorated->getFormatter();
    }
}
