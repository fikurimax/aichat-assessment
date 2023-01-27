<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'contact_number',
        'email',
    ];

    public function fullname(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['first_name'] . ' ' . $this->attributes['last_name']
        );
    }
}
