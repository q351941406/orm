<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasFactory,SoftDeletes;
    /**
     * 不可以批量赋值的属性
     *
     * @var array
     */
    protected $guarded = [];
    /**
     * 与模型关联的数据表.
     *
     * @var string
     */
//    protected $table = 'citys';



}