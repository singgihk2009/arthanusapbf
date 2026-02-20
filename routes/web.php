<?php

use App\Http\Controllers\Apps\DashboardController;
use App\Http\Controllers\Apps\PermissionController;
use App\Http\Controllers\Apps\RoleController;
use App\Http\Controllers\Apps\UserController;
use App\Http\Controllers\Apps\MasterData\ItemController;
use App\Http\Controllers\Apps\MasterData\UomController;
use App\Http\Controllers\Apps\MasterData\CategoryController;
use App\Http\Controllers\Apps\MasterData\WarehouseController;
use App\Http\Controllers\Apps\MasterData\ItemBarcodeController;
use App\Http\Controllers\Apps\MasterData\ItemUomConversionController;
use App\Http\Controllers\Apps\MasterData\MinStockController;
use App\Http\Controllers\Apps\Reports\InventoryReportPageController;
use App\Http\Controllers\Apps\Inbound\ReceivingEntryController;
use App\Http\Controllers\Apps\Outbound\InternalUsageController;
use App\Http\Controllers\Apps\Outbound\StockAdjustmentController;
use App\Http\Controllers\Apps\Outbound\StockOpnameController;
use App\Http\Controllers\Apps\Transfer\WarehouseTransferController;
use App\Http\Controllers\Apps\InventoryPostingController;
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


    // inventory master data routes
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::resource('/warehouses', WarehouseController::class);
        Route::resource('/categories', CategoryController::class);
        Route::resource('/uoms', UomController::class);
        Route::resource('/items', ItemController::class);
        Route::resource('/conversions', ItemUomConversionController::class)->parameters(['conversions' => 'conversion']);
        Route::resource('/barcodes', ItemBarcodeController::class)->parameters(['barcodes' => 'barcode']);
        Route::resource('/min-stocks', MinStockController::class)->parameters(['min-stocks' => 'min_stock']);
    });

    // inventory report page
    Route::get('/reports/inventory', InventoryReportPageController::class)->name('reports.inventory.index');

    // inventory posting actions
    Route::post('/inventory/posting/grn/{goodsReceipt}', [InventoryPostingController::class, 'postGoodsReceipt'])->name('inventory.posting.grn');
    Route::post('/inventory/posting/receiving/{receivingEntry}', [InventoryPostingController::class, 'postReceivingEntry'])->name('inventory.posting.receiving');
    Route::post('/inventory/posting/transfer/{transferId}', [InventoryPostingController::class, 'postTransfer'])->name('inventory.posting.transfer');
    Route::post('/inventory/posting/sale/{saleId}', [InventoryPostingController::class, 'postSale'])->name('inventory.posting.sale');
    Route::post('/inventory/posting/usage/{usageId}', [InventoryPostingController::class, 'postInternalUsage'])->name('inventory.posting.usage');
    Route::post('/inventory/posting/adjustment/{adjustmentId}', [InventoryPostingController::class, 'postStockAdjustment'])->name('inventory.posting.adjustment');
    Route::post('/inventory/posting/opname/{stockOpname}', [StockOpnameController::class, 'post'])->name('inventory.posting.opname');
    Route::post('/inventory/posting/opening-balance', [InventoryPostingController::class, 'postOpeningBalance'])->name('inventory.posting.opening-balance');


    // opening balance page + import tools
    Route::get('/inventory/opening-balance', [InventoryPostingController::class, 'openingBalancePage'])->name('inventory.opening-balance.index');
    Route::post('/inventory/opening-balance/import', [InventoryPostingController::class, 'importOpeningBalance'])->name('inventory.opening-balance.import');
    Route::get('/inventory/opening-balance/template/csv', [InventoryPostingController::class, 'downloadOpeningBalanceTemplateCsv'])->name('inventory.opening-balance.template.csv');
    Route::get('/inventory/opening-balance/template/excel', [InventoryPostingController::class, 'downloadOpeningBalanceTemplateExcel'])->name('inventory.opening-balance.template.excel');

    // inbound receiving entry
    Route::get('/inbound/receiving', [ReceivingEntryController::class, 'index'])->name('inbound.receiving.index');
    Route::get('/inbound/receiving/create', [ReceivingEntryController::class, 'create'])->name('inbound.receiving.create');
    Route::post('/inbound/receiving', [ReceivingEntryController::class, 'store'])->name('inbound.receiving.store');
    Route::get('/inbound/receiving/{receivingEntry}/edit', [ReceivingEntryController::class, 'edit'])->name('inbound.receiving.edit');
    Route::put('/inbound/receiving/{receivingEntry}', [ReceivingEntryController::class, 'update'])->name('inbound.receiving.update');
    Route::delete('/inbound/receiving/{receivingEntry}', [ReceivingEntryController::class, 'destroy'])->name('inbound.receiving.destroy');
    Route::get('/inbound/receiving/export/excel', [ReceivingEntryController::class, 'exportExcel'])->name('inbound.receiving.export.excel');

    // outbound internal usage
    Route::get('/outbound/internal-usage', [InternalUsageController::class, 'index'])->name('outbound.internal-usage.index');
    Route::get('/outbound/internal-usage/create', [InternalUsageController::class, 'create'])->name('outbound.internal-usage.create');
    Route::post('/outbound/internal-usage', [InternalUsageController::class, 'store'])->name('outbound.internal-usage.store');
    Route::get('/outbound/internal-usage/{internalUsage}/edit', [InternalUsageController::class, 'edit'])->name('outbound.internal-usage.edit');
    Route::put('/outbound/internal-usage/{internalUsage}', [InternalUsageController::class, 'update'])->name('outbound.internal-usage.update');
    Route::delete('/outbound/internal-usage/{internalUsage}', [InternalUsageController::class, 'destroy'])->name('outbound.internal-usage.destroy');

    // outbound stock adjustment
    Route::get('/outbound/stock-adjustment', [StockAdjustmentController::class, 'index'])->name('outbound.stock-adjustment.index');
    Route::get('/outbound/stock-adjustment/create', [StockAdjustmentController::class, 'create'])->name('outbound.stock-adjustment.create');
    Route::post('/outbound/stock-adjustment', [StockAdjustmentController::class, 'store'])->name('outbound.stock-adjustment.store');
    Route::get('/outbound/stock-adjustment/{stockAdjustment}/edit', [StockAdjustmentController::class, 'edit'])->name('outbound.stock-adjustment.edit');
    Route::put('/outbound/stock-adjustment/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->name('outbound.stock-adjustment.update');
    Route::delete('/outbound/stock-adjustment/{stockAdjustment}', [StockAdjustmentController::class, 'destroy'])->name('outbound.stock-adjustment.destroy');

    // outbound stock opname
    Route::get('/outbound/stock-opname', [StockOpnameController::class, 'index'])->name('outbound.stock-opname.index');
    Route::get('/outbound/stock-opname/template/excel', [StockOpnameController::class, 'downloadTemplateExcel'])->name('outbound.stock-opname.template.excel');
    Route::post('/outbound/stock-opname/import/excel', [StockOpnameController::class, 'importExcel'])->name('outbound.stock-opname.import.excel');
    Route::get('/outbound/stock-opname/create', [StockOpnameController::class, 'create'])->name('outbound.stock-opname.create');
    Route::post('/outbound/stock-opname', [StockOpnameController::class, 'store'])->name('outbound.stock-opname.store');
    Route::get('/outbound/stock-opname/{stockOpname}/edit', [StockOpnameController::class, 'edit'])->name('outbound.stock-opname.edit');
    Route::put('/outbound/stock-opname/{stockOpname}', [StockOpnameController::class, 'update'])->name('outbound.stock-opname.update');
    Route::delete('/outbound/stock-opname/{stockOpname}', [StockOpnameController::class, 'destroy'])->name('outbound.stock-opname.destroy');

    // transfer antar gudang
    Route::get('/transfer/warehouse', [WarehouseTransferController::class, 'index'])->name('transfer.warehouse.index');
    Route::get('/transfer/warehouse/create', [WarehouseTransferController::class, 'create'])->name('transfer.warehouse.create');
    Route::post('/transfer/warehouse', [WarehouseTransferController::class, 'store'])->name('transfer.warehouse.store');
    Route::get('/transfer/warehouse/{warehouseTransfer}/edit', [WarehouseTransferController::class, 'edit'])->name('transfer.warehouse.edit');
    Route::put('/transfer/warehouse/{warehouseTransfer}', [WarehouseTransferController::class, 'update'])->name('transfer.warehouse.update');
    Route::delete('/transfer/warehouse/{warehouseTransfer}', [WarehouseTransferController::class, 'destroy'])->name('transfer.warehouse.destroy');

    // inventory reports api
    Route::prefix('reports/inventory')->name('reports.inventory.')->group(function () {
        Route::get('/stock-balance', [InventoryReportController::class, 'stockBalance'])->name('stock-balance');
        Route::get('/stock-card', [InventoryReportController::class, 'stockCard'])->name('stock-card');
        Route::get('/expired-soon', [InventoryReportController::class, 'expiredSoon'])->name('expired-soon');
        Route::get('/minimum-stock-alerts', [InventoryReportController::class, 'minimumStockAlerts'])->name('minimum-stock-alerts');
    });
});

require __DIR__.'/auth.php';
