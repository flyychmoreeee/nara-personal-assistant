<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'type',
        'reminder_date',
        'target_phone',
        'status',
        'message',
        'response_payload',
        'sent_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'response_payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
