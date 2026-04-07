<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPrivateDetailController;
use App\Http\Controllers\LostItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//protected routes
Route::middleware('auth:sanctum')->group(function () {
Route::post('/logout', [AuthController::class, 'logout']);
//items
Route::resource('items', ItemController::class);
Route::post('/items/{id}/private-details', [ItemPrivateDetailController::class, 'store']);
Route::get('/items/{id}/private-details', [ItemPrivateDetailController::class, 'show']);
Route::resource('lost-items', LostItemController::class);
});
