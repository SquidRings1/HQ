<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MobileShimController;
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

// gocyc mobile-app compatibility shim — see MobileShimController for the rationale.
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [MobileShimController::class, 'register']);
    Route::post('/login', [MobileShimController::class, 'login']);
    Route::post('/validate-user', [MobileShimController::class, 'validateUser']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/get-event', [MobileShimController::class, 'getEvents']);
    Route::post('/get-event-detail', [MobileShimController::class, 'getEventDetail']);
    Route::get('/my-join-events', [MobileShimController::class, 'myJoinedEvents']);
    Route::middleware('throttle:join')->group(function () {
        Route::post('/add-interested', [MobileShimController::class, 'joinEvent']);
        Route::post('/v2_add-interested', [MobileShimController::class, 'joinEvent']);
    });
});
