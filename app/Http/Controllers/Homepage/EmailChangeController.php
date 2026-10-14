<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Services\Contacts\EmailChangeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class EmailChangeController extends Controller
{
    public function __construct(protected EmailChangeService $emailChanges) {}

    /**
     * Shows a button instead of confirming directly, so mail scanners opening the link do not trigger the change.
     */
    public function show(string $token): View
    {
        $change = $this->emailChanges->find($token);

        return view('homepage.auth.email-change', [
            'token' => $token,
            'change' => $change,
            'result' => session('email_change_result'),
        ]);
    }

    public function confirm(string $token): RedirectResponse
    {
        $result = $this->emailChanges->confirm($token);

        return redirect()
            ->route('homepage.email-change.show', $token)
            ->with('email_change_result', $result ?? 'invalid');
    }
}
