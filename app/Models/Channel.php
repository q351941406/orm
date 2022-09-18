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
//            Log::debug('111111');
//            if ($model->last_msg_date){//es只接收DATE_RFC3339格式的data字段
//                $timeStamp = strtotime($model->last_msg_date);
//                $model->last_msg_date = date(DATE_RFC3339,$timeStamp);
//            }
            ElasticSearchApi::es_install_data('channels',[$model->toArray()]);
        });
        static::deleted(function ($model) {
            ElasticSearchApi::delete_data('channels',[$model->id]);
        });
    }

}
