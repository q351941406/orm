<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Enemy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enemys';


    public function keywords()
    {
        return $this->belongsToMany(Keyword::class,'keyword_enemy');
    }
}
