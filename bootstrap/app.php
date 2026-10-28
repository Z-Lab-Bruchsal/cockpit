<?php

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests on protected homepage pages (e.g. /konto) go to the magic link form; the cockpit has its own login.
        $middleware->redirectGuestsTo(fn () => route('homepage.login'));

        // One-click unsubscribe from mail clients (RFC 8058) cannot send a CSRF token; the secret token in the URL protects it.
        $middleware->validateCsrfTokens(except: ['abmelden/*']);

        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:send-todo-follow-up-reminders')->dailyAt('07:00');
        $schedule->command('app:send-break-compliance-warnings')->dailyAt('21:00');
        $schedule->command('app:expire-reservations')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-course-digests')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('app:send-school-reminders')->hourly()->withoutOverlapping();
    })->create();
