<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Keyword extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'keywords';

    public function enemys()
    {
        return $this->belongsToMany(Enemy::class,'keyword_enemy');
    }
}
