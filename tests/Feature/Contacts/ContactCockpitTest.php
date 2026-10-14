<?php

namespace Tests\Feature\Contacts;

use App\Enums\ContactType;
use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\RelationManagers\KidsRelationManager;
use App\Filament\Resources\Kids\Pages\CreateKid;
use App\Filament\Resources\Kids\Pages\EditKid;
use App\Mail\ContactMagicLinkMail;
use App\Models\Contact;
use App\Models\Interest;
use App\Models\Kid;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContactCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $user = User::factory()->create();

        foreach (['Contact', 'Kid'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);
    }

    public function test_pages_render(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create();

        foreach (['/cockpit/contacts', '/cockpit/contacts/create', "/cockpit/contacts/{$contact->id}/edit", '/cockpit/kids', '/cockpit/kids/create', "/cockpit/kids/{$kid->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_user_without_permission_cannot_see_contacts(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit/contacts')->assertForbidden();
    }

    public function test_list_has_tabs_for_parents_and_schools(): void
    {
        $parent = Contact::factory()->create();
        $school = Contact::factory()->school()->create();

        Livewire::test(ListContacts::class)
            ->set('activeTab', 'schools')
            ->assertCanSeeTableRecords([$school])
            ->assertCanNotSeeTableRecords([$parent]);
    }

    public function test_can_create_school_contact(): void
    {
        Livewire::test(CreateContact::class)
            ->fillForm([
                'type' => ContactType::School->value,
                'name' => 'Grundschule Bruchsal',
                'contact_person' => 'Frau Lehrer',
                'email' => 'Sekretariat@Schule.example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Contact::class, [
            'name' => 'Grundschule Bruchsal',
            'type' => 'school',
            'contact_person' => 'Frau Lehrer',
            'email' => 'sekretariat@schule.example',
        ]);
    }

    public function test_email_must_be_unique(): void
    {
        Contact::factory()->create(['email' => 'doppelt@example.com']);

        Livewire::test(CreateContact::class)
            ->fillForm(['type' => 'parent', 'name' => 'Zweiter', 'email' => 'doppelt@example.com'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_consent_toggle_sets_and_clears_consent_date(): void
    {
        $contact = Contact::factory()->create();

        Livewire::test(EditContact::class, ['record' => $contact->id])
            ->fillForm(['wants_interest_mails' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($contact->fresh()->wantsInterestMails());

        Livewire::test(EditContact::class, ['record' => $contact->id])
            ->assertSchemaStateSet(['wants_interest_mails' => true])
            ->fillForm(['wants_interest_mails' => false])
            ->call('save');

        $this->assertFalse($contact->fresh()->wantsInterestMails());
    }

    public function test_can_send_login_link_from_cockpit(): void
    {
        Mail::fake();
        $contact = Contact::factory()->create(['email' => 'mama@example.com']);

        Livewire::test(EditContact::class, ['record' => $contact->id])
            ->callAction('sendLoginLink')
            ->assertNotified('Anmeldelink verschickt');

        Mail::assertSent(ContactMagicLinkMail::class, fn (ContactMagicLinkMail $mail): bool => $mail->hasTo('mama@example.com'));
    }

    public function test_login_link_action_is_hidden_without_email(): void
    {
        $contact = Contact::factory()->create(['email' => null]);

        Livewire::test(ListContacts::class)
            ->assertActionHidden(TestAction::make('sendLoginLink')->table($contact));
    }

    public function test_can_create_kid_for_contact(): void
    {
        $contact = Contact::factory()->create();
        $interest = Interest::factory()->create();

        Livewire::test(KidsRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => EditContact::class])
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Paul',
                'age' => 10,
                'interests' => [$interest->id],
            ])
            ->assertHasNoFormErrors();

        $kid = $contact->kids()->sole();
        $this->assertSame('Paul', $kid->name);
        $this->assertSame(10, $kid->age());
        $this->assertTrue($kid->birthdate_estimated);
        $this->assertTrue($kid->interests->contains($interest));
    }

    public function test_can_create_kid_with_several_parents(): void
    {
        $mother = Contact::factory()->create();
        $father = Contact::factory()->create();

        Livewire::test(CreateKid::class)
            ->fillForm([
                'name' => 'Lena',
                'age' => 8,
                'contacts' => [$mother->id, $father->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$mother->id, $father->id], Kid::query()->sole()->contacts->pluck('id')->all());
    }

    public function test_kid_age_is_calculated_from_birthdate(): void
    {
        $kid = Kid::factory()->create(['birthdate' => '2016-10-15']);

        $this->assertSame(9, $kid->age(now()->setDate(2026, 10, 14)));
        $this->assertSame(10, $kid->age(now()->setDate(2026, 10, 15)));
        $this->assertNull(Kid::factory()->create(['birthdate' => null])->age());
    }

    public function test_cockpit_shows_age_but_never_the_birthdate(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create(['name' => 'Paul', 'birthdate' => '2016-03-17']);
        $contact->kids()->attach($kid);

        foreach (['/cockpit/kids', "/cockpit/kids/{$kid->id}/edit", "/cockpit/contacts/{$contact->id}/edit"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('2016-03-17')
                ->assertDontSee('17.03.2016');
        }

        Livewire::test(EditKid::class, ['record' => $kid->id])
            ->assertSchemaStateSet(['age' => $kid->age()])
            ->assertDontSee('2016-03-17');
    }

    public function test_saving_kid_with_unchanged_age_keeps_exact_birthdate(): void
    {
        $kid = Kid::factory()->create(['birthdate' => '2016-03-17']);

        Livewire::test(EditKid::class, ['record' => $kid->id])
            ->fillForm(['name' => 'Paula'])
            ->call('save')
            ->assertHasNoFormErrors();

        $kid->refresh();
        $this->assertSame('2016-03-17', $kid->birthdate->toDateString());
        $this->assertFalse($kid->birthdate_estimated);
    }

    public function test_changing_age_in_cockpit_stores_estimated_birthdate(): void
    {
        $kid = Kid::factory()->create(['birthdate' => now()->subYears(9)->subMonth()->toDateString()]);

        Livewire::test(EditKid::class, ['record' => $kid->id])
            ->fillForm(['age' => 11])
            ->call('save')
            ->assertHasNoFormErrors();

        $kid->refresh();
        $this->assertSame(11, $kid->age());
        $this->assertTrue($kid->birthdate_estimated);
        $this->assertNull($kid->exactBirthdate());
    }
}
