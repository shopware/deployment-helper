<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\DumpEnvCommand;
use Shopware\Deployment\Services\DotenvLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DumpEnvCommand::class)]
class DumpEnvCommandTest extends TestCase
{
    public function testDumpsParsedValuesAsJson(): void
    {
        $loader = $this->createMock(DotenvLoader::class);
        $loader->expects(static::once())
            ->method('parse')
            ->willReturn([
                'APP_URL' => 'https://example.com',
                'FOO' => 'bar',
            ]);

        $tester = new CommandTester(new DumpEnvCommand($loader));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertJson($tester->getDisplay());
        static::assertSame(
            ['APP_URL' => 'https://example.com', 'FOO' => 'bar'],
            json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testDumpsEmptyObjectWhenNothingIsParsed(): void
    {
        $loader = $this->createMock(DotenvLoader::class);
        $loader->method('parse')->willReturn([]);

        $tester = new CommandTester(new DumpEnvCommand($loader));
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame("{}\n", $tester->getDisplay());
    }

    public function testCommandIsHidden(): void
    {
        $command = new DumpEnvCommand($this->createMock(DotenvLoader::class));

        static::assertTrue($command->isHidden());
        static::assertSame('dump-env', $command->getName());
    }

    public function testDoesNotInterpretFormatterTags(): void
    {
        $loader = $this->createMock(DotenvLoader::class);
        $loader->method('parse')->willReturn(['FOO' => '<info>bar</info>']);

        $tester = new CommandTester(new DumpEnvCommand($loader));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('<info>bar</info>', $tester->getDisplay());
    }
}
