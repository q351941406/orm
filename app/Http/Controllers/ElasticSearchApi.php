<?php
namespace App\Http\Controllers;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use App\Models\Channel;
class ElasticSearchApi {

    public static function es_install_data($engine_name,$models){

        $urlSuffix = "/api/as/v1/engines/{$engine_name}/documents";
        $data = $models->toArray();
        $response = ElasticSearchApi::post($urlSuffix,$data);
        foreach ($response as $x){
            if (count($x['errors']) > 0){
                Log::error('数据上传错误',$x['errors']);
            }
        }
//        dd($response);
    }
    public static function post($urlSuffix,$data){
        $esDomain = 'https://telegram.ent.us-west-1.aws.found.io';
        $response = Http::withToken('private-h4vn3vuat6vdxehdu9bz4x2c')->withHeaders([
            'Content-Type' => 'application/json'
        ])
            ->post("{$esDomain}{$urlSuffix}", $data);

        if ($response->successful()){
            return $response->json();
        }else {
            Log::error('返回错误状态码');
        }
    }
}
