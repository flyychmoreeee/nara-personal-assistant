<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasPayment extends Model
{
    protected $guarded = ['id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function kasWeek()
    {
        return $this->belongsTo(KasWeek::class);
    }
}
