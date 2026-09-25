<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracurricular_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('extracurricular_registrations')->restrictOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->unsignedBigInteger('amount');
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->string('status', 32)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_invoices');
    }
};
