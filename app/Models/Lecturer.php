<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gender',
        'phone_number',
    ];

    protected $appends = [
        'salutation',
    ];

    public function getSalutationAttribute(): string
    {
        return $this->gender === 'male' ? 'Bapak' : 'Ibu';
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
