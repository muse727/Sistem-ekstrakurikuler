<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracurricular_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extracurricular_id')->constrained('extracurriculars')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['extracurricular_id', 'session_date'], 'idx_sessions_ekskul_date');
            $table->index(['academic_year_id', 'session_date'], 'idx_sessions_ay_date');
            $table->index(['venue_id', 'session_date'], 'idx_sessions_venue_date');
            $table->index(['status', 'session_date'], 'idx_sessions_status_date');
            $table->index(['extracurricular_id', 'status'], 'idx_sessions_ekskul_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_sessions');
    }
};
