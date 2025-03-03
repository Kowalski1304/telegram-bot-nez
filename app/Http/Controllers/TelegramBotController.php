<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMessageHandler;
use Illuminate\Http\JsonResponse;
use Telegram\Bot\Api;
use Illuminate\Http\Response;

class TelegramBotController extends Controller
{
    protected $messageHandler;
    protected $telegram;

    public function __construct(TelegramMessageHandler $messageHandler)
    {
        $this->messageHandler = $messageHandler;
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function webhook(): Response|JsonResponse
    {
        $update = $this->telegram->getWebhookUpdate();

        if ($message = $update->message) {
            $telegramId = $message->chat->id;
            $text = $message->text ?? null;

            if ($text === '/start') {
                return $this->messageHandler->handleStart($telegramId, $message);
            } if ($text === '/link') {
                return $this->messageHandler->handleLink($telegramId, $message);
            } else {
                return $this->messageHandler->handleMessage($telegramId, $message);
            }
        }

        return response('ok', 200);
    }

    public function updateTelegramWebhook(): JsonResponse
    {
        $telegramToken = env('TELEGRAM_BOT_TOKEN');
        $webhookUrl = env('TELEGRAM_WEBHOOK_URL');
        $apiUrl = "https://api.telegram.org/bot{$telegramToken}/setWebhook";

        $client = new \GuzzleHttp\Client();
        $response = $client->post($apiUrl, [
            'form_params' => ['url' => $webhookUrl]
        ]);

        return response()->json([
            'status' => 'ok',
            'response' => json_decode($response->getBody()->getContents(), true)
        ]);
    }
}
