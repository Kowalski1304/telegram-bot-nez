<?php

namespace App\Providers;

use App\Services\Expense\ExpenseService;
use App\Services\Google\GoogleService;
use App\Services\OpenAi\OpenAiService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMediaService;
use App\Services\Telegram\TelegramMessageHandler;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->app->bind(Api::class, fn () => new Api(config('telegram.bots.mybot.token')));

        $this->app->singleton(TelegramClient::class, fn () => new TelegramClient());

        $this->app->singleton(TelegramMediaService::class, fn () => new TelegramMediaService());

        $this->app->singleton(
            TelegramMessageHandler::class,
            fn($app) => new TelegramMessageHandler(
                $app->make(OpenAiService::class),
                $app->make(GoogleService::class),
                $app->make(TelegramClient::class),
                $app->make(TelegramMediaService::class),
                $app->make(ExpenseService::class)
            )
        );
    }
}
