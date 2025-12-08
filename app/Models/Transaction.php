<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $fillable = ['user_id','reference','type','asset','amount','status','meta'];
    protected $casts = ['meta'=>'array'];
    public function user(){ return $this->belongsTo(User::class); }
}
