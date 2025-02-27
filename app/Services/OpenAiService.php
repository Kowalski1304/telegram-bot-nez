<?php

namespace App\Services;

use OpenAI;

class OpenAiService
{

    public function analyzeText(string $text)
    {
        $prompt = "Проаналізуй наступний текст витрат і поверни лише JSON з двома ключами:
    'expenses' - масив об'єктів з полями 'item' та 'amount',
    'total' - загальна сума витрат.
    Текст: {$text}";

        $client = OpenAI::client(env('OPENAI_API_KEY'));
        $response = $client->chat()->create([
            'model'       => 'gpt-3.5-turbo',
            'temperature' => 0,
            'messages'    => [
                ['role' => 'system', 'content' => 'Аналізуй витрати з текстових повідомлень.'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '';
        $result = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Неправильний формат JSON у відповіді OpenAI'], 500);
        }

        return $result;
    }
}
