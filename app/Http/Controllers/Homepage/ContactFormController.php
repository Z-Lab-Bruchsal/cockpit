<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\ContactFormTopic;
use App\Http\Controllers\Controller;
use App\Mail\ContactFormMail;
use App\Models\CompanySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ContactFormController extends Controller
{
    public function show(): View
    {
        return view('homepage.contact-form');
    }

    /**
     * Sends the message to the contact address from the company settings, with reply-to set to the sender.
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('contactForm', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'topic' => ['required', Rule::enum(ContactFormTopic::class)],
            'old_email' => ['nullable', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy' => ['accepted'],
            // Honeypot: real visitors never fill this hidden field.
            'website' => ['prohibited'],
        ], [
            'privacy.accepted' => 'Bitte stimme der Verarbeitung deiner Angaben zu.',
        ], [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'message' => 'Nachricht',
            'old_email' => 'bisherige E-Mail-Adresse',
        ]);

        $settings = CompanySetting::current();

        Mail::to($settings->contact_email ?: config('mail.from.address'))->send(new ContactFormMail(
            senderName: $validated['name'],
            senderEmail: $validated['email'],
            senderPhone: $validated['phone'] ?? null,
            topic: ContactFormTopic::from($validated['topic']),
            body: $validated['message'],
            oldEmail: $validated['old_email'] ?? null,
        ));

        return redirect()->to(url()->previous().'#kontaktformular')->with('contact_form_sent', true);
    }
}
