<?php

namespace Worldesports\MultiTenancy\Support;

use Illuminate\Support\Facades\File;

class AuthScaffoldingDetector
{
    /**
     * Detect if Laravel Breeze is installed.
     */
    public function hasBreeze(): bool
    {
        return File::exists(base_path('composer.json')) &&
            str_contains(File::get(base_path('composer.json')), 'laravel/breeze');
    }

    /**
     * Detect if Laravel Jetstream is installed.
     */
    public function hasJetstream(): bool
    {
        return File::exists(base_path('composer.json')) &&
            str_contains(File::get(base_path('composer.json')), 'laravel/jetstream');
    }

    /**
     * Detect if Laravel Fortify is installed.
     */
    public function hasFortify(): bool
    {
        return File::exists(base_path('composer.json')) &&
            str_contains(File::get(base_path('composer.json')), 'laravel/fortify');
    }

    /**
     * Detect if Laravel Sanctum is installed.
     */
    public function hasSanctum(): bool
    {
        return File::exists(base_path('composer.json')) &&
            str_contains(File::get(base_path('composer.json')), 'laravel/sanctum');
    }

    /**
     * Detect if Fortify Actions are present (used by Jetstream).
     */
    public function hasFortifyActions(): bool
    {
        return File::exists(app_path('Actions/Fortify'));
    }

    /**
     * Detect if custom authentication is being used.
     * This checks for Auth controllers that aren't part of Breeze.
     */
    public function hasCustomAuth(): bool
    {
        // Check for Auth controllers (not used by Jetstream/Fortify)
        $hasAuthControllers = File::exists(app_path('Http/Controllers/Auth')) &&
            ! $this->hasBreeze();

        // Check for Fortify actions (used by Jetstream)
        $hasFortifyActions = $this->hasFortifyActions();

        return $hasAuthControllers || $hasFortifyActions;
    }

    /**
     * Get the detected authentication scaffolding type.
     */
    public function getAuthType(): ?string
    {
        if ($this->hasJetstream()) {
            return 'jetstream';
        }

        if ($this->hasBreeze()) {
            return 'breeze';
        }

        if ($this->hasFortify()) {
            return 'fortify';
        }

        if ($this->hasCustomAuth()) {
            return 'custom';
        }

        return null;
    }

    /**
     * Check if any auth scaffolding is detected.
     */
    public function hasAuthScaffolding(): bool
    {
        return $this->getAuthType() !== null;
    }

    /**
     * Check if all required auth scaffolding is in place.
     *
     * @phpstan-impure
     */
    public function passes(): bool
    {
        return $this->hasAuthScaffolding();
    }

    /**
     * Get list of auth issues (missing scaffolding).
     */
    public function issues(): array
    {
        $issues = [];

        if (! $this->hasBreeze() && ! $this->hasJetstream() && ! $this->hasFortify() && ! $this->hasSanctum() && ! $this->hasCustomAuth()) {
            $issues[] = 'No authentication scaffolding detected';
        }

        return $issues;
    }
}
