<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::STUDENT->value)->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->foreignId('student_id')->nullable()->after('last_login_at')->constrained('students')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->after('student_id')->constrained('coaches')->nullOnDelete();

            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['coach_id']);
            $table->dropColumn(['role', 'is_active', 'last_login_at', 'student_id', 'coach_id']);
        });
    }
};
