<?php

namespace App\Services\Telegram;

use App\DTO\ExpenseDTO;
use App\Models\User;
use App\Services\Expense\ExpenseService;
use App\Services\Google\GoogleService;
use App\Services\OpenAi\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramMessageHandler
{
    public function __construct(
        private readonly OpenAiService $openAiService,
        private readonly GoogleService $googleService,
        private readonly TelegramClient $telegramClient,
        private readonly TelegramMediaService $mediaService,
        private readonly ExpenseService $expenseService
    ) {
    }

    public function handleStart(int $telegramId, object $message): JsonResponse
    {
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['name' => $message->from->first_name]
        );

        $this->checkSheetLink($user);

        return response()->json(['status' => 'ok']);
    }

    public function handleLink(int $telegramId, object $message): JsonResponse
    {
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['name' => $message->from->first_name]
        );
        if ($user->sheet_link) {
            $this->telegramClient->sendMessage($user->telegram_id, "Твоя табличка з витратами: $user->sheet_link");
        }

        if (!$user->sheet_link) {
            $this->checkSheetLink($user);
        }

        return response()->json(['status' => 'ok']);
    }

    public function handleMessage(int $telegramId, object $message): JsonResponse
    {
        try {
            $data = $this->analyze($message, $telegramId);

            $amount = $data['total'];
            $category = $data['category'];
            $description = $data['description'];

            if (is_null($amount)) {
                $this->telegramClient->sendMessage($telegramId, "Надішли заново. Обробка не успішна.");
                return response()->json(['error' => 'Amount extraction failed']);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            if (!$user) {
                $this->handleStart($telegramId, $message);
                $user = User::where('telegram_id', $telegramId)->first();
            }

            $this->checkSheetLink($user);

            $this->googleService->addExpenseToSheet($amount, $user, $category, $description);
            $expenseDTO = new ExpenseDTO(
                $user->id,
                $amount,
                'telegram',
                $category,
                $description
            );

            $this->expenseService->storeExpense($expenseDTO);

            $this->telegramClient->sendMessage($telegramId, "Ти cтав біднішій на: {$amount}");

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Error handling message: ' . $e->getMessage());
            $this->telegramClient->sendMessage($telegramId, "Виникла помилка під час обробки повідомлення.");
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    protected function analyze(object $message, int $telegramId): JsonResponse|array
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

        return response()->json(['error' => 'No data to analyze'], 400);
    }

    protected function checkSheetLink(?User $user): void
    {
        if ($user && !$user->sheet_link) {
            $sheetLink = $this->googleService->createCustomSheet($user);
            $this->telegramClient->sendMessage($user->telegram_id, "Твоя табличка з витратами: $sheetLink");
        }
    }
}
