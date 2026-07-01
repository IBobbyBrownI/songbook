<?php

declare (strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/public')
    ->in(__DIR__ . '/seed')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);