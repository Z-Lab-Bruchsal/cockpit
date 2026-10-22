<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const LOGO_PATH = 'company/z-lab-logo.svg';

    /**
     * Copy the Z-LAB logo of auerbach-stiftung.de to the public disk and use it as homepage logo.
     */
    public function up(): void
    {
        Storage::disk('public')->put(self::LOGO_PATH, file_get_contents(resource_path('images/z-lab-logo.svg')));

        DB::table('company_settings')->updateOrInsert(
            ['id' => 1],
            ['logo_path' => self::LOGO_PATH, 'logo_alt' => 'Logo Z-LAB Zukunftslabor', 'updated_at' => now()],
        );
    }

    /**
     * Remove the logo again, unless another one was uploaded in the meantime.
     */
    public function down(): void
    {
        DB::table('company_settings')
            ->where('logo_path', self::LOGO_PATH)
            ->update(['logo_path' => null, 'logo_alt' => null]);

        Storage::disk('public')->delete(self::LOGO_PATH);
    }
};
