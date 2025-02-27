<?php

namespace App\Services;

use Google_Service_Drive;
use Google_Service_Drive_Permission;
use Google_Service_Sheets_ValueRange;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_Spreadsheet;

class GoogleService
{
    public function createCustomSheet($user)
    {
        $client = new Google_Client();
        $client->setApplicationName("My Laravel App $user->name");
        $client->setScopes([
            Google_Service_Sheets::SPREADSHEETS,
            Google_Service_Drive::DRIVE
        ]);

        $credentialsPath = storage_path('app/credentials.json');
        if (!file_exists($credentialsPath)) {
            throw new \Exception("Файл credentials.json не знайдено.");
        }
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $token = $client->fetchAccessTokenWithAssertion();
        if (isset($token['error'])) {
            throw new \Exception('Помилка отримання токена: ' . $token['error_description']);
        }
        $client->setAccessToken($token);

        $sheetsService = new Google_Service_Sheets($client);

        $spreadsheet = new Google_Service_Sheets_Spreadsheet([
            'properties' => ['title' => "Витрати $user->name"],
            'sheets' => [
                [
                    'properties' => [
                        'sheetId' => 0,
                        'title' => 'My Custom Sheet',
                        'gridProperties' => [
                            'rowCount' => 52,
                            'columnCount' => 20
                        ]
                    ]
                ]
            ]
        ]);

        $result = $sheetsService->spreadsheets->create($spreadsheet, ['fields' => 'spreadsheetId']);
        $spreadsheetId = $result->spreadsheetId;

        $driveService = new Google_Service_Drive($client);
        $permission = new Google_Service_Drive_Permission();
        $permission->setType('anyone');
        $permission->setRole('reader');
        $driveService->permissions->create($spreadsheetId, $permission);

        $headerValues = [
            ['Дата', 'Сума']
        ];
        $headerBody = new Google_Service_Sheets_ValueRange(['values' => $headerValues]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->update($spreadsheetId, 'My Custom Sheet!A1:B1', $headerBody, $params);

        $sumValues = [
            ['Всього', '=SUM(B2:B51)']
        ];
        $sumBody = new Google_Service_Sheets_ValueRange(['values' => $sumValues]);

        $params = ['valueInputOption' => 'USER_ENTERED'];

        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            'My Custom Sheet!D1:E1',
            $sumBody,
            $params
        );

        $url  = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";
        $user->update([
            'sheet_link' => $url,
        ]);

        return $url;
    }

    public function addExpenseToSheet($amount, $user)
    {
        $date = date('Y-m-d');

        $client = new Google_Client();
        $client->setApplicationName('My Laravel App');
        $client->setScopes([
            Google_Service_Sheets::SPREADSHEETS
        ]);

        $credentialsPath = storage_path('app/credentials.json');
        if (!file_exists($credentialsPath)) {
            throw new \Exception("Файл credentials.json не знайдено.");
        }
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $token = $client->fetchAccessTokenWithAssertion();
        if (isset($token['error'])) {
            throw new \Exception('Помилка отримання токена: ' . $token['error_description']);
        }
        $client->setAccessToken($token);

        $sheetsService = new Google_Service_Sheets($client);

        $parsedUrl = parse_url($user->sheet_link);
        $spreadsheetId = explode('/', $parsedUrl['path'])[3];


        $range = 'My Custom Sheet!A2:A51';
        $response = $sheetsService->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues() ?? [];

        $nextRow = count($values) + 2;

        if ($nextRow > 51) {
            return response()->json(['error' => 'Немає вільного місця у таблиці'], 400);
        }

        $newValues = [
            [$date, $amount]
        ];

        $body = new Google_Service_Sheets_ValueRange(['values' => $newValues]);

        $params = ['valueInputOption' => 'USER_ENTERED'];

        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "My Custom Sheet!A{$nextRow}:B{$nextRow}",
            $body,
            $params
        );

        return response()->json(['message' => 'Витрати додано успішно']);
    }
}
