<?php

namespace Tests\Feature;

use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GermanDateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_picker_defaults_to_german_display_format(): void
    {
        $picker = DatePicker::make('date');

        $this->assertFalse($picker->isNative());
        $this->assertSame('d.m.Y', $picker->getDisplayFormat());
    }

    public function test_date_time_picker_defaults_to_german_display_format(): void
    {
        $picker = DateTimePicker::make('date_time');

        $this->assertFalse($picker->isNative());
        $this->assertSame('d.m.Y H:i', $picker->getDisplayFormat());
    }

    public function test_table_column_renders_datetimes_in_german_format(): void
    {
        $user = User::factory()->create();
        TimeEntry::create([
            'user_id' => $user->id,
            'type' => TimeEntryType::Come,
            'happened_at' => '2026-03-05 14:30:00',
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ListTimeEntries::class)
            ->assertSee('05.03.2026 14:30');
    }
}
