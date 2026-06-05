<?php

use App\Http\Controllers\MortalityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/mortality/cases', [MortalityController::class, 'index']);
    Route::get('/mortality/pending', [MortalityController::class, 'pendingApprovals']);
    Route::get('/mortality/pending/counts', [MortalityController::class, 'getPendingCounts']);
    Route::get('/mortality/case/{id}', [MortalityController::class, 'show']);
    Route::post('/mortality/case/{id}/approve', [MortalityController::class, 'approve']);
    Route::post('/mortality', [MortalityController::class, 'store']);
    Route::post('/mortality/pm-examination/{mortalityCaseId}', [MortalityController::class, 'storePmExamination']);
    Route::get('/mortality/cases/pending-exam', [MortalityController::class, 'pmExamination']);
    Route::get('/mortality/cases/completed', [MortalityController::class, 'completedCases']);
});
