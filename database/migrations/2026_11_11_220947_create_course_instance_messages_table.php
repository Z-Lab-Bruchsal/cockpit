<?php

use App\Models\CourseInstance;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mails sent from the cockpit to the participants of a course instance; the recipients are kept as a snapshot.
     */
    public function up(): void
    {
        Schema::create('course_instance_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CourseInstance::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->json('recipients');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_instance_messages');
    }
};
