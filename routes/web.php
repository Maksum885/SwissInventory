<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\RackMonitoringController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

Route::prefix('rack')->name('rack.')->group(function () {
    Route::get('/', [RackMonitoringController::class, 'index'])->name('index');
    Route::get('/all-slots', [RackMonitoringController::class, 'allSlots'])->name('all-slots');
    Route::get('/{rack}/slots', [RackMonitoringController::class, 'slots'])->name('slots');
    Route::get('/slot/{storageBin}', [RackMonitoringController::class, 'slotDetail'])->name('slot-detail');
    Route::get('/search', [RackMonitoringController::class, 'search'])->name('search');
});

Route::get('/transaksi', [TransaksiController::class, 'index'])->name('transaksi');

Route::prefix('laporan')->name('laporan.')->group(function () {
    Route::get('/', [LaporanController::class, 'index'])->name('index');
    Route::get('/export/excel', [LaporanController::class, 'exportExcel'])->name('export.excel');
    Route::get('/export/pdf', [LaporanController::class, 'exportPdf'])->name('export.pdf');
});
