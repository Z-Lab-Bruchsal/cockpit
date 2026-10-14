<?php

namespace Tests\Feature\Contacts;

use App\Livewire\Homepage\Account;
use App\Models\Contact;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Kid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->contact = Contact::factory()->create(['name' => 'Maria Muster']);
        $this->actingAs($this->contact, 'contact');
    }

    public function test_account_page_renders(): void
    {
        $this->get('/konto')
            ->assertOk()
            ->assertSee('Meine Kinder')
            ->assertSeeLivewire(Account::class);
    }

    public function test_parent_can_update_profile_and_give_consent(): void
    {
        Livewire::test(Account::class)
            ->set('name', 'Maria Mustermann')
            ->set('phone', '0721 123456')
            ->set('city', 'Bruchsal')
            ->set('wantsInterestMails', true)
            ->call('saveProfile')
            ->assertHasNoErrors();

        $contact = $this->contact->fresh();
        $this->assertSame('Maria Mustermann', $contact->name);
        $this->assertSame('Bruchsal', $contact->city);
        $this->assertTrue($contact->wantsInterestMails());

        Livewire::test(Account::class)
            ->set('wantsInterestMails', false)
            ->call('saveProfile');

        $this->assertFalse($this->contact->fresh()->wantsInterestMails());
    }

    public function test_consent_date_is_kept_when_saving_again(): void
    {
        $this->contact->update(['interest_mails_consent_at' => now()->subMonth()]);

        Livewire::test(Account::class)->set('name', 'Neu')->call('saveProfile');

        $this->assertTrue($this->contact->fresh()->interest_mails_consent_at->isSameDay(now()->subMonth()));
    }

    public function test_parent_can_add_several_kids_with_interests(): void
    {
        $robotics = Interest::factory()->create(['name' => 'Robotik']);
        $sewing = Interest::factory()->create(['name' => 'Nähen']);

        Livewire::test(Account::class)
            ->call('createKid')
            ->set('kidName', 'Paul')
            ->set('kidBirthdate', now()->subYears(9)->toDateString())
            ->set('kidInterestIds', [$robotics->id])
            ->call('saveKid')
            ->assertHasNoErrors()
            ->call('createKid')
            ->set('kidName', 'Lena')
            ->set('kidBirthdate', now()->subYears(12)->toDateString())
            ->set('kidInterestIds', [$robotics->id, $sewing->id])
            ->call('saveKid')
            ->assertHasNoErrors()
            ->assertSee('Paul')
            ->assertSee('9 Jahre')
            ->assertSee('Lena');

        $kids = $this->contact->kids()->with('interests')->get();
        $this->assertCount(2, $kids);
        $this->assertEqualsCanonicalizing(['Robotik', 'Nähen'], $kids->firstWhere('name', 'Lena')->interests->pluck('name')->all());
    }

    public function test_kid_needs_name_and_plausible_birthdate(): void
    {
        $inactive = Interest::factory()->inactive()->create();

        Livewire::test(Account::class)
            ->call('createKid')
            ->set('kidName', '')
            ->set('kidBirthdate', now()->addDay()->toDateString())
            ->set('kidInterestIds', [$inactive->id])
            ->call('saveKid')
            ->assertHasErrors(['kidName' => 'required', 'kidBirthdate' => 'before', 'kidInterestIds.0' => 'exists']);

        $this->assertSame(0, Kid::query()->count());
    }

    public function test_parent_can_edit_own_kid(): void
    {
        $kid = Kid::factory()->create(['name' => 'Paul']);
        $this->contact->kids()->attach($kid);
        $interest = Interest::factory()->create();

        Livewire::test(Account::class)
            ->call('editKid', $kid->id)
            ->assertSet('kidName', 'Paul')
            ->set('kidName', 'Paul Muster')
            ->set('kidInterestIds', [$interest->id])
            ->call('saveKid')
            ->assertHasNoErrors();

        $this->assertSame('Paul Muster', $kid->fresh()->name);
        $this->assertTrue($kid->interests->contains($interest));
    }

    public function test_parent_cannot_edit_or_remove_foreign_kids(): void
    {
        $foreignKid = Kid::factory()->create();
        Contact::factory()->create()->kids()->attach($foreignKid);

        Livewire::test(Account::class)->call('editKid', $foreignKid->id)->assertNotFound();
        Livewire::test(Account::class)->call('removeKid', $foreignKid->id)->assertNotFound();

        $this->assertNotNull($foreignKid->fresh());
    }

    public function test_removing_a_kid_deletes_it_only_without_other_links(): void
    {
        $onlyMine = Kid::factory()->create();
        $shared = Kid::factory()->create();
        $withHistory = Kid::factory()->create();
        $this->contact->kids()->attach([$onlyMine->id, $shared->id, $withHistory->id]);
        Contact::factory()->create()->kids()->attach($shared);
        $withHistory->courses()->attach(Course::factory()->create(), ['date' => now()->subMonth()]);

        Livewire::test(Account::class)
            ->call('removeKid', $onlyMine->id)
            ->call('removeKid', $shared->id)
            ->call('removeKid', $withHistory->id);

        $this->assertNull($onlyMine->fresh());
        $this->assertNotNull($shared->fresh());
        $this->assertNotNull($withHistory->fresh());
        $this->assertSame(0, $this->contact->kids()->count());
    }

    public function test_parents_see_exact_birthdate_but_not_an_estimated_one(): void
    {
        $exact = Kid::factory()->create(['birthdate' => '2016-03-17']);
        $estimated = Kid::factory()->create()->estimateBirthdateFromAge(9);
        $estimated->save();
        $this->contact->kids()->attach([$exact->id, $estimated->id]);

        Livewire::test(Account::class)
            ->call('editKid', $exact->id)
            ->assertSet('kidBirthdate', '2016-03-17')
            ->call('editKid', $estimated->id)
            ->assertSet('kidBirthdate', '')
            ->assertSet('kidBirthdateIsEstimated', true)
            ->assertSee('ungefähre Alter')
            ->set('kidBirthdate', '2017-05-01')
            ->call('saveKid')
            ->assertHasNoErrors();

        $estimated->refresh();
        $this->assertSame('2017-05-01', $estimated->birthdate->toDateString());
        $this->assertFalse($estimated->birthdate_estimated);
    }
}
