<?php

use App\Http\Controllers\PromptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PromptController::class, 'index'])->name('prompts.index');

Route::post('/prompts', [PromptController::class, 'store'])->name('prompts.store');

Route::post('/prompts/stream', [PromptController::class, 'stream'])
    ->name('prompts.stream');

Route::post('/prompts/review', [PromptController::class, 'review'])
    ->name('prompts.review');
