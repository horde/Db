<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for Horde_Db.
 *
 * Supports running from the package root (vendor/autoload.php) and from a
 * parent project that vendors horde/db without dev autoload entries.
 */

$packageRoot = dirname(__DIR__);
$autoloadCandidates = [
    $packageRoot . '/vendor/autoload.php',
    dirname($packageRoot, 2) . '/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

$databaseTestCase = __DIR__ . '/Integration/DatabaseTestCase.php';
if (is_file($databaseTestCase) && !class_exists('Horde\\Db\\Test\\Integration\\DatabaseTestCase', false)) {
    require $databaseTestCase;
}
