<?php

namespace Tests\Feature\Homepage;

use App\Mail\TeamContactMail;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Max Muster',
            'email' => 'max@example.com',
            'message' => 'Hallo, ich habe eine Frage zum Robotikkurs.',
            'privacy' => '1',
        ], $overrides);
    }

    public function test_message_is_forwarded_to_the_team_member_with_reply_to_sender(): void
    {
        $member = TeamProfile::factory()->for(User::factory()->state(['email' => 'team@example.com']))->create()->user;

        $this->from('/ueber-uns')
            ->post(route('homepage.team.contact', $member), $this->validPayload())
            ->assertRedirect('/ueber-uns#team-'.$member->id)
            ->assertSessionHas('team_contact_sent', $member->id);

        Mail::assertSent(TeamContactMail::class, function (TeamContactMail $mail): bool {
            return $mail->hasTo('team@example.com')
                && $mail->hasReplyTo('max@example.com', 'Max Muster')
                && $mail->body === 'Hallo, ich habe eine Frage zum Robotikkurs.';
        });
    }

    public function test_mail_renders(): void
    {
        $mail = new TeamContactMail('Lea', 'Max Muster', 'max@example.com', 'Hallo Lea');

        $mail->assertSeeInHtml('Hallo Lea');
        $mail->assertSeeInHtml('max@example.com');
        $mail->assertHasSubject('Nachricht über die Homepage von Max Muster');
    }

    public function test_hidden_or_not_contactable_members_cannot_be_contacted(): void
    {
        $hidden = TeamProfile::factory()->for(User::factory())->create(['team_visible' => false])->user;
        $notContactable = TeamProfile::factory()->for(User::factory())->create(['team_contactable' => false])->user;
        $noTeamMember = User::factory()->create();

        foreach ([$hidden, $notContactable, $noTeamMember] as $user) {
            $this->post(route('homepage.team.contact', $user), $this->validPayload())->assertNotFound();
        }

        Mail::assertNothingSent();
    }

    public function test_validation_errors_use_their_own_bag(): void
    {
        $member = TeamProfile::factory()->for(User::factory())->create()->user;

        $this->from('/ueber-uns')
            ->post(route('homepage.team.contact', $member), $this->validPayload([
                'email' => 'keine-mail',
                'message' => '',
                'privacy' => null,
            ]))
            ->assertRedirect('/ueber-uns')
            ->assertSessionHasErrors(['email', 'message', 'privacy'], null, 'teamContact');

        Mail::assertNothingSent();
    }

    public function test_honeypot_blocks_bots(): void
    {
        $member = TeamProfile::factory()->for(User::factory())->create()->user;

        $this->post(route('homepage.team.contact', $member), $this->validPayload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors(['website'], null, 'teamContact');

        Mail::assertNothingSent();
    }
}
