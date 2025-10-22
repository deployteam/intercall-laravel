<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use DeployTeam\IntercallLaravel\Http\Controllers\IntercallController;

/*
|--------------------------------------------------------------------------
| Intercall HTTP Fallback Routes
|--------------------------------------------------------------------------
|
| These routes provide an HTTP fallback mechanism for inter-system
| communication when Redis is unavailable or not preferred.
|
*/

$endpoint = rtrim(config('intercall.http_fallback.endpoint', '/intercall'), '/');
$name = config('intercall.http_fallback.route_name', 'intercall.handle');

Route::post($endpoint, IntercallController::class)->name($name);
Route::get("{$endpoint}/heartbeat", [IntercallController::class, 'heartbeat'])->name('intercall.heartbeat');
