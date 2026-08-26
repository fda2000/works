<?php

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::post('message/send', [MessageController::class, 'send']);
    Route::get('message/status/{user}', [MessageController::class, 'status']);
});
