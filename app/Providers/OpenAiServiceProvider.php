<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class OpenAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->app->bind(\OpenAI::class, fn () => \OpenAI::client(config('open_ai.key')));
    }
}
