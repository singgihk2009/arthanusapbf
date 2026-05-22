<?php

use App\Http\Controllers\Apps\DashboardController;
use App\Http\Controllers\Apps\PermissionController;
use App\Http\Controllers\Apps\RoleController;
use App\Http\Controllers\Apps\UserController;
use App\Http\Controllers\Apps\MasterData\ItemController;
use App\Http\Controllers\Apps\MasterData\UomController;
use App\Http\Controllers\Apps\MasterData\CategoryController;
use App\Http\Controllers\Apps\MasterData\WarehouseController;
use App\Http\Controllers\Apps\MasterData\FacilitySchemeController;
use App\Http\Controllers\Apps\MasterData\ItemBarcodeController;
use App\Http\Controllers\Apps\MasterData\ItemUomConversionController;
use App\Http\Controllers\Apps\MasterData\MinStockController;
use App\Http\Controllers\Apps\MasterData\ItemPictureController;
use App\Http\Controllers\Apps\MasterData\RegulatoryProductController;
use App\Http\Controllers\Apps\MasterData\RegulatorySourceController;
use App\Http\Controllers\Apps\MasterData\RegulatoryDocumentController;
use App\Http\Controllers\Apps\Reports\InventoryReportPageController;
use App\Http\Controllers\Apps\Inbound\ReceivingEntryController;
use App\Http\Controllers\Apps\Outbound\InternalUsageController;
use App\Http\Controllers\Apps\Outbound\StockAdjustmentController;
use App\Http\Controllers\Apps\Outbound\StockOpnameController;
use App\Http\Controllers\Apps\Transfer\WarehouseTransferController;
use App\Http\Controllers\Apps\InventoryPostingController;
use App\Http\Controllers\Apps\Reports\InventoryReportController;
use App\Http\Controllers\Apps\Reports\FacilityMovementReportController;
use App\Http\Controllers\Apps\Integration\IntegrationController;
use App\Http\Controllers\Apps\Procurement\VendorController;
use App\Http\Controllers\Apps\Procurement\PurchaseOrderController;
use App\Http\Controllers\Apps\Procurement\GoodsReceiptController;
use App\Http\Controllers\Apps\Procurement\VendorInvoiceController;
use App\Http\Controllers\Apps\Procurement\VendorPaymentController;
use App\Http\Controllers\Apps\Procurement\VendorLedgerController;
use App\Http\Controllers\Apps\Procurement\DocumentTypeController;
use App\Http\Controllers\Apps\Procurement\VendorDocumentRequirementController;
use App\Http\Controllers\Apps\Procurement\VendorContactController;
use App\Http\Controllers\Apps\DocumentRequirementController;
use App\Http\Controllers\Apps\DocumentMonitoringController;
use App\Http\Controllers\Apps\DocumentCenterDocumentController;
use App\Http\Controllers\Apps\CompanyProfileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    // Compatibility alias for Breeze/Fortify default route name used by Ziggy/frontend
    Route::redirect('/dashboard', '/apps/dashboard')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth'])->group(function () {
    Route::redirect('/setup/company-profile', '/apps/setup/company-profile', 301);
    Route::put('/setup/company-profile', [CompanyProfileController::class, 'update'])->name('setup.company-profile.update.alias');
    Route::post('/setup/company-profile/logo', [CompanyProfileController::class, 'uploadLogo'])->name('setup.company-profile.logo.upload.alias');
    Route::delete('/setup/company-profile/logo', [CompanyProfileController::class, 'deleteLogo'])->name('setup.company-profile.logo.delete.alias');
});

