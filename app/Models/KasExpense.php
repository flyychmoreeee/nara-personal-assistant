<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class KasExpense extends Model
{
    protected $guarded = ['id'];

    protected function receiptImage(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
