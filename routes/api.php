<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;

Route::middleware('auth:sanctum')->group(function () {
    // Files
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{file}', [FileController::class, 'show']);
    Route::get('/files/{file}/download', [FileController::class, 'download']);
    Route::get('/files/{file}/thumbnail/{size?}', [FileController::class, 'thumbnail']);
    Route::delete('/files/{file}', [FileController::class, 'destroy']);
    Route::get('/files/statistics', [FileController::class, 'statistics']);
    Route::post('/files/bulk-delete', [FileController::class, 'bulkDelete']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/messages', [MessageController::class, 'index']);
});
