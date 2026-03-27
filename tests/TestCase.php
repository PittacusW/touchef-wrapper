<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pittacusw\Touchef\TouchefServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TouchefServiceProvider::class,
        ];
    }
}
