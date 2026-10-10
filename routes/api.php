<?php

use App\Http\Controllers\Api\V1\TerminalController;
use App\Http\Middleware\AuthenticateTerminal;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([AuthenticateTerminal::class, 'throttle:terminal'])
    ->group(function (): void {
        Route::get('/book/{chip}', [TerminalController::class, 'book'])
            ->where('chip', '[0-9A-Fa-f][0-9A-Fa-f:\-]*')
            ->name('api.v1.book');
        Route::get('/bookinginfo/{chip}', [TerminalController::class, 'bookingInfo'])
            ->where('chip', '[0-9A-Fa-f][0-9A-Fa-f:\-]*')
            ->name('api.v1.bookinginfo');
    });
