<?php

use App\Http\Controllers\Api\TextController;
use App\Http\Controllers\Api\UserController;
use App\OpenApi\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

if (app()->environment('local', 'testing')) {
    Route::get('/openapi-json', [SwaggerController::class, 'json'])
        ->name('openapi-json')
        ->middleware('moonshine.basic');
}

Route::get('/texts', [TextController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('profile', [UserController::class, 'profile']);
});