Route::group(['prefix' => 'apps', 'as' => 'apps.' , 'middleware' => ['auth', 'restrict_inventory_reports_access']], function(){
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
        Route::get('/items/export/excel', [ItemController::class, 'exportExcel'])->name('items.export.excel');
        Route::get('/items/template/excel', [ItemController::class, 'downloadTemplateExcel'])->name('items.template.excel');
        Route::post('/items/import/excel', [ItemController::class, 'importExcel'])->name('items.import.excel');
        Route::resource('/items', ItemController::class);
        Route::resource('/facility-schemes', FacilitySchemeController::class)->except(['create','edit','show']);
        Route::redirect('/facility-scheme', '/apps/master-data/facility-schemes', 301);
        Route::redirect('/facilities', '/apps/master-data/facility-schemes', 301);
        Route::redirect('/fasilitas', '/apps/master-data/facility-schemes', 301);
        Route::resource('/conversions', ItemUomConversionController::class)->parameters(['conversions' => 'conversion']);
        Route::resource('/barcodes', ItemBarcodeController::class)->parameters(['barcodes' => 'barcode']);
        Route::resource('/min-stocks', MinStockController::class)->parameters(['min-stocks' => 'min_stock']);
        Route::get('/pictures', [ItemPictureController::class, 'index'])->name('pictures.index');
        Route::post('/items/{item}/pictures', [ItemPictureController::class, 'store'])->name('pictures.store');
        Route::patch('/items/{item}/pictures/default', [ItemPictureController::class, 'setDefault'])->name('pictures.default');
        Route::delete('/items/{item}/pictures/{picture}', [ItemPictureController::class, 'destroy'])->name('pictures.destroy');

        // Backward-compatible aliases (old singular URLs)
        Route::redirect('/regulatory-source', '/apps/master-data/regulatory-sources', 301);
        Route::redirect('/regulatory-product', '/apps/master-data/regulatory-products', 301);
        Route::redirect('/regulartory-products', '/apps/master-data/regulatory-products', 301);
        Route::redirect('/regulartory-products/template/excel', '/apps/master-data/regulatory-products/template/excel', 301);
        Route::redirect('/regulartory-products/import/excel', '/apps/master-data/regulatory-products/import/excel', 301);

        Route::resource('/regulatory-sources', RegulatorySourceController::class)->parameters(['regulatory-sources' => 'regulatorySource']);
        Route::resource('/regulatory-documents', RegulatoryDocumentController::class)->parameters(['regulatory-documents' => 'regulatoryDocument']);
        Route::get('/regulatory-products/export/excel', [RegulatoryProductController::class, 'exportExcel'])->name('regulatory-products.export.excel');
        Route::get('/regulatory-products/search', [RegulatoryProductController::class, 'search'])->name('regulatory-products.search');
        Route::resource('/regulatory-products', RegulatoryProductController::class)->parameters(['regulatory-products' => 'regulatoryProduct']);
        Route::get('/regulatory-products/template/excel', [RegulatoryProductController::class, 'downloadTemplateExcel'])->name('regulatory-products.template.excel');
        Route::post('/regulatory-products/import/excel', [RegulatoryProductController::class, 'importExcel'])->name('regulatory-products.import.excel');
        Route::get('/regulatory-product/template/excel', [RegulatoryProductController::class, 'downloadTemplateExcel']);
        Route::post('/regulatory-product/import/excel', [RegulatoryProductController::class, 'importExcel']);
        Route::get('/regulatory-products/import-bpom', [RegulatoryProductController::class, 'create'])->name('regulatory-products.import-bpom');
        Route::post('/regulatory-products/import-bpom', [RegulatoryProductController::class, 'importBpom'])->name('regulatory-products.import.bpom');
        Route::get('/regulatory-products/import-kemenkes-drug', [RegulatoryProductController::class, 'create'])->name('regulatory-products.import-kemenkes-drug');
        Route::post('/regulatory-products/import-kemenkes-drug', [RegulatoryProductController::class, 'importKemenkes'])->name('regulatory-products.import.kemenkes-drug');
        Route::get('/regulatory-products/import-alkes', [RegulatoryProductController::class, 'create'])->name('regulatory-products.import-alkes');
        Route::post('/regulatory-products/import-alkes', [RegulatoryProductController::class, 'importKemenkesAlkes'])->name('regulatory-products.import.alkes');
        Route::get('/regulatory-products/import-alkes/template', [RegulatoryProductController::class, 'downloadTemplateAlkesExcel'])->name('regulatory-products.import-alkes.template');
        Route::post('/regulatory-products/mapping/attach', [RegulatoryProductController::class, 'attach'])->name('regulatory-products.mapping.attach');
        Route::post('/regulatory-products/mapping/detach', [RegulatoryProductController::class, 'detach'])->name('regulatory-products.mapping.detach');
        Route::post('/regulatory-products/mapping/set-primary', [RegulatoryProductController::class, 'setPrimary'])->name('regulatory-products.mapping.set-primary');
        Route::get('/regulatory-products/{regulatoryProduct}/candidates', [RegulatoryProductController::class, 'candidates'])->name('regulatory-products.candidates');
        Route::get('/items/{item}/regulatory-products', [ItemController::class, 'edit'])->name('items.regulatory-products.index');
        Route::post('/items/{item}/regulatory-products', [ItemController::class, 'updateRegulatoryProduct'])->name('items.regulatory-products.attach');
        Route::delete('/items/{item}/regulatory-products/{regulatoryProduct}', [ItemController::class, 'removeRegulatoryProduct'])->name('items.regulatory-products.detach');
        Route::patch('/items/{item}/regulatory-products/{regulatoryProduct}/set-primary', [ItemController::class, 'setPrimaryRegulatoryProduct'])->name('items.regulatory-products.set-primary');
    });

    // inventory report page
    Route::get('/reports/inventory', InventoryReportPageController::class)->name('reports.inventory.index');
    Route::get('/reports/inventory/export/excel', [InventoryReportPageController::class, 'exportStockBalanceExcel'])->name('reports.inventory.export.excel');
    Route::get('/reports/inventory/search/items', [InventoryReportPageController::class, 'searchItems'])->name('reports.inventory.search-items');
    Route::get('/reports/facility-movements', FacilityMovementReportController::class)->name('reports.facility-movements.index');

    // inventory posting actions
    Route::post('/inventory/posting/grn/{goodsReceipt}', [InventoryPostingController::class, 'postGoodsReceipt'])->name('inventory.posting.grn');
    Route::post('/inventory/posting/receiving/{receivingEntry}', [InventoryPostingController::class, 'postReceivingEntry'])->name('inventory.posting.receiving');
    Route::post('/inventory/unposting/receiving/{receivingEntry}', [InventoryPostingController::class, 'unpostReceivingEntry'])->name('inventory.unposting.receiving');
    Route::post('/inventory/posting/transfer/{transferId}', [InventoryPostingController::class, 'postTransfer'])->name('inventory.posting.transfer');
    Route::post('/inventory/unposting/transfer/{transferId}', [InventoryPostingController::class, 'unpostTransfer'])->name('inventory.unposting.transfer');
    Route::post('/inventory/posting/sale/{saleId}', [InventoryPostingController::class, 'postSale'])->name('inventory.posting.sale');
    Route::post('/inventory/posting/usage/{usageId}', [InventoryPostingController::class, 'postInternalUsage'])->name('inventory.posting.usage');
    Route::post('/inventory/unposting/usage/{usageId}', [InventoryPostingController::class, 'unpostInternalUsage'])->name('inventory.unposting.usage');
    Route::post('/inventory/posting/adjustment/{adjustmentId}', [InventoryPostingController::class, 'postStockAdjustment'])->name('inventory.posting.adjustment');
    Route::post('/inventory/posting/opname/{stockOpname}', [StockOpnameController::class, 'post'])->name('inventory.posting.opname');
    Route::post('/inventory/posting/opening-balance', [InventoryPostingController::class, 'postOpeningBalance'])->name('inventory.posting.opening-balance');


    // inventory item list + 360 inventory card
    Route::get('/inventory/item-cards/{item}', [ItemController::class, 'inventoryCard'])->name('inventory.items.card');

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


    // integration finance hub
    
    Route::get('/setup/company-profile', [CompanyProfileController::class, 'index'])->name('setup.company-profile.index');
    Route::put('/setup/company-profile', [CompanyProfileController::class, 'update'])->name('setup.company-profile.update');
    Route::post('/setup/company-profile/logo', [CompanyProfileController::class, 'uploadLogo'])->name('setup.company-profile.logo.upload');
    Route::delete('/setup/company-profile/logo', [CompanyProfileController::class, 'deleteLogo'])->name('setup.company-profile.logo.delete');

    Route::get('/integration', [IntegrationController::class, 'index'])->name('integration.index');
    Route::get('/integration/export/csv', [IntegrationController::class, 'exportCsv'])->name('integration.export.csv');
    Route::post('/integration/{transactionId}/retry', [IntegrationController::class, 'retry'])->name('integration.retry');

    // inventory reports api
    Route::prefix('reports/inventory')->name('reports.inventory.')->group(function () {
        Route::get('/stock-balance', [InventoryReportController::class, 'stockBalance'])->name('stock-balance');
        Route::get('/stock-card', [InventoryReportController::class, 'stockCard'])->name('stock-card');
        Route::get('/expired-soon', [InventoryReportController::class, 'expiredSoon'])->name('expired-soon');
        Route::get('/minimum-stock-alerts', [InventoryReportController::class, 'minimumStockAlerts'])->name('minimum-stock-alerts');
    });

    Route::prefix('document-center')->name('document-center.')->group(function () {
        Route::get('/requirement-setup', [DocumentRequirementController::class, 'setupPage'])->name('requirements.setup');
        Route::get('/requirements', [DocumentRequirementController::class, 'index'])->name('requirements.index');
        Route::post('/requirements', [DocumentRequirementController::class, 'store'])->name('requirements.store');
        Route::put('/requirements/{requirement}', [DocumentRequirementController::class, 'update'])->name('requirements.update');
        Route::delete('/requirements/{requirement}', [DocumentRequirementController::class, 'destroy'])->name('requirements.destroy');
        Route::post('/requirements/bulk-save', [DocumentRequirementController::class, 'bulkSave'])->name('requirements.bulk-save');
        Route::get('/requirements/owner-types', [DocumentRequirementController::class, 'ownerTypes'])->name('requirements.owner-types');
        Route::get('/requirements/{ownerType}/matrix', [DocumentRequirementController::class, 'matrix'])->name('requirements.matrix');
    });
    Route::get('/documents/owners/{ownerType}/{ownerId}/completion', [DocumentRequirementController::class, 'completion'])->name('documents.owner.completion');
    Route::get('/document-center/expiry-monitoring', [DocumentMonitoringController::class, 'expiryPage'])->name('document-center.expiry-page');
    Route::get('/document-center/missing-required-documents', [DocumentMonitoringController::class, 'missingPage'])->name('document-center.missing-page');
    Route::get('/document-center/expiring-soon', [DocumentMonitoringController::class, 'expiringSoon'])->name('document-center.expiring-soon');
    Route::get('/document-center/missing-required', [DocumentMonitoringController::class, 'missingRequired'])->name('document-center.missing-required');
    Route::post('/document-center/documents', [DocumentCenterDocumentController::class, 'store'])->name('document-center.documents.store');
    Route::post('/document-center/documents/{document}/revision', [DocumentCenterDocumentController::class, 'revision'])->name('document-center.documents.revision');
    Route::post('/document-center/documents/{document}/renewal', [DocumentCenterDocumentController::class, 'renewal'])->name('document-center.documents.renewal');
    Route::get('/document-center/documents/{document}/versions', [DocumentCenterDocumentController::class, 'versions'])->name('document-center.documents.versions');
    Route::get('/document-center/documents/pending-review', [DocumentCenterDocumentController::class, 'pendingReviewPage'])->name('document-center.documents.pending-review.page');
    Route::get('/document-center/documents/pending-review/list', [DocumentCenterDocumentController::class, 'pendingReviewList'])->name('document-center.documents.pending-review.list');
    Route::get('/document-center/documents/{document}', [DocumentCenterDocumentController::class, 'show'])->name('document-center.documents.show');
    Route::get('/document-center/documents/{document}/download', [DocumentCenterDocumentController::class, 'download'])->name('document-center.documents.download');
    Route::get('/document-center/documents/{document}/audit-logs', [DocumentCenterDocumentController::class, 'auditLogs'])->name('document-center.documents.audit-logs');
    Route::post('/document-center/documents/{document}/verify', [DocumentCenterDocumentController::class, 'verify'])->name('document-center.documents.verify');
    Route::post('/document-center/documents/{document}/reject', [DocumentCenterDocumentController::class, 'reject'])->name('document-center.documents.reject');

    // Backward-compatible aliases under /apps prefix (old/typo procurement URLs)
    Route::redirect('/procureme', '/apps/procurement/vendors', 301);
    Route::redirect('/procureme/vendors', '/apps/procurement/vendors', 301);
    Route::redirect('/procureme/vendors/create', '/apps/procurement/vendors/create', 301);
    Route::redirect('/procuremen/vendors', '/apps/procurement/vendors', 301);
    Route::redirect('/procuremen/vendors/create', '/apps/procurement/vendors/create', 301);

    // Backward-compatible aliases (old/typo procurement URLs)
    Route::redirect('/procurement', '/apps/procurement/vendors', 301);
    Route::redirect('/procureme', '/apps/procurement/vendors', 301);
    Route::redirect('/procureme/vendors', '/apps/procurement/vendors', 301);
    Route::redirect('/procureme/vendor', '/apps/procurement/vendors', 301);
    Route::redirect('/procuremen/vendors', '/apps/procurement/vendors', 301);
    Route::redirect('/procuremen/vendor', '/apps/procurement/vendors', 301);
    Route::redirect('/procurement/vendor', '/apps/procurement/vendors', 301);

    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/vendors/qualification-report', [VendorController::class, 'qualificationReport'])->name('vendors.qualification-report');
        Route::get('/vendors/template/excel', [VendorController::class, 'downloadTemplateExcel'])->name('vendors.template.excel');
        Route::post('/vendors/import/excel', [VendorController::class, 'importExcel'])->name('vendors.import.excel');
        Route::post('/vendors/{vendor}/submit-qualification', [VendorController::class, 'submitQualification'])->name('vendors.submit-qualification');
        Route::post('/vendors/{vendor}/approve-qualification', [VendorController::class, 'approveQualification'])->name('vendors.approve-qualification');
        Route::post('/vendors/{vendor}/reject-qualification', [VendorController::class, 'rejectQualification'])->name('vendors.reject-qualification');
        Route::post('/vendors/{vendor}/documents', [VendorController::class, 'uploadDocument'])->name('vendors.documents.upload');
        Route::delete('/vendors/{vendor}/documents/{document}', [VendorController::class, 'deleteDocument'])->name('vendors.documents.delete');
        Route::post('/vendors/{vendor}/documents/{document}/verify', [VendorController::class, 'verifyDocument'])->name('vendors.documents.verify');
        Route::post('/vendors/{vendor}/documents/{document}/submit', [VendorController::class, 'submitDocument'])->name('vendors.documents.submit');
        Route::post('/vendors/{vendor}/documents/{document}/reject', [VendorController::class, 'rejectDocument'])->name('vendors.documents.reject');
        Route::post('/vendors/{vendor}/documents/{document}/archive', [VendorController::class, 'archiveDocument'])->name('vendors.documents.archive');
        Route::post('/vendors/{vendor}/documents/{document}/restore', [VendorController::class, 'restoreDocument'])->name('vendors.documents.restore');
        Route::get('/vendors/{vendor}/documents/{document}/download', [VendorController::class, 'downloadDocument'])->name('vendors.documents.download');
        Route::get('/vendors/{vendor}/overview', [VendorController::class, 'overview'])->name('vendors.overview');
        Route::get('/vendors/{vendor}/profile', [VendorController::class, 'profile'])->name('vendors.profile');
        Route::put('/vendors/{vendor}/profile', [VendorController::class, 'updateProfile'])->name('vendors.profile.update');
        Route::delete('/vendors/{vendor}/profile', [VendorController::class, 'deleteProfile'])->name('vendors.profile.delete');
        Route::get('/vendors/{vendor}/legal', [VendorController::class, 'legal'])->name('vendors.legal');
        Route::get('/vendors/{vendor}/contacts', [VendorController::class, 'contacts'])->name('vendors.contacts');
        Route::get('/vendors/{vendor}/party-contacts', [VendorContactController::class, 'index'])->name('vendors.party-contacts.index');
        Route::post('/vendors/{vendor}/party-contacts', [VendorContactController::class, 'store'])->name('vendors.party-contacts.store');
        Route::put('/vendors/{vendor}/party-contacts/{partyContact}', [VendorContactController::class, 'update'])->name('vendors.party-contacts.update');
        Route::delete('/vendors/{vendor}/party-contacts/{partyContact}', [VendorContactController::class, 'destroy'])->name('vendors.party-contacts.destroy');
        Route::post('/vendors/{vendor}/party-contacts/{partyContact}/set-primary', [VendorContactController::class, 'setPrimary'])->name('vendors.party-contacts.set-primary');
        Route::post('/vendors/{vendor}/party-contacts/{partyContact}/toggle-status', [VendorContactController::class, 'toggleStatus'])->name('vendors.party-contacts.toggle-status');
        Route::post('/vendors/{vendor}/party-contacts/{partyContact}/toggle-can-login', [VendorContactController::class, 'toggleCanLogin'])->name('vendors.party-contacts.toggle-can-login');
        Route::post('/vendors/{vendor}/party-contacts/{partyContact}/create-user-login', [VendorContactController::class, 'createUserLogin'])->name('vendors.party-contacts.create-user-login');
        Route::get('/vendors/{vendor}/documents', [VendorController::class, 'documents'])->name('vendors.documents');
        Route::get('/vendors/{vendor}/purchase-orders', [VendorController::class, 'purchaseOrders'])->name('vendors.purchase-orders');
        Route::get('/vendors/{vendor}/receivings', [VendorController::class, 'receivings'])->name('vendors.receivings');
        Route::get('/vendors/{vendor}/invoices', [VendorInvoiceController::class, 'index'])->name('vendors.invoices');
        Route::get('/vendors/{vendor}/invoices/create', [VendorInvoiceController::class, 'create'])->name('vendors.invoices.create');
        Route::post('/vendors/{vendor}/invoices', [VendorInvoiceController::class, 'store'])->name('vendors.invoices.store');
        Route::get('/vendors/{vendor}/payments', [VendorController::class, 'payments'])->name('vendors.payments');
        Route::get('/vendors/{vendor}/payments/create', [VendorPaymentController::class, 'create'])->name('vendors.payments.create');
        Route::post('/vendors/{vendor}/payments', [VendorPaymentController::class, 'store'])->name('vendors.payments.store');
        Route::get('/vendors/{vendor}/payments/{payment}', [VendorPaymentController::class, 'show'])->name('vendors.payments.show');
        Route::get('/vendors/{vendor}/payments/{payment}/edit', [VendorPaymentController::class, 'edit'])->name('vendors.payments.edit');
        Route::put('/vendors/{vendor}/payments/{payment}', [VendorPaymentController::class, 'update'])->name('vendors.payments.update');
        Route::post('/vendors/{vendor}/payments/{payment}/submit', [VendorPaymentController::class, 'submit'])->name('vendors.payments.submit');
        Route::post('/vendors/{vendor}/payments/{payment}/approve', [VendorPaymentController::class, 'approve'])->name('vendors.payments.approve');
        Route::post('/vendors/{vendor}/payments/{payment}/mark-as-paid', [VendorPaymentController::class, 'markAsPaid'])->name('vendors.payments.mark-as-paid');
        Route::post('/vendors/{vendor}/payments/{payment}/post', [VendorPaymentController::class, 'post'])->name('vendors.payments.post');
        Route::post('/vendors/{vendor}/payments/{payment}/cancel', [VendorPaymentController::class, 'cancel'])->name('vendors.payments.cancel');
        Route::get('/vendors/{vendor}/ledger', [VendorController::class, 'ledger'])->name('vendors.ledger');
        Route::get('/vendors/{vendor}/audit-logs', [VendorController::class, 'auditLogs'])->name('vendors.audit-logs');
        Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->whereNumber('vendor')->name('vendors.show');
        Route::resource('/vendors', VendorController::class)->except(['show']);
        Route::resource('/purchase-orders', PurchaseOrderController::class);
        Route::delete('/purchase-orders/{purchaseOrder}/documents/{document}', [PurchaseOrderController::class, 'deleteDocument'])->name('purchase-orders.documents.delete');
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::get('/purchase-orders/{purchaseOrder}/goods-receipts/create', [GoodsReceiptController::class, 'createFromPO'])->name('goods-receipts.create-from-po');
        Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->name('goods-receipts.post');
        Route::resource('/goods-receipts', GoodsReceiptController::class);
        Route::post('/vendor-invoices/{vendorInvoice}/approve', [VendorInvoiceController::class, 'approve'])->name('vendor-invoices.approve');
        Route::resource('/vendor-invoices', VendorInvoiceController::class);
        Route::resource('/vendor-payments', VendorPaymentController::class);
        Route::resource('/vendor-ledgers', VendorLedgerController::class)->only(['index','show']);

        Route::get('/settings/document-types', [DocumentTypeController::class, 'index'])->name('settings.document-types.index');
        Route::post('/settings/document-types', [DocumentTypeController::class, 'store'])->name('settings.document-types.store');
        Route::put('/settings/document-types/{documentType}', [DocumentTypeController::class, 'update'])->name('settings.document-types.update');
        Route::delete('/settings/document-types/{documentType}', [DocumentTypeController::class, 'destroy'])->name('settings.document-types.destroy');

        Route::get('/settings/vendor-document-requirements', [VendorDocumentRequirementController::class, 'index'])->name('settings.vendor-document-requirements.index');
        Route::post('/settings/vendor-document-requirements', [VendorDocumentRequirementController::class, 'store'])->name('settings.vendor-document-requirements.store');
        Route::put('/settings/vendor-document-requirements/{requirement}', [VendorDocumentRequirementController::class, 'update'])->name('settings.vendor-document-requirements.update');
        Route::delete('/settings/vendor-document-requirements/{requirement}', [VendorDocumentRequirementController::class, 'destroy'])->name('settings.vendor-document-requirements.destroy');
    });
});

require __DIR__.'/auth.php';
