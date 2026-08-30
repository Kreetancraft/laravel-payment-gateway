<?php

declare(strict_types=1);

use Kreetancraft\PaymentGateway\Tests\TestCase;

spl_autoload_register(function (string $class): void {
    $factoriesPrefix = 'Kreetancraft\\PaymentGateway\\Database\\Factories\\';
    if (str_starts_with($class, $factoriesPrefix)) {
        $relative = substr($class, strlen($factoriesPrefix));
        $file = __DIR__.'/../database/factories/'.str_replace('\\', '/', $relative).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    $seedersPrefix = 'Kreetancraft\\PaymentGateway\\Database\\Seeders\\';
    if (str_starts_with($class, $seedersPrefix)) {
        $relative = substr($class, strlen($seedersPrefix));
        $file = __DIR__.'/../database/seeders/'.str_replace('\\', '/', $relative).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

uses(TestCase::class)->in('Feature', 'Unit');
