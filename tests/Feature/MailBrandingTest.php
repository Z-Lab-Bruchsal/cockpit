<?php

namespace Tests\Feature;

use App\Mail\ContactMagicLinkMail;
use App\Models\CompanySetting;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mails_show_z_lab_header_and_german_footer(): void
    {
        CompanySetting::current()->update([
            'homepage_title' => 'Z-LAB Bruchsal',
            'street' => 'Werner-von-Siemens-Str. 2-6',
            'zip' => '76646',
            'city' => 'Bruchsal',
            'contact_email' => 'zlab@example.com',
            'logo_path' => null,
        ]);
        Section::factory()->standalone()->create(['slug' => 'impressum']);

        $html = (new ContactMagicLinkMail('https://example.test/login/abc', false, 'Maria'))->render();

        $this->assertStringContainsString('<title>Z-LAB Bruchsal</title>', $html);
        $this->assertStringContainsString('Werner-von-Siemens-Str. 2-6, 76646 Bruchsal', $html);
        $this->assertStringContainsString('mailto:zlab@example.com', $html);
        $this->assertStringContainsString(route('homepage.section', 'impressum'), $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('Z-Lab Cockpit', $html);
    }

    public function test_logo_with_alt_text_is_used_in_mail_header(): void
    {
        CompanySetting::current()->update(['logo_path' => 'company/logo.png', 'logo_alt' => 'Logo Z-LAB']);

        $html = (new ContactMagicLinkMail('https://example.test/login/abc', false))->render();

        $this->assertStringContainsString('company/logo.png', $html);
        $this->assertStringContainsString('alt="Logo Z-LAB"', $html);
    }
}
