<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ExtracurricularInvoice extends Model
{
    use HasFactory;

    protected $table = 'extracurricular_invoices';

    protected $fillable = [
        'registration_id',
        'invoice_number',
        'amount',
        'issued_at',
        'due_at',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExtracurricularRegistration::class, 'registration_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'invoice_id')->orderByDesc('id');
    }

    public function verifications(): HasManyThrough
    {
        return $this->hasManyThrough(
            PaymentVerification::class,
            PaymentProof::class,
            'invoice_id',
            'payment_proof_id',
            'id',
            'id'
        );
    }

    public function latestProof(): ?PaymentProof
    {
        return $this->relationLoaded('proofs')
            ? $this->proofs->first()
            : $this->proofs()->first();
    }

    public function isActive(): bool
    {
        $status = $this->status instanceof InvoiceStatus ? $this->status->value : (string) $this->status;

        return in_array($status, [InvoiceStatus::UNPAID->value, InvoiceStatus::PENDING_VERIFICATION->value], true);
    }
}
