<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracurricular_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('extracurricular_id')->constrained('extracurriculars')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['extracurricular_id', 'status']);
            $table->index(['academic_year_id', 'status']);
            $table->index(['status']);
        });

        // Partial unique index: prevent duplicate ACTIVE registrations
        // (same student + extracurricular + academic year with active-ish status).
        // History rows (rejected/cancelled) may re-register.
        // MySQL has no partial index, so use a generated column trick:
        // active_key is NULL for terminal states -> unique ignores NULLs.
        Schema::table('extracurricular_registrations', function (Blueprint $table) {
            $table->string('active_key', 191)->nullable()->after('cancelled_by');
            $table->unique(['active_key'], 'registrations_active_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_registrations');
    }
};
