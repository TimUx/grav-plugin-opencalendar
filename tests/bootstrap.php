<?php

/**
 * PHPUnit bootstrap for OpenCalendar plugin tests.
 *
 * Loads Composer autoloader. Grav core is not required for unit tests that
 * exercise pure domain classes; integration tests may define their own bootstrap.
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(
        STDERR,
        "Composer autoload not found. Run `composer install` before executing tests.\n"
    );
    exit(1);
}

require $autoload;
