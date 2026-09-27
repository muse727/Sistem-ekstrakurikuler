<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracurricular_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('registration_id')->constrained('extracurricular_registrations')->restrictOnDelete();
            $table->foreignId('extracurricular_id')->constrained('extracurriculars')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->string('evaluation_period');
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->decimal('activity_score', 5, 2)->nullable();
            $table->decimal('skill_score', 5, 2)->nullable();
            $table->decimal('discipline_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'evaluation_period'], 'unique_registration_period_evaluation');
            $table->index(['student_id', 'status'], 'idx_eval_student_status');
            $table->index(['extracurricular_id', 'academic_year_id'], 'idx_eval_ekskul_year');
            $table->index(['evaluation_period', 'status'], 'idx_eval_period_status');
            $table->index(['evaluator_id'], 'idx_eval_evaluator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_evaluations');
    }
};
