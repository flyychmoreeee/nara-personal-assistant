<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasWeek extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'amount' => 'integer',
        'week' => 'integer',
        'month' => 'integer',
        'year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'due_date' => 'date',
    ];

    public function payments()
    {
        return $this->hasMany(KasPayment::class);
    }

    public function scopeRoutine($query)
    {
        return $query->where('type', 'routine');
    }

    public function scopeIncidental($query)
    {
        return $query->where('type', 'incidental');
    }
}
