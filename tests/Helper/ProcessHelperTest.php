<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Tests\TestUtil\BufferedConsoleOutput;

#[CoversClass(ProcessHelper::class)]
class ProcessHelperTest extends TestCase
{
    public function testOutputIsBuffered(): void
    {
        $output = new BufferedConsoleOutput();
        $helper = new ProcessHelper('/tmp', output: $output);

        $helper->runAndTail('echo "test"');

        static::assertStringContainsString('Start: echo "test"', $output->getStdout());
        static::assertStringContainsString('test', $output->getStdout());
        static::assertStringContainsString('End: echo "test"', $output->getStdout());
    }

    public function testRunAndTailReplacesPhpBinPlaceholder(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'php_bin_test_');

        try {
            $helper = new ProcessHelper('/tmp', output: new BufferedConsoleOutput());
            $helper->runAndTail('echo %php.bin% > ' . $tempFile);

            static::assertFileExists($tempFile);
            static::assertStringContainsString(\PHP_BINARY, (string) file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }
}
