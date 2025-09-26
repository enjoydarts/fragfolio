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

        // AI Services
        $this->app->singleton(\App\Services\AI\AIProviderFactory::class);
        $this->app->singleton(\App\Services\AI\CostTrackingService::class);
        $this->app->bind(\App\Services\AI\CompletionService::class);
        $this->app->bind(\App\Services\AI\NormalizationService::class);
        $this->app->bind(\App\UseCases\AI\CompleteFragranceUseCase::class);
        $this->app->bind(\App\UseCases\AI\NormalizeFragranceUseCase::class);
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
