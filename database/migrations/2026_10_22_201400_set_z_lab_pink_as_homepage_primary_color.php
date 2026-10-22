<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Use the Z-LAB pink of auerbach-stiftung.de as homepage brand color; the colors derived from it
     * (headings, buttons, card edges) are computed by CompanySetting::brandCssVariables().
     */
    public function up(): void
    {
        DB::table('company_settings')->updateOrInsert(
            ['id' => 1],
            ['primary_color' => '#e41f6c', 'updated_at' => now()],
        );
    }

    /**
     * The previous color is not known any more, it stays as it is and can be changed in the cockpit (Firmeninfos).
     */
    public function down(): void
    {
        //
    }
};
