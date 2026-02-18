<?php

use App\Http\Controllers\Apps\DashboardController;
use App\Http\Controllers\Apps\PermissionController;
use App\Http\Controllers\Apps\RoleController;
use App\Http\Controllers\Apps\UserController;
use App\Http\Controllers\Apps\Reports\InventoryReportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::group(['prefix' => 'apps', 'as' => 'apps.' , 'middleware' => ['auth']], function(){
    // dashboard route
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // permissions route
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    // roles route
    Route::resource('/roles', RoleController::class)->except(['create', 'edit', 'show']);
    // users route
    Route::resource('/users', UserController::class)->except('show');

    // inventory reports api
    Route::prefix('reports/inventory')->name('reports.inventory.')->group(function () {
        Route::get('/stock-balance', [InventoryReportController::class, 'stockBalance'])->name('stock-balance');
        Route::get('/stock-card', [InventoryReportController::class, 'stockCard'])->name('stock-card');
        Route::get('/expired-soon', [InventoryReportController::class, 'expiredSoon'])->name('expired-soon');
        Route::get('/minimum-stock-alerts', [InventoryReportController::class, 'minimumStockAlerts'])->name('minimum-stock-alerts');
    });
});

require __DIR__.'/auth.php';
