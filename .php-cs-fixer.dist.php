<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/var/cache/cs-fixer/.php-cs-fixer.cache')
    ->setRules([
        '@PHP84Migration' => true,
        '@PHP80Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder(
        Finder::create()
            ->name('shopware-deployment-helper')
            ->in([
                __DIR__ . '/bin',
                __DIR__ . '/src',
                __DIR__ . '/tests',
            ]),
    )
;
