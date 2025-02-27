<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExpenseBotController;
use App\Services\GoogleService;
use App\Services\OpenAiService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Route::post('/webhook', [ExpenseBotController::class, 'handleMessage']);
Route::post('/webhook', [ExpenseBotController::class, 'webhook']);
Route::get('/test', [GoogleService::class, 'createCustomSheet']);
Route::post('/analyze', [OpenAiService::class, 'analyze']);
Route::get('/test/add', [GoogleService::class, 'addExpenseToSheet']);
Route::get('/start', [ExpenseBotController::class, 'start']);
Route::get('/updateTelegramWebhook', [ExpenseBotController::class, 'updateTelegramWebhook']);
