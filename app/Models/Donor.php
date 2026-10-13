<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blood_group',
        'gender',
        'address',
        'last_donation_date',
        'is_available',
        'hep_b',
        'hep_c',
        'hiv',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'hep_b' => 'boolean',
            'hep_c' => 'boolean',
            'hiv' => 'boolean',
            'last_donation_date' => 'date',
        ];
    }

    /**
     * Get the user that owns the donor profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}