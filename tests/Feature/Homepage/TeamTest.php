<?php

namespace Tests\Feature\Homepage;

use App\Enums\TeamArea;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function teamSection(array $data = []): Section
    {
        return Section::factory()->create([
            'slug' => 'ueber-uns',
            'content' => [['type' => 'team', 'data' => $data]],
        ]);
    }

    public function test_visible_team_members_are_grouped_by_area_in_order(): void
    {
        User::factory()->teamMember(TeamArea::Ehrenamt)->create(['name' => 'Erna Ehrenamt', 'team_role' => 'IT-Admin']);
        User::factory()->teamMember(TeamArea::Leitung)->create(['name' => 'Lea Leitung', 'team_role' => 'Leitung']);
        User::factory()->teamMember(TeamArea::Jugendleitung)->create(['name' => 'Zora Jung', 'team_sort' => 2]);
        User::factory()->teamMember(TeamArea::Jugendleitung)->create(['name' => 'Anna Jung', 'team_sort' => 1]);
        $this->teamSection();

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSeeInOrder([
                'Leitung', 'Lea Leitung',
                'Jugendleitung', 'Anna Jung', 'Zora Jung',
                'Ehrenamt', 'Erna Ehrenamt', 'IT-Admin',
            ]);
    }

    public function test_hidden_members_and_members_without_area_are_not_shown(): void
    {
        User::factory()->teamMember()->create(['name' => 'Sichtbar Person']);
        User::factory()->teamMember()->create(['name' => 'Versteckt Person', 'team_visible' => false]);
        User::factory()->teamMember()->create(['name' => 'Ohne Bereich', 'team_area' => null]);
        User::factory()->create(['name' => 'Kein Teamprofil']);
        $this->teamSection();

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee('Sichtbar Person')
            ->assertDontSee('Versteckt Person')
            ->assertDontSee('Ohne Bereich')
            ->assertDontSee('Kein Teamprofil');
    }

    public function test_block_can_be_limited_to_areas_without_headings(): void
    {
        User::factory()->teamMember(TeamArea::Freiwilligendienst)->create(['name' => 'Bufdi Person']);
        User::factory()->teamMember(TeamArea::Leitung)->create(['name' => 'Chefin Person']);
        $this->teamSection(['areas' => [TeamArea::Freiwilligendienst->value], 'show_area_headings' => false]);

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee('Bufdi Person')
            ->assertDontSee('Chefin Person')
            ->assertDontSee('Bundesfreiwilligendienst</h3>', false);
    }

    public function test_business_card_shows_bio_interests_and_courses_but_no_email(): void
    {
        $member = User::factory()->teamMember()->create([
            'email' => 'geheim@example.com',
            'team_bio' => "Ich löte gern.\n<script>alert(1)</script>",
        ]);
        $member->interests()->attach(Interest::factory()->create(['name' => 'Robotik']));
        Course::factory()->create(['name' => 'Roboter bauen', 'responsible_user_id' => $member->id]);
        Course::factory()->unpublished()->create(['name' => 'Geheimkurs', 'responsible_user_id' => $member->id]);
        $this->teamSection();

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee('Ich löte gern.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Robotik')
            ->assertSee('Roboter bauen')
            ->assertDontSee('Geheimkurs')
            ->assertSee(route('homepage.team.contact', $member), false)
            ->assertDontSee('geheim@example.com');
    }

    public function test_member_without_contact_has_no_contact_form(): void
    {
        $member = User::factory()->teamMember()->create(['team_contactable' => false]);
        $this->teamSection();

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee($member->name)
            ->assertDontSee(route('homepage.team.contact', $member), false)
            ->assertDontSee('Nachricht schreiben');
    }

    public function test_initials_are_shown_without_photo(): void
    {
        User::factory()->teamMember()->create(['name' => 'Dr. Sylvia Zinser']);
        $this->teamSection();

        $this->get('/ueber-uns')->assertOk()->assertSee('SZ');
    }

    public function test_team_block_can_be_linked_via_anchor(): void
    {
        User::factory()->teamMember()->create();
        $this->teamSection(['anchor' => 'team']);

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee('id="team"', false);
    }

    public function test_anchor_is_sanitized_and_omitted_when_empty(): void
    {
        User::factory()->teamMember()->create();
        $this->teamSection(['anchor' => '"><script>x</script>']);

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('id="scriptxscript"', false);

        Section::query()->delete();
        $this->teamSection(['anchor' => '']);

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertDontSee('class="scroll-mt-24">', false);
    }

    public function test_hover_preview_shows_business_card_summary(): void
    {
        $member = User::factory()->teamMember()->create([
            'team_role' => 'Drohnen und Elektronik',
            'team_bio' => 'Ich baue Drohnen <b>gern</b>.',
        ]);
        $member->interests()->attach(Interest::factory()->create(['name' => 'Drohnen']));
        $this->teamSection();

        $this->get('/ueber-uns')
            ->assertOk()
            ->assertSee('id="team-'.$member->id.'-preview"', false)
            ->assertSee('role="tooltip"', false)
            ->assertSee('aria-describedby="team-'.$member->id.'-preview"', false)
            ->assertSee('Ich baue Drohnen &lt;b&gt;gern&lt;/b&gt;.', false)
            ->assertSee('Klicken für Visitenkarte &amp; Kontakt', false);
    }
}
