<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('extracurricular_invoices')->cascadeOnDelete();
            $table->string('file_path', 512);
            $table->string('original_filename', 255);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_at');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
