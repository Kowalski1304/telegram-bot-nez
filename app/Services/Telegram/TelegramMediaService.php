<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;

class TelegramMediaService
{
    public function __construct(private readonly Api $telegram)
    {
    }

    public function downloadMedia($message, string $type): bool|string
    {
        switch ($type) {
            case 'photo':
                $photoSizes = $message->photo;
                $largestPhoto = $photoSizes[count($photoSizes) - 1];
                $fileId = $largestPhoto->file_id;

                $storageFolder = 'photos';
                break;
            case 'voice':
                $fileId = $message->voice->file_id;

                $storageFolder = 'voices';
                break;
            default:
                Log::error("Unsupported media type: {$type}");
                return false;
        }

        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);

            $filePath = $file->getFilePath();

            $downloadUrl = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath;

            $response = Http::get($downloadUrl);
            $contents = $response->body();

            $localFileName = basename($filePath);

            Storage::put("{$storageFolder}/{$localFileName}", $contents);

            return storage_path("app/{$storageFolder}/{$localFileName}");
        } catch (\Exception $e) {
            Log::error("Download error for {$type}: " . $e->getMessage());
            return false;
        }
    }
}
