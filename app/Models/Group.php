<?php

namespace App\Models;

use App\Http\Controllers\AppSearchApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Group extends Model
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


    protected static function booted()
    {
        static::saved(function ($model) {
            AppSearchApi::es_install_data('groups',[$model->toArray()]);
        });
        static::deleted(function ($model) {
            AppSearchApi::delete_data('groups',[$model->id]);
        });
    }
}
