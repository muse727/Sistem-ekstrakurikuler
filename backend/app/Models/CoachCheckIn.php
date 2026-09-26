<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'coach_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_from_venue_meters',
        'device_captured_at',
        'server_received_at',
        'photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'integer',
            'distance_from_venue_meters' => 'integer',
            'device_captured_at' => 'datetime',
            'server_received_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExtracurricularSession::class, 'session_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
