<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasPayment extends Model
{
    public const METHOD_CASH = 'cash';
    public const METHOD_QRIS = 'qris';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_QRIS,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function kasWeek()
    {
        return $this->belongsTo(KasWeek::class);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', self::METHOD_CASH);
    }

    public function scopeQris($query)
    {
        return $query->where('payment_method', self::METHOD_QRIS);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }
}
