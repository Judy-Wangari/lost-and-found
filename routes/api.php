<?php

use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\AppealController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPrivateDetailController;
use App\Http\Controllers\LostItemController;
use App\Http\Controllers\LostItemPrivateDetailController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SecurityHandoverController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//protected routes
Route::middleware('auth:sanctum')->group(function () {

Route::post('/logout', [AuthController::class, 'logout']);

//found items
Route::resource('items', ItemController::class);
Route::post('/items/{id}/private-details', [ItemPrivateDetailController::class, 'store']);
Route::get('/items/{id}/private-details', [ItemPrivateDetailController::class, 'show']);

//lost items
Route::resource('lost-items', LostItemController::class);
Route::post('/lost-items/{id}/private-details', [LostItemPrivateDetailController::class, 'store']);
Route::get('/lost-items/{id}/private-details', [LostItemPrivateDetailController::class, 'show']);
Route::post('/lost-items/{id}/match-found-post', [LostItemController::class, 'matchFoundPost']);

//claim
Route::resource('claims', ClaimController::class);
Route::post('/claims/{id}/undo-approval', [ClaimController::class, 'undoApproval']);
Route::post('/claims/{id}/collect-direct', [ClaimController::class, 'collectDirect']);
Route::post('/claims/{id}/submit-clarification', [ClaimController::class, 'submitClarification']);

//appeal
Route::resource('appeals', AppealController::class);

//message
Route::get('/messages/{claimId}', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);

//admin messages
Route::get('/admin-messages', [AdminMessageController::class, 'index']);
Route::post('/admin-messages', [AdminMessageController::class, 'store']);

//notification
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::get('/notifications/{id}', [NotificationController::class, 'show']);
Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

//security
Route::get('/security-handovers', [SecurityHandoverController::class, 'index']);
Route::post('/security-handovers', [SecurityHandoverController::class, 'store']);
Route::put('/security-handovers/{id}', [SecurityHandoverController::class, 'update']);

//user account approval
Route::get('/pending-accounts', [UserController::class, 'getPendingAccounts']);
Route::put('/users/{id}/approve-account', [UserController::class, 'approveAccount']);

//user profile
Route::get('/profile', [UserController::class, 'getProfile']);
Route::put('/profile', [UserController::class, 'updateProfile']);

//user management (admin only)
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

});