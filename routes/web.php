<?php

use App\Http\Controllers\PromptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PromptController::class, 'index'])->name('prompts.index');

Route::post('/prompts', [PromptController::class, 'store'])->name('prompts.store');
