<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'daily_bonus',
        'cap_multiplier',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'daily_bonus' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function capAmount(): string
    {
        return bcmul($this->price, (string) $this->cap_multiplier, 2);
    }
}
