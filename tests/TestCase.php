<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $dbName = config('database.connections.pgsql.database');
        if (config('database.default') === 'pgsql' && $dbName === 'media_intelligent') {
            echo "\n\n[FATAL ERROR] Tests cannot be run against the production database (media_intelligent).\n";
            echo "Execution aborted to prevent data loss.\n\n";
            exit(1);
        }

        return parent::setUpTraits();
    }
}
