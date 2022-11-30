<?php

namespace App\Models;

use App\Http\Controllers\AppSearchApi;
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
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public function messages()
    {
        return $this->hasMany(ChannelMessage::class);
    }
    protected static function booted()
    {
        // updateOrCreate、save、Create都会调
        static::saved(function ($model) {
//            Log::debug('111111');
            if ($model->last_msg_date){//es只接收DATE_RFC3339格式的data字段
                $timeStamp = strtotime($model->last_msg_date);
                $model->last_msg_date = date(DATE_RFC3339,$timeStamp);
            }
            AppSearchApi::es_install_data('channels',[$model->toArray()]);
        });
        static::deleted(function ($model) {
            AppSearchApi::delete_data('channels',[$model->id]);
        });
    }

}
