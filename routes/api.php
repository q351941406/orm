<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('/test', [\App\Http\Controllers\MainController::class, 'test']);
Route::any('/es_install_data', [\App\Http\Controllers\MainController::class, 'es_install_data']);
Route::post('/get_not_keyword_enemy_for_enemyBot', [\App\Http\Controllers\MainController::class, 'get_not_keyword_enemy_for_enemyBot']);
Route::post('/get_unsend_for_text', [\App\Http\Controllers\MainController::class, 'get_unsend_for_text']);

Route::post('/updateInfo', [\App\Http\Controllers\MainController::class, 'updateInfo']);
Route::post('/save_private_keyword', [\App\Http\Controllers\MainController::class, 'save_private_keyword']);
Route::post('/get_uninsertLink', [\App\Http\Controllers\MainController::class, 'get_uninsertLink']);
Route::post('/get_account', [\App\Http\Controllers\MainController::class, 'get_account']);
Route::post('/update_account_status', [\App\Http\Controllers\MainController::class, 'update_account_status']);
Route::post('/batch_update', [\App\Http\Controllers\MainController::class, 'batch_update']);
Route::post('/link_delete', [\App\Http\Controllers\MainController::class, 'link_delete']);
Route::post('/update', [\App\Http\Controllers\MainController::class, 'update']);
Route::post('/save_account', [\App\Http\Controllers\MainController::class, 'save_account']);
Route::any('/get_need_update_groupList', [\App\Http\Controllers\MainController::class, 'get_need_update_groupList']);
Route::post('/updateMessage', [\App\Http\Controllers\MainController::class, 'updateMessage']);
