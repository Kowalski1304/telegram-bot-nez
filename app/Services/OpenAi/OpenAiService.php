<?php

namespace App\Services\OpenAi;

use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use OpenAI;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->client = OpenAI::client($this->apiKey);
        $this->telegramClient = new TelegramClient;
    }


    public function analyzeText(string $text, $telegramId): JsonResponse|array
    {
        try {
            $prompt = "
                Проаналізуй наступний текст витрат і поверни лише JSON з ключами:
                - 'total' (загальна сума чека),
                - 'category' (категорія товарів або 'Інше', якщо неможливо визначити),
                - 'description' (короткий перелік товарів з цінами в одному рядку або 'Товар', якщо неможливо визначити).

                Не додавай пояснень чи додаткового тексту, лише JSON.
                Текст: {$text}
                ";

            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => 'Аналізуй витрати з текстових повідомлень.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $content = $response->choices[0]->message->content ?? '';
            $result = json_decode($content, true);

            if (
                !isset($result['total']) ||
                !isset($result['category']) ||
                !isset($result['description']) ||
                $result['total'] == 0
            ) {
                $this->telegramClient->sendMessage($telegramId, "Повідомлення не дійсне. Отримали текст: {$text}.");
                http_response_code(200);
                echo json_encode(['ok' => true]);
                exit;
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OpenAI response parsing error: ' . json_last_error_msg());
                Log::error('OpenAI raw response: ' . $content);
                return response()->json(['error' => 'Неправильний формат JSON у відповіді OpenAI'], 500);
            }

            $total = $result['total'];
            $category = $result['category'];
            $description = $result['description'];

            return compact('total', 'category', 'description');
        } catch (\Exception $e) {
            Log::error('OpenAI analysis error: ' . $e->getMessage());
            return response()->json(['error' => 'Помилка при аналізі тексту: ' . $e->getMessage()], 500);
        }
    }

    public function analyzeAudio($audioPath): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->attach(
                'file',
                file_get_contents($audioPath),
                basename($audioPath)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ]);

            if ($response->successful()) {
                return $response->json('text');
            } else {
                Log::error('Audio transcription error: ' . $response->body());
                throw new \Exception('API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Audio analysis error: ' . $e->getMessage());
            throw $e;
        }
    }
}
