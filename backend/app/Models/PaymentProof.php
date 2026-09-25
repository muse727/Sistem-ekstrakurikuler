<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentProof extends Model
{
    protected $table = 'payment_proofs';

    protected $fillable = [
        'invoice_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ExtracurricularInvoice::class, 'invoice_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class, 'payment_proof_id')->orderByDesc('id');
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(PaymentVerification::class, 'payment_proof_id')->latestOfMany();
    }
}
