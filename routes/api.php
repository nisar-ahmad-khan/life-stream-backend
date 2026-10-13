<?php

use App\Http\Controllers\DonorController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication and donor endpoints
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::get('/donors', [DonorController::class, 'index']);

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/user', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile', [UserController::class, 'updateProfile']);
    Route::put('/user', [UserController::class, 'updateProfile']);
    Route::delete('/profile', [UserController::class, 'deleteProfile']);
    Route::delete('/user', [UserController::class, 'deleteProfile']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::post('/donors', [DonorController::class, 'store']);
});
