<?php

use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['attendance.api', 'throttle:120,1'])->group(function (): void {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('api.transactions.index');
    Route::get('/transactions/{employeeId}', [TransactionController::class, 'employee'])
        ->where('employeeId', '[A-Za-z0-9._\/-]+')
        ->name('api.transactions.employee');
});
