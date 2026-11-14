<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'birth_date',
        'gender',
        'weight_kg',
        'height_cm',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'weight_kg' => 'decimal:1',
        'height_cm' => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
