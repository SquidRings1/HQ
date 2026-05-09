<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/me/events', [EventController::class, 'mine']);

    Route::middleware('throttle:join')->group(function () {
        Route::post('/events/{event}/join', [EventController::class, 'join'])->whereNumber('event');
        Route::delete('/events/{event}/join', [EventController::class, 'cancel'])->whereNumber('event');
    });
});
