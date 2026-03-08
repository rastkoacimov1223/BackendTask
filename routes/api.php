<?php

use App\Http\Controllers\Api\UserRevenueReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/user-revenue', UserRevenueReportController::class);
