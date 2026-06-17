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

    public function testConsoleParallelRunsAllCommandsAndTagsOutput(): void
    {
        $projectDir = self::makeFakeProjectDir(<<<'PHP'
            $argv = array_values(array_filter(array_slice($argv, 1), fn (string $a): bool => $a !== '-n'));
            echo 'argv=' . implode(' ', $argv) . "\n";
            exit(0);
            PHP);

        try {
            $output = new BufferedConsoleOutput();
            $helper = new ProcessHelper($projectDir, output: $output);

            $helper->consoleParallel([
                ['theme:compile', '--sales-channel-id=aaa'],
                ['theme:compile', '--sales-channel-id=bbb'],
                ['theme:compile', '--sales-channel-id=ccc'],
            ], 2);

            $stdout = $output->getStdout();
            static::assertStringContainsString('[#1 theme:compile --sales-channel-id=aaa] argv=theme:compile --sales-channel-id=aaa', $stdout);
            static::assertStringContainsString('[#2 theme:compile --sales-channel-id=bbb] argv=theme:compile --sales-channel-id=bbb', $stdout);
            static::assertStringContainsString('[#3 theme:compile --sales-channel-id=ccc] argv=theme:compile --sales-channel-id=ccc', $stdout);
        } finally {
            self::removeDirectory($projectDir);
        }
    }

    public function testConsoleParallelRaisesWithCapturedStderr(): void
    {
        $projectDir = self::makeFakeProjectDir(<<<'PHP'
            $argv = array_slice($argv, 1);
            if (in_array('--fail', $argv, true)) {
                fwrite(STDERR, "boom: something went wrong\n");
                exit(7);
            }
            echo "ok\n";
            exit(0);
            PHP);

        try {
            $helper = new ProcessHelper($projectDir, output: new BufferedConsoleOutput());

            try {
                $helper->consoleParallel([
                    ['theme:compile', '--fail'],
                    ['theme:compile', '--ok'],
                ], 2);
                static::fail('expected RuntimeException');
            } catch (\RuntimeException $e) {
                static::assertStringContainsString('Parallel execution failed', $e->getMessage());
                static::assertStringContainsString('boom: something went wrong', $e->getMessage());
                static::assertStringContainsString('exit 7', $e->getMessage());
            }
        } finally {
            self::removeDirectory($projectDir);
        }
    }

    public function testConsoleParallelStopsLaunchingAfterFailure(): void
    {
        $projectDir = self::makeFakeProjectDir(<<<'PHP'
            $argv = array_values(array_filter(array_slice($argv, 1), fn (string $a): bool => $a !== '-n'));
            [$marker, $stateFile] = [$argv[1] ?? '', $argv[2] ?? ''];
            if ($stateFile !== '') {
                file_put_contents($stateFile, $marker . "\n", FILE_APPEND | LOCK_EX);
            }
            exit($marker === 'fail' ? 1 : 0);
            PHP);
        $stateFile = (string) tempnam(sys_get_temp_dir(), 'parallel_state_');

        try {
            $helper = new ProcessHelper($projectDir, output: new BufferedConsoleOutput());

            try {
                $helper->consoleParallel([
                    ['cmd', 'fail', $stateFile],
                    ['cmd', 'ok-2', $stateFile],
                    ['cmd', 'ok-3', $stateFile],
                    ['cmd', 'ok-4', $stateFile],
                ], 1);
            } catch (\RuntimeException) {
                // expected
            }

            $launched = array_values(array_filter(
                explode("\n", (string) file_get_contents($stateFile)),
                static fn (string $line): bool => $line !== '',
            ));
            static::assertContains('fail', $launched);
            static::assertLessThan(4, \count($launched), 'queue should stop dispatching after a failure');
        } finally {
            @unlink($stateFile);
            self::removeDirectory($projectDir);
        }
    }

    private static function makeFakeProjectDir(string $phpBody): string
    {
        $dir = sys_get_temp_dir() . '/ph_test_' . bin2hex(random_bytes(6));
        mkdir($dir . '/bin', 0o755, true);
        file_put_contents($dir . '/bin/console', "<?php\n" . $phpBody . "\n");

        return $dir;
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
