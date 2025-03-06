<?php

namespace App\Services\Google;

use App\Models\User;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_Permission;
use Google_Service_Sheets;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_ValueRange;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Illuminate\Support\Facades\Log;

class GoogleService
{
    protected function initializeClient($userName = null): Google_Client
    {
        $client = new Google_Client();
        $client->setApplicationName("Expense Tracker" . ($userName ? " - $userName" : ""));
        $client->setScopes([
            Google_Service_Sheets::SPREADSHEETS,
            Google_Service_Drive::DRIVE
        ]);

        $credentialsPath = storage_path('app/credentials.json');
        if (!file_exists($credentialsPath)) {
            throw new \Exception("Credentials.json file not found.");
        }

        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $token = $client->fetchAccessTokenWithAssertion();
        if (isset($token['error'])) {
            throw new \Exception('Error getting token: ' . $token['error_description']);
        }

        $client->setAccessToken($token);

        return $client;
    }

    public function createCustomSheet(User $user): string|\Exception
    {
        try {
            $client = $this->initializeClient($user->name);
            $sheetsService = new Google_Service_Sheets($client);
            $driveService = new Google_Service_Drive($client);

            // Створення таблиці
            $spreadsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => ['title' => "Витрати {$user->name}"],
                'sheets' => [
                    [
                        'properties' => [
                            'sheetId' => 0,
                            'title' => 'Витрати',
                            'gridProperties' => [
                                'rowCount' => 1002,
                                'columnCount' => 20
                            ]
                        ]
                    ]
                ]
            ]);

            $result = $sheetsService->spreadsheets->create($spreadsheet, ['fields' => 'spreadsheetId']);
            $spreadsheetId = $result->spreadsheetId;

            // Налаштування прав доступу
            $permission = new Google_Service_Drive_Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $driveService->permissions->create($spreadsheetId, $permission);

            // Ініціалізація заголовків і форматування
            $this->initializeSheetHeaders($sheetsService, $spreadsheetId);
            $this->formatSpreadsheet($sheetsService, $spreadsheetId);

            $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";
            $user->update(['sheet_link' => $url]);

