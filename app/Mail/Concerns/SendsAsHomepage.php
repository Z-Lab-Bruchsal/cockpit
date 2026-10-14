<?php

namespace App\Mail\Concerns;

use App\Models\CompanySetting;
use Illuminate\Mail\Mailables\Address;

/**
 * Mails to parents and schools carry the homepage title as sender name instead of the cockpit title.
 */
trait SendsAsHomepage
{
    protected function homepageSender(): Address
    {
        return new Address(config('mail.from.address'), CompanySetting::current()->homepageTitle());
    }
}
