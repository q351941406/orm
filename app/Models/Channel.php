<?php

namespace App\Models;

use App\Http\Controllers\ElasticSearchApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

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

    protected static function booted()
    {
        // updateOrCreate、save、update、Create都会调
        static::saved(function ($model) {
            ElasticSearchApi::es_install_data('channels',[$model->toArray()]);
        });
        static::deleted(function ($model) {
            ElasticSearchApi::delete_data('channels',[$model->id]);
        });
    }

}
