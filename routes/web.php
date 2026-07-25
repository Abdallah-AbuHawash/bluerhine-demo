<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('estimates.create'));

    Route::get('/estimates/new', [EstimateController::class, 'create'])->name('estimates.create');
    Route::post('/estimates', [EstimateController::class, 'store'])->name('estimates.store');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/quotes/{quote}/issue', [QuoteController::class, 'issue'])->name('quotes.issue');
    Route::get('/quotes/{quote}/order', [QuoteController::class, 'orderPreview'])->name('quotes.order');
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');

    Route::get('/intake', [IntakeController::class, 'create'])->name('intake.create');
    Route::post('/intake', [IntakeController::class, 'store'])->name('intake.store');
    Route::get('/intake/{submission}/review', [IntakeController::class, 'review'])->name('intake.review');
    Route::post('/intake/{submission}/approve', [IntakeController::class, 'approve'])->name('intake.approve');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::put('/admin/cut-parameters', [AdminController::class, 'updateParameters'])->name('admin.parameters');
    Route::put('/admin/cutting-rates/{rate}', [AdminController::class, 'updateRate'])->name('admin.rates.update');
    Route::post('/admin/cutting-rates', [AdminController::class, 'storeRate'])->name('admin.rates.store');
    Route::delete('/admin/cutting-rates/{rate}', [AdminController::class, 'destroyRate'])->name('admin.rates.destroy');
    Route::put('/admin/lead-time-rules/{rule}', [AdminController::class, 'updateLeadTime'])->name('admin.lead-times.update');
});
