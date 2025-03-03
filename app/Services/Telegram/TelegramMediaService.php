<?php

namespace App\Services\Telegram;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;

class TelegramMediaService
{
    protected $telegram;
    protected $client;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $this->client = new Client(['timeout' => 10.0]);
    }

    public function downloadMedia($message, string $type): bool|string
    {
        $fileId = null;
        $storageFolder = null;

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

            $response = $this->client->get($downloadUrl);
            $contents = $response->getBody()->getContents();

            $localFileName = basename($filePath);

            Storage::put("{$storageFolder}/{$localFileName}", $contents);

            return storage_path("app/{$storageFolder}/{$localFileName}");
        } catch (\Exception $e) {
            Log::error("Download error for {$type}: " . $e->getMessage());
            return false;
        }
    }
}
