<?php declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        // Ensure there is no code on the same line as the PHP open tag and it is followed by a blank line.
        'blank_line_after_opening_tag' => false,
        // Ensure there is no code on the same line as the PHP open tag.
        'linebreak_after_opening_tag' => false,
        // We use the attribute CoversClass which is not recognized by php-cs-fixer
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__),
    )
    ;