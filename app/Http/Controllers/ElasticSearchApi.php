<?php

namespace App\Http\Controllers;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\Psr18Client;
class ElasticSearchApi
{
    private \Elastic\Elasticsearch\Client $es;
    public function __construct()
    {
        $this->es = ClientBuilder::create()
            ->setHttpClient(new Psr18Client)
            ->setElasticCloudId('Telegram:dXMtd2VzdC0xLmF3cy5mb3VuZC5pbzo0NDMkYTNlMTU4MjMzMGI0NDkwOWEzZGI1Zjk5YTkzNmE5ZjEkMzE4M2Y4YTc2YmE5NDRiZTlhZjE4OWZmNjhhZmQ5ZWU=')
            ->setApiKey('ZGZMdXg0UUJ4dWVneG9RcUVSZW46aGtTWVBVWEhUOUN3V1daM2pWZmpDZw==')
            ->build();

    }
    public function updateOrCreate_bulk($data,$index='search-message'){
        $params['body'] = [];
        foreach ($data as $x){
            $params['body'][] = [
                'index' => [   #创建
                    '_index' => $index,
                    '_id' => $x['id'],
                ]
            ];
            $x = Arr::except($x, ['id']);
            $params['body'][] = $x;
        }

        $bulk_result = $this->bulk($params);

        // 更新uuid
        Log::debug("当前消费正在上传:{$data[0]['channel_id']}");
        $newData = array_map(function($item) {
            // 过滤掉不要的数据，节省内存
            $item = Arr::except($item, ['hyperlinks','text','name','info',
                'invite_link','deleted_at','updated_at',
                'created_at','send_time','id',
                'is_forward','views','parent_status','head_url',
                'entity_id','subscribers','parent_created_at',
                'parent_updated_at','parent_deleted_at']);
            return $item;
        }, $data);
        $scyllaDomain = env('SCYLLA_DB_GO');
        $result = Http::post("{$scyllaDomain}/api/v1/msg/channel/multi_update_uuid",['channel_msg_list'=>$newData]);

        return $bulk_result;
    }



    function bulk($params){
        $response = $this->es->bulk($params)->asArray();
        if ($response['errors']){
            Log::error('ES返回错误',$response['items']);
        }
        return $response;
    }

}
