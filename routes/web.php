<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Homepage\ContactAuthController;
use App\Http\Controllers\Homepage\ContactFormController;
use App\Http\Controllers\Homepage\CourseCalendarController;
use App\Http\Controllers\Homepage\CourseController;
use App\Http\Controllers\Homepage\EmailChangeController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Homepage\RegistrationController;
use App\Http\Controllers\OrderController;
use App\Models\Section;
use Illuminate\Support\Facades\Route;

Route::get('/updateOrderstatusOrdered/{uuid}', [OrderController::class, 'ordered']);
Route::get('/updateOrderstatusTaken/{uuid}', [OrderController::class, 'taken']);

Route::redirect('/admin', '/cockpit');

// Signed decision links from approval mails to the course lead.
Route::middleware('signed')->group(function (): void {
    Route::get('/freigabe/{registration}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/freigabe/{registration}', [ApprovalController::class, 'decide'])->name('approvals.decide');
});

Route::name('homepage.')->group(function (): void {
    Route::get('/', [HomepageController::class, 'index'])->name('index');

    Route::get('/kurse', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/kurse/{slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/kalender', [CourseCalendarController::class, 'index'])->name('calendar');
    Route::get('/kalender/events', [CourseCalendarController::class, 'events'])
        ->middleware('throttle:60,1')
        ->name('calendar.events');

    Route::get('/warenkorb', [RegistrationController::class, 'cart'])->name('cart');
    Route::get('/warenkorb/abgeschlossen', [RegistrationController::class, 'done'])->name('cart.done');
    Route::post('/warenkorb/kurs/{instance}', [RegistrationController::class, 'addToCart'])
        ->middleware('throttle:20,1')
        ->name('cart.add');
    Route::post('/warenkorb/entfernen/{registration}', [RegistrationController::class, 'removeFromCart'])->name('cart.remove');
    Route::get('/anmeldung/verwalten/{token}', [RegistrationController::class, 'manage'])->name('registration.manage');
    Route::post('/anmeldung/verwalten/{token}', [RegistrationController::class, 'cancel'])
        ->middleware('throttle:10,1')
        ->name('registration.cancel');
    Route::get('/anmeldung/{token}', [RegistrationController::class, 'show'])->name('registration.show');
    Route::post('/anmeldung/{token}', [RegistrationController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('registration.confirm');
    Route::post('/konto/anmeldungen/{registration}/stornieren', [RegistrationController::class, 'cancelFromAccount'])
        ->middleware('auth:contact')
        ->name('account.registration.cancel');

    Route::get('/kontaktformular', [ContactFormController::class, 'show'])->name('contact-form');
    Route::post('/kontaktformular', [ContactFormController::class, 'send'])
        ->middleware('throttle:contact-form')
        ->name('contact-form.send');

    Route::get('/anmelden', [ContactAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/anmelden', [ContactAuthController::class, 'sendLink'])
        ->middleware('throttle:magic-link')
        ->name('login.send');
    Route::get('/login/{token}', [ContactAuthController::class, 'showConfirm'])->name('login.confirm');
    Route::post('/login/{token}', [ContactAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.consume');
    Route::post('/logout', [ContactAuthController::class, 'logout'])->name('logout');
    Route::get('/email-aendern/{token}', [EmailChangeController::class, 'show'])->name('email-change.show');
    Route::post('/email-aendern/{token}', [EmailChangeController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('email-change.confirm');
    Route::get('/konto', [ContactAuthController::class, 'account'])
        ->middleware('auth:contact')
        ->name('account');

    // Must stay the last homepage route: standalone sections like /impressum.
    Route::get('/{slug}', [HomepageController::class, 'section'])
        ->where('slug', '(?!(?:'.implode('|', Section::RESERVED_SLUGS).')$)[A-Za-z0-9\-_]+')
        ->name('section');
});
