<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
| API routes. Every route in this file is served under the /api/v1 prefix
| (see apiPrefix in bootstrap/app.php).
*/

Route::get('health', HealthController::class)->name('health');
