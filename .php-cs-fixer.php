<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->name('*.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
    ])
    ->setFinder($finder)
;
