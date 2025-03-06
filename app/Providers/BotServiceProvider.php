<?php

namespace App\Providers;

use App\Services\Expense\ExpenseService;
use App\Services\Google\GoogleService;
use App\Services\OpenAi\OpenAiService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMediaService;
use App\Services\Telegram\TelegramMessageHandler;
use Illuminate\Support\ServiceProvider;

class BotServiceProvider extends ServiceProvider
{
    /**
     * Реєструє сервіси бота
     */
    public function register(): void
    {
        // Реєстрація базових сервісів
        $this->app->singleton(OpenAiService::class, function ($app) {
            return new OpenAiService();
        });

        $this->app->singleton(GoogleService::class, function ($app) {
            return new GoogleService();
        });

        // Реєстрація Telegram-сервісів
        $this->app->singleton(TelegramClient::class, function ($app) {
            return new TelegramClient();
        });

        $this->app->singleton(TelegramMediaService::class, function ($app) {
            return new TelegramMediaService();
        });

        // Реєстрація сервісу для витрат
        $this->app->singleton(ExpenseService::class, function ($app) {
            return new ExpenseService();
        });

        // Реєстрація обробника повідомлень
        $this->app->singleton(TelegramMessageHandler::class, function ($app) {
            return new TelegramMessageHandler(
                $app->make(OpenAiService::class),
                $app->make(GoogleService::class),
                $app->make(TelegramClient::class),
                $app->make(TelegramMediaService::class),
                $app->make(ExpenseService::class)
            );
        });
    }

    /**
     * Налаштовує сервіси після завантаження
     */
    public function boot(): void
    {
        // Тут можна додати додаткові налаштування, якщо потрібно
    }
}
