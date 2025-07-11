<?php
namespace Paytr\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Paytr\PaytrServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [PaytrServiceProvider::class];
    }
}
