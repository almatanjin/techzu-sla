<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}

