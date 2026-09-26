<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('extracurricular_sessions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('registration_id')->constrained('extracurricular_registrations')->restrictOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'student_id'], 'unique_session_student_attendance');
            $table->index(['session_id', 'status'], 'idx_attend_session_status');
            $table->index(['student_id', 'status'], 'idx_attend_student_status');
            $table->index(['registration_id'], 'idx_attend_registration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
