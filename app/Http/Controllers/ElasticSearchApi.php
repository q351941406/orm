<?php
namespace App\Http\Controllers;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use App\Models\Channel;
class ElasticSearchApi {

    public static function es_install_data($engine_name,$models){

        $urlSuffix = "/api/as/v1/engines/{$engine_name}/documents";
        $data = $models;
        // 修改提升值，不然衰减函数无法生效，让它值变小，
        if($engine_name === 'groups'){
            foreach ($data as &$x){
                if ($x['msg_average_interval'] == 0){// 1不能除以0，所以这里把最高发送频率的0设置成1
                    $x['msg_average_interval'] = 1;
                }
                $x['msg_average_interval'] = 1/ $x['msg_average_interval'];
            }
        }
//        return $data;
        $response = ElasticSearchApi::post($urlSuffix,$data);
        if (!$response){
            Log::error('ES没有返回内容');
            return;
        }
        foreach ($response as $x){
            if (count($x['errors']) > 0){
                Log::error('数据上传错误',$x['errors']);
            }
        }
    }
    public static function delete_data($engine_name,$ids){

        $urlSuffix = "/api/as/v1/engines/{$engine_name}/documents";
        $data = $ids;
        $response = ElasticSearchApi::delete($urlSuffix,$data);
//        foreach ($response as $x){
//            if ($x['deleted'] == false){//这里es那边有问题，无论成功失败都是返回false,但其实已经删除成功了的
//                Log::error("数据删除错误id:{$x['id']}",);
//            }
//        }
    }






    public static function post($urlSuffix,$data){
        $esDomain = 'https://telegram.ent.us-west-1.aws.found.io';
        $response = Http::withToken('private-h4vn3vuat6vdxehdu9bz4x2c')->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("{$esDomain}{$urlSuffix}", $data);

        if ($response->successful()){
            return $response->json();
        }else {
            Log::error('返回错误状态码');
        }
    }
    public static function delete($urlSuffix,$data){
        $esDomain = 'https://telegram.ent.us-west-1.aws.found.io';
        $response = Http::withToken('private-h4vn3vuat6vdxehdu9bz4x2c')->withHeaders([
            'Content-Type' => 'application/json'
        ])->delete("{$esDomain}{$urlSuffix}", $data);

        if ($response->successful()){
            return $response->json();
        }else {
            Log::error('返回错误状态码');
        }
    }
}
