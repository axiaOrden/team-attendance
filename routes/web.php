<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\TrailController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard');
})->name('home');

Route::middleware('auth')->group(function () {
    // Employee attendance
    Route::get('/dashboard', [AttendanceController::class, 'index'])->name('dashboard');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::post('/attendance/geocode', [AttendanceController::class, 'geocode'])
        ->middleware('throttle:60,1')
        ->name('attendance.geocode');
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('/attendance/{record}/photo/{variant?}', [AttendanceController::class, 'photo'])
        ->whereIn('variant', ['watermarked', 'original'])
        ->name('attendance.photo');

    // Breeze profile management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/export', [AdminDashboardController::class, 'export'])->name('dashboard.export');
    Route::get('/trail', [TrailController::class, 'index'])->name('trail');
    Route::get('/employees', [AdminEmployeeController::class, 'index'])->name('employees');
    Route::patch('/employees/{user}/role', [AdminEmployeeController::class, 'updateRole'])->name('employees.role');
    Route::patch('/employees/{user}/status', [AdminEmployeeController::class, 'updateStatus'])->name('employees.status');
});

require __DIR__.'/auth.php';
