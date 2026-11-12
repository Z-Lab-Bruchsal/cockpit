<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($query): void {
                $query->whereNotNull('team_area')
                    ->orWhereNotNull('team_role')
                    ->orWhereNotNull('team_bio')
                    ->orWhereNotNull('team_photo_path')
                    ->orWhereNotNull('team_photo_alt')
                    ->orWhere('team_sort', '!=', 0)
                    ->orWhere('team_visible', true)
                    ->orWhere('team_contactable', false)
                    ->orWhereIn('id', DB::table('interest_user')->select('user_id'));
            })
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $profile = Arr::only((array) $user, [
                        'team_visible', 'team_area', 'team_role', 'team_bio', 'team_photo_path',
                        'team_photo_alt', 'team_sort', 'team_contactable', 'created_at', 'updated_at',
                    ]);

                    DB::table('team_profiles')->insertOrIgnore($profile + ['user_id' => $user->id]);
                }
            });

        DB::table('interest_team_profile')->insertOrIgnoreUsing(
            ['team_profile_id', 'interest_id'],
            DB::table('interest_user')
                ->join('team_profiles', 'team_profiles.user_id', '=', 'interest_user.user_id')
                ->select('team_profiles.id', 'interest_user.interest_id'),
        );
    }

    public function down(): void
    {
        // Original user fields remain available to the previous version.
    }
};
