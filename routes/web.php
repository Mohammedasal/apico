<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcelImportController;
use App\Http\Controllers\ChequeInController;
use App\Http\Controllers\ChequeOutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', LocaleController::class)->name('language.switch');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/imports/sales-sheet', [ExcelImportController::class, 'store'])
        ->middleware('role:admin')
        ->name('imports.sales-sheet.store');
    Route::post('/imports/production-sheet', [ExcelImportController::class, 'production'])
        ->middleware('role:admin')
        ->name('imports.production-sheet.store');

    Route::get('/production', [ProductionController::class, 'index'])
        ->middleware('role:admin,viewer')
        ->name('production.index');
    Route::post('/production/days', [ProductionController::class, 'saveDays'])
        ->middleware('role:admin')
        ->name('production.days.store');
    Route::post('/production/expenses', [ProductionController::class, 'saveExpenses'])
        ->middleware('role:admin')
        ->name('production.expenses.store');

    Route::get('/cheques-out', [ChequeOutController::class, 'index'])->name('cheques-out.index');
    Route::post('/cheques-out', [ChequeOutController::class, 'store'])->middleware('role:admin,data_entry')->name('cheques-out.store');
    Route::get('/cheques-out/{chequeOut}/edit', [ChequeOutController::class, 'edit'])->middleware('role:admin,data_entry')->name('cheques-out.edit');
    Route::put('/cheques-out/{chequeOut}', [ChequeOutController::class, 'update'])->middleware('role:admin,data_entry')->name('cheques-out.update');
    Route::get('/cheques-in', [ChequeInController::class, 'index'])->name('cheques-in.index');
    Route::put('/cheques-in/{payment}', [ChequeInController::class, 'update'])->middleware('role:admin,data_entry')->name('cheques-in.update');

    Route::get('/customers/{customer}/export', [CustomerController::class, 'export'])->middleware('role:admin,viewer')->name('customers.export');
    Route::resource('customers', CustomerController::class)->only(['index', 'show']);
    Route::resource('customers', CustomerController::class)->only(['create', 'store', 'edit', 'update'])->middleware('role:admin,data_entry');
    Route::resource('materials', MaterialController::class)->only(['index']);
    Route::resource('materials', MaterialController::class)->only(['create', 'store', 'edit', 'update'])->middleware('role:admin,data_entry');
    Route::get('/suppliers/{supplier}/export', [SupplierController::class, 'export'])->middleware('role:admin,viewer')->name('suppliers.export');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'show']);
    Route::resource('suppliers', SupplierController::class)->only(['create', 'store', 'edit', 'update'])->middleware('role:admin,data_entry');
    Route::resource('supplier-payments', SupplierPaymentController::class)->only(['index']);
    Route::resource('supplier-payments', SupplierPaymentController::class)->only(['create', 'store', 'edit', 'update'])->middleware('role:admin,data_entry');

    Route::prefix('{module}')
        ->whereIn('module', ['recycle-in', 'recycle-out', 'payments', 'stock-purchases', 'stock-sales'])
        ->name('operations.')
        ->group(function () {
            Route::get('/', [OperationController::class, 'index'])->name('index');
            Route::get('/create', [OperationController::class, 'create'])->middleware('role:admin,data_entry')->name('create');
            Route::post('/', [OperationController::class, 'store'])->middleware('role:admin,data_entry')->name('store');
            Route::get('/{id}/edit', [OperationController::class, 'edit'])->middleware('role:admin,data_entry')->name('edit');
            Route::put('/{id}', [OperationController::class, 'update'])->middleware('role:admin,data_entry')->name('update');
        });

    Route::middleware('role:admin,viewer')->group(function () {
        Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('/reports/monthly/export', [ReportController::class, 'monthlyExport'])->name('reports.monthly.export');
        Route::get('/reports/customer-statement/{customer}', [ReportController::class, 'customerStatement'])->name('reports.customer-statement');
        Route::get('/reports/stock-profit', [ReportController::class, 'stockProfit'])->name('reports.stock-profit');
        Route::get('/reports/stock-profit/export', [ReportController::class, 'stockProfitExport'])->name('reports.stock-profit.export');
        Route::get('/reports/alerts', [ReportController::class, 'alerts'])->name('reports.alerts');
        Route::get('/reports/alerts/export', [ReportController::class, 'alertsExport'])->name('reports.alerts.export');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });
});
