<?php

use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\OrderController;
use App\Models\Section;
use Illuminate\Support\Facades\Route;

Route::get('/updateOrderstatusOrdered/{uuid}', [OrderController::class, 'ordered']);
Route::get('/updateOrderstatusTaken/{uuid}', [OrderController::class, 'taken']);

Route::redirect('/admin', '/cockpit');

Route::name('homepage.')->group(function (): void {
    Route::get('/', [HomepageController::class, 'index'])->name('index');

    // Must stay the last homepage route: standalone sections like /impressum.
    Route::get('/{slug}', [HomepageController::class, 'section'])
        ->where('slug', '(?!(?:'.implode('|', Section::RESERVED_SLUGS).')$)[A-Za-z0-9\-_]+')
        ->name('section');
});
