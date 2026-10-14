<?php

namespace Tests\Feature\TerminalApi;

use App\Enums\BookingSource;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Chips\Pages\ListChips;
use App\Filament\Resources\TerminalEvents\Pages\ListTerminalEvents;
use App\Filament\Resources\Terminals\Pages\CreateTerminal;
use App\Filament\Resources\Terminals\Pages\EditTerminal;
use App\Models\Booking;
use App\Models\Chip;
use App\Models\Terminal;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TerminalCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function actingAsUserWithPermissions(string ...$models): User
    {
        $user = User::factory()->create();

        foreach ($models as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);

        return $user;
    }

    public function test_user_without_permission_cannot_open_terminal_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit/terminals')->assertForbidden();
        $this->get('/cockpit/chips')->assertForbidden();
        $this->get('/cockpit/terminal-events')->assertForbidden();
    }

    public function test_pages_render_for_permitted_user(): void
    {
        $this->actingAsUserWithPermissions('Terminal', 'Chip', 'TerminalEvent');
        $terminal = Terminal::factory()->create();

        $this->get('/cockpit/terminals')->assertOk();
        $this->get('/cockpit/terminals/create')->assertOk();
        $this->get("/cockpit/terminals/{$terminal->id}/edit")->assertOk();
        $this->get('/cockpit/chips')->assertOk();
        $this->get('/cockpit/terminal-events')->assertOk();
    }

    public function test_terminal_is_created_with_hashed_password(): void
    {
        $this->actingAsUserWithPermissions('Terminal');

        Livewire::test(CreateTerminal::class)
            ->fillForm([
                'name' => 'Eingang',
                'username' => 'eingang',
                'password' => 'a-long-secret-123',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $terminal = Terminal::sole();
        $this->assertTrue(Hash::check('a-long-secret-123', $terminal->password));
    }

    public function test_terminal_creation_requires_a_password(): void
    {
        $this->actingAsUserWithPermissions('Terminal');

        Livewire::test(CreateTerminal::class)
            ->fillForm(['name' => 'Eingang', 'username' => 'eingang', 'password' => null])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);
    }

    public function test_editing_terminal_without_password_keeps_the_old_one(): void
    {
        $this->actingAsUserWithPermissions('Terminal');
        $terminal = Terminal::factory()->create(['password' => 'old-secret-1234']);

        Livewire::test(EditTerminal::class, ['record' => $terminal->id])
            ->fillForm(['name' => 'Umbenannt', 'password' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $terminal->refresh();
        $this->assertSame('Umbenannt', $terminal->name);
        $this->assertTrue(Hash::check('old-secret-1234', $terminal->password));
    }

    public function test_unknown_chip_can_be_assigned_to_a_user(): void
    {
        $this->actingAsUserWithPermissions('Chip');
        $chip = Chip::factory()->unassigned()->create();
        $owner = User::factory()->create();

        Livewire::test(ListChips::class)
            ->callAction(TestAction::make('assign')->table($chip), ['user_id' => $owner->id])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame($owner->id, $chip->fresh()->user_id);
    }

    public function test_assign_action_is_hidden_for_assigned_chips(): void
    {
        $this->actingAsUserWithPermissions('Chip');
        $chip = Chip::factory()->create();

        Livewire::test(ListChips::class)
            ->assertActionHidden(TestAction::make('assign')->table($chip));
    }

    public function test_unassigned_filter_lists_only_unknown_chips(): void
    {
        $this->actingAsUserWithPermissions('Chip');
        $unknown = Chip::factory()->unassigned()->create();
        $assigned = Chip::factory()->create();

        Livewire::test(ListChips::class)
            ->filterTable('unassigned')
            ->assertCanSeeTableRecords([$unknown])
            ->assertCanNotSeeTableRecords([$assigned]);
    }

    public function test_terminal_event_log_cannot_be_created_manually(): void
    {
        $this->actingAsUserWithPermissions('TerminalEvent');

        Livewire::test(ListTerminalEvents::class)->assertOk();
        $this->get('/cockpit/terminal-events/create')->assertNotFound();
    }

    public function test_manually_created_booking_is_marked_as_manual(): void
    {
        $user = $this->actingAsUserWithPermissions('Booking');

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'user_id' => $user->id,
                'start_at' => '2026-07-16 08:00:00',
                'end_at' => '2026-07-16 12:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(BookingSource::WebManual, Booking::sole()->source);
    }
}
