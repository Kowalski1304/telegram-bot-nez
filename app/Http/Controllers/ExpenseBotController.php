<?php

namespace App\Http\Controllers;

use App\Services\GoogleService;
use App\Services\OpenAiService;
use App\Models\User;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use thiagoalessio\TesseractOCR\TesseractOCR;
use GuzzleHttp\Client;

class ExpenseBotController extends Controller
{
    protected $telegram;
    protected $openAiService;
    protected $googleService;

    public function __construct(OpenAiService $openAiService, GoogleService $googleService)
    {
        $this->openAiService = $openAiService;
        $this->googleService = $googleService;
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function webhook()
    {
        $update = $this->telegram->getWebhookUpdate();

        if (isset($update->message)) {
            $message = $update->message;
            $telegramId = $message->chat->id;
            $text = $message->text;

            if ($text === '/start') {
                $this->start($telegramId, $message);
            } else {
                $this->handleMessage($telegramId, $message);
            }
        }
        return response('ok', 200);
    }


    protected function start($telegramId, $message)
    {
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['name' => $message->from->first_name]
        );
        Log::info('$telegramId', ['value' => $telegramId]);

        $sheetLink = $user->sheet_link;

        if (!$sheetLink) {
            $sheetLink = $this->googleService->createCustomSheet($user);
        }

        $this->sendTelegramMessage($telegramId, "Твоя табличка з витратами: {$sheetLink}");

        return response()->json(['status' => 'ok']);
    }

    public function handleMessage($telegramId, $message)
    {
        $result = $this->analyze($message);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        $amount = $result['total'];
        $this->sendTelegramMessage($telegramId, "Ти cтав біднішій на: {$amount}");

        if (is_null($amount)) {
            $this->sendTelegramMessage($telegramId, "Надішли заново обробка не успішна.");
            return response()->json(['error' => 'Amount extraction failed'], 400);
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not registered'], 400);
        }

        if (!$user->sheet_link) {
            $this->googleService->createCustomSheet($user);
        }

        $this->googleService->addExpenseToSheet($amount, $user);

        $this->storeExpense($user->id, $amount, 'telegram');

        return response()->json(['status' => 'ok']);
    }

    protected function analyze($message)
    {
        if (isset($message->text)) {
            return $this->openAiService->analyzeText($message->text);
        }

        if (isset($message->photo)) {
            $photoPath = $this->downloadPhoto($message);

            $text = (new TesseractOCR($photoPath))
                ->lang('ukr')
                ->run();

            return $this->openAiService->analyzeText($text);
        }

        if (isset($message->voice)) {
            // Функціонал обробки голосових повідомлень в розробці
            //            return $this->analyzeVoiceMessage($request);
            return response()->json(['error' => 'В розробці'], 400);
        }

        return response()->json(['error' => 'Немає даних для аналізу'], 400);
    }

    protected function downloadPhoto($message)
    {
        $photoSizes = $message['photo'];
        $highestQuality = end($photoSizes);
        $fileId = $highestQuality['file_id'];

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $file = $telegram->getFile(['file_id' => $fileId]);
        $filePath = $file->getFilePath();

        $downloadUrl = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath;
        $client = new Client(['timeout' => 10.0]);

        try {
            $client->get($downloadUrl);
        } catch (\Exception $e) {
            \Log::error('Download error: ' . $e->getMessage());
            return false;
        }

        $contents = file_get_contents($downloadUrl);

        $localFileName = basename($filePath);
        $localPath = storage_path("app/photos/{$localFileName}");
        file_put_contents($localPath, $contents);

        return $localPath;
    }

    protected function sendTelegramMessage($telegramId, $message)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $telegram->sendMessage([
            'chat_id' => $telegramId,
            'text'    => $message,
        ]);
    }

    public function storeExpense(int $userId, float $amount, string $source)
    {
        Expense::create([
            'user_id'    => $userId,
            'amount'     => $amount,
            'source'     => $source,
            'created_at' => Carbon::now()
        ]);

    }

    protected function getUserSheetLink($userId)
    {
        $user = User::find($userId);
        return $user ? $user->sheet_link : null;
    }

    public function updateTelegramWebhook()
    {
        $telegramToken = env('TELEGRAM_BOT_TOKEN');
        $webhookUrl = env('TELEGRAM_WEBHOOK_URL');
        $apiUrl = "https://api.telegram.org/bot{$telegramToken}/setWebhook";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $webhookUrl]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
