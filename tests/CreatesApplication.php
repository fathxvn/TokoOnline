<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Cached production configuration must never bypass environment overrides.
        if ($app->configurationIsCached()) {
            throw new \RuntimeException('Tests refused: cached application configuration is present.');
        }

        $app->make(Kernel::class)->bootstrap();

        \Tests\Support\DatabaseIsolation::assert($app);

        return $app;
    }
}
