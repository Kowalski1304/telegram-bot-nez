<?php

namespace App\Services\Telegram;

use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Message as MessageObject;
use Telegram\Bot\Objects\Update as UpdateObject;

class TelegramClient
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function sendMessage($chatId, $message, array $options = []): MessageObject|bool
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $message,
            ], $options);

            return $this->telegram->sendMessage($params);
        } catch (\Exception $e) {
            Log::error("Error sending Telegram message: " . $e->getMessage());
            return false;
        }
    }

    public function getUpdates(): array
    {
        return $this->telegram->getUpdates();
    }

    public function getWebhookUpdate(): UpdateObject
    {
        return $this->telegram->getWebhookUpdate();
    }
}