            return $url;
        } catch (\Exception $e) {
            Log::error('Error creating Google Sheet: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function initializeSheetHeaders($sheetsService, $spreadsheetId): void
    {
        $sheetName = 'Витрати';
        $params = ['valueInputOption' => 'USER_ENTERED'];

        $headerValues = [
            ['Дата', 'Сума', 'Категорія', 'Опис']
        ];
        $headerBody = new Google_Service_Sheets_ValueRange(['values' => $headerValues]);
        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!A1:D1",
            $headerBody,
            $params
        );

        $sumValues = [
            ['Всього', '=SUM(B2:B1001)']
        ];
        $sumBody = new Google_Service_Sheets_ValueRange(['values' => $sumValues]);
        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!F1:G1",
            $sumBody,
            $params
        );

        $categoryAnalyticsHeader = [
            ['Аналітика по категоріям']
        ];
        $categoryHeaderBody = new Google_Service_Sheets_ValueRange(['values' => $categoryAnalyticsHeader]);
        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!F3:G3",
            $categoryHeaderBody,
            $params
        );

        $categoryLabels = [
            ['Категорія', 'Сума']
        ];
        $categoryLabelsBody = new Google_Service_Sheets_ValueRange(['values' => $categoryLabels]);
        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!F4:G4",
            $categoryLabelsBody,
            $params
        );
    }

    protected function formatSpreadsheet($sheetsService, $spreadsheetId): void
    {
        $requests = [];

        // Форматування заголовків основної таблиці (A1:D1)
        $requests[] = $this->createHeaderFormatRequest(0, 0, 0, 3);

        // Форматування заголовка "Всього" (F1:G1)
        $requests[] = $this->createHeaderFormatRequest(0, 5, 0, 6);

        // Форматування заголовків аналітики по категоріям (F3:G3)
        $requests[] = $this->createHeaderFormatRequest(2, 5, 2, 6);

        // Форматування заголовків категорій (F4:G4)
        $requests[] = $this->createHeaderFormatRequest(3, 5, 3, 6);

        // Автоматичне масштабування стовпців
        $requests[] = [
            'autoResizeDimensions' => [
                'dimensions' => [
                    'sheetId' => 0,
                    'dimension' => 'COLUMNS',
                    'startIndex' => 0,
                    'endIndex' => 7
                ]
            ]
        ];

        // Форматування комірки з сумою витрат (G1)
        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => 0,
                    'endRowIndex' => 1,
                    'startColumnIndex' => 6,
                    'endColumnIndex' => 7
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'backgroundColor' => [
                            'red' => 0.95,
                            'green' => 0.95,
                            'blue' => 0.95
                        ],
                        'textFormat' => [
                            'bold' => true,
                            'fontSize' => 12
                        ],
                        'numberFormat' => [
                            'type' => 'CURRENCY',
                            'pattern' => '₴#,##0.00'
                        ],
                    ]
                ],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,numberFormat)'
            ]
        ];

        // Форматування стовпця з сумами витрат (B2:B1001)
        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => 1,
                    'endRowIndex' => 1001,
                    'startColumnIndex' => 1,
                    'endColumnIndex' => 2
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'numberFormat' => [
                            'type' => 'CURRENCY',
                            'pattern' => '₴#,##0.00'
                        ]
                    ]
                ],
                'fields' => 'userEnteredFormat.numberFormat'
            ]
        ];

        // Форматування стовпця дати (A2:A1001)
        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => 1,
                    'endRowIndex' => 1001,
                    'startColumnIndex' => 0,
                    'endColumnIndex' => 1
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'numberFormat' => [
                            'type' => 'DATE',
                            'pattern' => 'yyyy-mm-dd'
                        ]
                    ]
                ],
                'fields' => 'userEnteredFormat.numberFormat'
            ]
        ];

        // Додаємо легку сітку для основної таблиці
        $requests[] = [
            'updateBorders' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => 0,
                    'endRowIndex' => 1001,
                    'startColumnIndex' => 0,
                    'endColumnIndex' => 4
                ],
                'top' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8]
                ],
                'bottom' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8]
                ],
                'left' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8]
                ],
                'right' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8]
                ],
                'innerHorizontal' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.9, 'green' => 0.9, 'blue' => 0.9]
                ],
                'innerVertical' => [
                    'style' => 'SOLID',
                    'width' => 1,
                    'color' => ['red' => 0.9, 'green' => 0.9, 'blue' => 0.9]
                ]
            ]
        ];

        // Чергування кольору рядків для кращої читабельності
        $requests[] = [
            'addConditionalFormatRule' => [
                'rule' => [
                    'ranges' => [
                        [
                            'sheetId' => 0,
                            'startRowIndex' => 1,
                            'endRowIndex' => 1001,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => 4
                        ]
                    ],
                    'booleanRule' => [
                        'condition' => [
                            'type' => 'CUSTOM_FORMULA',
                            'values' => [
                                ['userEnteredValue' => '=MOD(ROW(),2)=0']
                            ]
                        ],
                        'format' => [
                            'backgroundColor' => [
                                'red' => 0.95,
                                'green' => 0.95,
                                'blue' => 1.0
                            ]
                        ]
                    ]
                ],
                'index' => 0
            ]
        ];

        $requests[] = [
            'updateDimensionProperties' => [
                'range' => [
                    'sheetId' => 0,
                    'dimension' => 'COLUMNS',
                    'startIndex' => 3,
                    'endIndex' => 4
                ],
                'properties' => [
                    'pixelSize' => 300
                ],
                'fields' => 'pixelSize'
            ]
        ];

        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => 1,
                    'endRowIndex' => 1001,
                    'startColumnIndex' => 3,
                    'endColumnIndex' => 4
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'wrapStrategy' => 'WRAP'
                    ]
                ],
                'fields' => 'userEnteredFormat.wrapStrategy'
            ]
        ];

        $requests[] = [
            'updateDimensionProperties' => [
                'range' => [
                    'sheetId' => 0,
                    'dimension' => 'COLUMNS',
                    // A=0, B=1, C=2 (endIndex 3 не включає колонку D)
                    'startIndex' => 0,
                    'endIndex' => 3
                ],
                'properties' => [
                    'pixelSize' => 120 // Підставте свій розмір
                ],
                'fields' => 'pixelSize'
            ]
        ];

        $requests[] = [
            'updateDimensionProperties' => [
                'range' => [
                    'sheetId' => 0,
                    'dimension' => 'COLUMNS',
                    // G=6 (endIndex 7 не включає колонку H)
                    'startIndex' => 6,
                    'endIndex' => 7
                ],
                'properties' => [
                    'pixelSize' => 150 // Підставте свій розмір
                ],
                'fields' => 'pixelSize'
            ]
        ];

        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
    }

    protected function createHeaderFormatRequest($startRowIndex, $startColumnIndex, $endRowIndex, $endColumnIndex): array
    {
        return [
            'repeatCell' => [
                'range' => [
                    'sheetId' => 0,
                    'startRowIndex' => $startRowIndex,
                    'endRowIndex' => $endRowIndex + 1,
                    'startColumnIndex' => $startColumnIndex,
                    'endColumnIndex' => $endColumnIndex + 1
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'backgroundColor' => [
                            'red' => 0.2,
                            'green' => 0.4,
                            'blue' => 0.8
                        ],
                        'textFormat' => [
                            'foregroundColor' => [
                                'red' => 1.0,
                                'green' => 1.0,
                                'blue' => 1.0
                            ],
                            'bold' => true,
                            'fontSize' => 12
                        ],
                        'horizontalAlignment' => 'CENTER',
                        'verticalAlignment' => 'MIDDLE'
                    ]
                ],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
            ]
        ];
    }

    public function addExpenseToSheet($amount, User $user, $category = null, $description = null): array
    {
        try {
            $date = date('Y-m-d');
            $client = $this->initializeClient();
            $sheetsService = new Google_Service_Sheets($client);

            $parsedUrl = parse_url($user->sheet_link);
            $spreadsheetId = explode('/', $parsedUrl['path'])[3];

            $sheetName = 'Витрати';
            $range = "{$sheetName}!A2:A1001";
            $response = $sheetsService->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues() ?? [];
            $nextRow = count($values) + 2;

            if ($nextRow > 1001) {
                return ['error' => 'Немає вільного місця у таблиці'];
            }

            $newValues = [
                [$date, $amount, $category ?: '', $description ?: '']
            ];

            $body = new Google_Service_Sheets_ValueRange(['values' => $newValues]);
            $params = ['valueInputOption' => 'USER_ENTERED'];

            $sheetsService->spreadsheets_values->update(
                $spreadsheetId,
                "{$sheetName}!A{$nextRow}:D{$nextRow}",
                $body,
                $params
            );

            if ($category) {
                $this->updateCategoryAnalytics($sheetsService, $spreadsheetId, $category);
            }

            return ['status' => 'success', 'message' => 'Витрати додано успішно'];
        } catch (\Exception $e) {
            Log::error('Error adding expense to sheet: ' . $e->getMessage());
            return ['error' => 'Помилка при додаванні витрат: ' . $e->getMessage()];
        }
    }

    protected function updateCategoryAnalytics($sheetsService, $spreadsheetId, $category): void
    {
        $sheetName = 'Витрати';

        $range = "{$sheetName}!F5:F20";
        $response = $sheetsService->spreadsheets_values->get($spreadsheetId, $range);
        $categories = $response->getValues() ?? [];

        $categoryExists = false;
        $categoryRow = 5;

        foreach ($categories as $index => $row) {
            if (isset($row[0]) && $row[0] === $category) {
                $categoryExists = true;
                $categoryRow = $index + 5;
                break;
            }
        }

        $params = ['valueInputOption' => 'USER_ENTERED'];

        if (!$categoryExists) {
            $categoryRow = count($categories) + 5;
            $newCategory = [[$category]];
            $categoryBody = new Google_Service_Sheets_ValueRange(['values' => $newCategory]);
            $sheetsService->spreadsheets_values->update(
                $spreadsheetId,
                "{$sheetName}!F{$categoryRow}",
                $categoryBody,
                $params
            );
        }

        $sumFormula = [["=SUMIF(C2:C1001,F{$categoryRow},B2:B1001)"]];
        $sumBody = new Google_Service_Sheets_ValueRange(['values' => $sumFormula]);
        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!G{$categoryRow}",
            $sumBody,
            $params
        );
    }
}
