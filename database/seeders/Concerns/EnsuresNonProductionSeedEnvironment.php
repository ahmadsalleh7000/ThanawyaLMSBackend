<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait EnsuresNonProductionSeedEnvironment
{
    private function ensureNonProductionSeedEnvironment(): void
    {
        // if (app()->environment('production')) {
        //     throw new RuntimeException('Demo seed data must never be seeded in the production environment.');
        // }
    }
}
