<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Services\Expense\ExpenseService;
use App\Services\Google\GoogleService;
use App\Services\OpenAi\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramMessageHandler
{
    protected $openAiService;
    protected $googleService;
    protected $telegramClient;
    protected $mediaService;
    protected $expenseService;

    public function __construct(
        OpenAiService $openAiService,
        GoogleService $googleService,
        TelegramClient $telegramClient,
        TelegramMediaService $mediaService,
        ExpenseService $expenseService
    ) {
        $this->openAiService = $openAiService;
        $this->googleService = $googleService;
        $this->telegramClient = $telegramClient;
        $this->mediaService = $mediaService;
        $this->expenseService = $expenseService;
    }

    public function handleStart($telegramId, $message): JsonResponse
    {
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['name' => $message->from->first_name]
        );

        $this->checkSheetLink($user, true);

        return response()->json(['status' => 'ok']);
    }

    public function handleMessage($telegramId, $message): JsonResponse
    {
        try {
            $data = $this->analyze($message, $telegramId);

            $amount = $data['total'];
            $category = $data['category'];
            $description = $data['description'];

            if (is_null($amount)) {
                $this->telegramClient->sendMessage($telegramId, "Надішли заново. Обробка не успішна.");
                return response()->json(['error' => 'Amount extraction failed'], 400);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            if (!$user) {
                $this->handleStart($telegramId, $message);
            }

            $this->checkSheetLink($user);

            $this->googleService->addExpenseToSheet($amount, $user, $category, $description);
            $this->expenseService->storeExpense($user->id, $amount, 'telegram', $category, $description);

            $this->telegramClient->sendMessage($telegramId, "Ти cтав біднішій на: {$amount}");

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Error handling message: ' . $e->getMessage());
            $this->telegramClient->sendMessage($telegramId, "Виникла помилка під час обробки повідомлення.");
            return response()->json(['error' => $e->getMessage()], 200);
        }
    }

    protected function analyze($message, $telegramId)
    {
        if (isset($message->text)) {
            return $this->openAiService->analyzeText($message->text, $telegramId);
        }

        if (isset($message->photo)) {
            $photoPath = $this->mediaService->downloadMedia($message, 'photo');

            $text = (new \thiagoalessio\TesseractOCR\TesseractOCR($photoPath))
                ->lang('ukr')
                ->run();

            return $this->openAiService->analyzeText($text, $telegramId);
        }

        if (isset($message->voice)) {
            $audioPath = $this->mediaService->downloadMedia($message, 'voice');
            $text = $this->openAiService->analyzeAudio($audioPath);
            return $this->openAiService->analyzeText($text, $telegramId);
        }

        return response()->json(['error' => 'Немає даних для аналізу'], 400);
    }

    protected function checkSheetLink(User $user, bool $sendMessage = null)
    {
        if (!$user->sheet_link) {
            $sheetLink = $this->googleService->createCustomSheet($user);
        }

        if ($sendMessage) {
            $sheetLink = $sheetLink ?? $user->sheet_link;
            $this->telegramClient->sendMessage($user->telegram_id, "Твоя табличка з витратами: $sheetLink");
        }
    }
}
