<?php

use App\Http\Controllers\Homepage\CourseCalendarController;
use App\Http\Controllers\Homepage\CourseController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\OrderController;
use App\Models\Section;
use Illuminate\Support\Facades\Route;

Route::get('/updateOrderstatusOrdered/{uuid}', [OrderController::class, 'ordered']);
Route::get('/updateOrderstatusTaken/{uuid}', [OrderController::class, 'taken']);

Route::redirect('/admin', '/cockpit');

Route::name('homepage.')->group(function (): void {
    Route::get('/', [HomepageController::class, 'index'])->name('index');

    Route::get('/kurse', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/kurse/{slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/kalender', [CourseCalendarController::class, 'index'])->name('calendar');
    Route::get('/kalender/events', [CourseCalendarController::class, 'events'])
        ->middleware('throttle:60,1')
        ->name('calendar.events');

    // Must stay the last homepage route: standalone sections like /impressum.
    Route::get('/{slug}', [HomepageController::class, 'section'])
        ->where('slug', '(?!(?:'.implode('|', Section::RESERVED_SLUGS).')$)[A-Za-z0-9\-_]+')
        ->name('section');
});
