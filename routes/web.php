<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/updateOrderstatusOrdered/{uuid}', [OrderController::class, 'ordered']);
Route::get('/updateOrderstatusTaken/{uuid}', [OrderController::class, 'taken']);
