<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\ApplicationOutput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(ApplicationOutput::class)]
class ApplicationOutputTest extends TestCase
{
    public function testGetDecoratedReturnsInnerOutput(): void
    {
        $inner = new BufferedOutput();
        $output = new ApplicationOutput($inner);

        static::assertSame($inner, $output->getDecorated());
    }

    public function testWritePrefixesMessages(): void
    {
        $inner = new BufferedOutput();
        $output = new ApplicationOutput($inner);

        $output->writeln('hello');

        static::assertSame("[deployment-helper] hello\n", $inner->fetch());
    }
}
