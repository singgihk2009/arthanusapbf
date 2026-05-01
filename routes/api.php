<?php

use App\Http\Controllers\Api\Integration\FinanceHubIntegrationController;
use App\Http\Controllers\Apps\MasterData\RegulatoryProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('integration')->group(function () {
    Route::get('/outbox', [FinanceHubIntegrationController::class, 'pullOutbox']);
    Route::post('/outbox/{id}/mark-sent', [FinanceHubIntegrationController::class, 'markSent']);
    Route::post('/gl/mark-posted', [FinanceHubIntegrationController::class, 'markPosted']);
    Route::post('/gl/mark-error', [FinanceHubIntegrationController::class, 'markError']);
});

Route::get('/regulatory-products/search', [RegulatoryProductController::class, 'search'])->middleware('auth');
