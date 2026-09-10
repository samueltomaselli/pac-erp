<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    private string $configuredGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuredGuard = config('auth.defaults.guard');

        $this->withHeader('Referer', 'http://'.config('sanctum.stateful')[0]);
    }

    protected function freshBrowserSession(): static
    {
        Auth::forgetGuards();
        Auth::shouldUse($this->configuredGuard);

        return $this;
    }
}
