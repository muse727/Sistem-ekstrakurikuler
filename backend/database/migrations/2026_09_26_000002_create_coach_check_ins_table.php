<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('extracurricular_sessions')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy_meters');
            $table->unsignedInteger('distance_from_venue_meters')->nullable();
            $table->timestamp('device_captured_at')->nullable();
            $table->timestamp('server_received_at');
            $table->string('photo_path', 512);
            $table->string('status')->default('present');
            $table->timestamps();

            $table->unique(['session_id', 'coach_id'], 'unique_session_coach_checkin');
            $table->index(['coach_id', 'server_received_at'], 'idx_checkins_coach_time');
            $table->index(['session_id', 'status'], 'idx_checkins_session_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_check_ins');
    }
};
