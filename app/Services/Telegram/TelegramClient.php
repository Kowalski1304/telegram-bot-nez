<?php

namespace App\Services\Telegram;

use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Message as MessageObject;

class TelegramClient
{
    public function __construct(private readonly Api $telegram)
    {
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
}
