<?php

declare(strict_types=1);

/** @var \PhpCsFixer\Config $phpCsConfig */
$phpCsConfig = require(dirname(__DIR__, 2) . '/.php-cs-fixer.dist.php');

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/public',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->ignoreVCSIgnored(true);

$phpCsConfig->setFinder($finder);
$phpCsConfig->setRules([
    ...$phpCsConfig->getRules(),
    'header_comment' => false,
]);

return $phpCsConfig->setFinder($finder);
