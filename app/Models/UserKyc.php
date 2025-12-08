<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserKyc extends Model
{
    use HasFactory;
	protected $table = 'user_kycs'; // ✅ Add this line
	
    protected $fillable = [
        'user_id',
		'provider',
        'id_type',
        'id_value',
        'bvn',
        'nin',
		'data',
        'status',
		'photo_path',
		'document_path',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
