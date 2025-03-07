<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;
use Telegram\Bot\Api;

class TelegramBotController extends Controller
{
    public function __construct(private readonly TelegramMessageHandler $messageHandler, private readonly Api $telegram)
    {
    }

    public function webhook(): Response|JsonResponse
    {
        $update = $this->telegram->getWebhookUpdate();
        $message = $update->message;

        if (isset($message)) {
            $telegramId = $message->chat->id;
            $text = $message->text ?? null;

            match($text) {
                '/start'    =>  $this->messageHandler->handleStart($telegramId, $message),
                '/link'     =>  $this->messageHandler->handleLink($telegramId, $message),
                default     =>  $this->messageHandler->handleMessage($telegramId, $message),
            };
        }

        return response('ok', 200);
    }

    public function updateTelegramWebhook(): JsonResponse
    {
        $telegramToken = config('telegram.bots.mybot.token');
        $webhookUrl = config('telegram.bots.mybot.webhook_url');
        $apiUrl = "https://api.telegram.org/bot{$telegramToken}/setWebhook";

        Http::post($apiUrl, [
            'url' => $webhookUrl
        ]);

        return response()->json(['status' => 'ok']);
    }
}
