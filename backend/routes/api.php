<?php

use App\Http\Controllers\Admin\BusinessHourController as AdminBusinessHourController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\HolidayController as AdminHolidayController;
use App\Http\Controllers\Admin\SlaPolicyController as AdminSlaPolicyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TicketActivityController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketStatusController;
use Illuminate\Support\Facades\Route;

/*
| API routes. Every route in this file is served under the /api/v1 prefix
| (see apiPrefix in bootstrap/app.php).
*/

Route::get('health', HealthController::class)->name('health');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset')->name('forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket}/status', TicketStatusController::class)->name('tickets.status');
    Route::get('tickets/{ticket}/activities', [TicketActivityController::class, 'index'])->name('tickets.activities');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::apiResource('users', AdminUserController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('categories', AdminCategoryController::class)->only(['store', 'update']);
        Route::get('sla-policies', [AdminSlaPolicyController::class, 'index'])->name('sla-policies.index');
        Route::patch('sla-policies/{priority}', [AdminSlaPolicyController::class, 'update'])->name('sla-policies.update');
        Route::get('business-hours', [AdminBusinessHourController::class, 'show'])->name('business-hours.show');
        Route::put('business-hours', [AdminBusinessHourController::class, 'update'])->name('business-hours.update');
        Route::get('holidays', [AdminHolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [AdminHolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [AdminHolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});
