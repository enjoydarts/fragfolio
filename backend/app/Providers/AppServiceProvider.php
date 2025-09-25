<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // WebAuthn Custom Challenge Repository
        $this->app->bind(
            \Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository::class,
            \App\WebAuthn\CacheChallengeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use custom WebAuthn credential model
        $this->app->bind(
            \Laragear\WebAuthn\Models\WebAuthnCredential::class,
            \App\Models\WebAuthnCredential::class
        );
    }
}
