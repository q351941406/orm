<?php

namespace App\Http\Controllers;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
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
    public function getMaxMessageID($type,$ids,$indexName='search-message'){
        $keyName = '';
        if ($type === 'channel'){
            $keyName = 'channel_id';
        }else {
            $keyName = 'group_id';
        }
        // 先用query内的terms进行条件限定,在agg内对channelID分组,然后在分组内进行max子聚合
        $params = [
            'index' => $indexName,
            'body' => [
                'query'=>[
                    'terms'=>[
                            $keyName=>$ids
                    ]
                ],
//                'runtime_mappings' => [// 动态修改字段类型，不然下面无法进行聚合计算，app search的类型和es的类型没关联
//                    'msg_id'=>[
//                        'type'=>'long'
//                    ]
//                ],
                'size' => 0,// 0代表不需要详细__source数据
                'aggs' => [
                    'channelID'=>[
                        'terms'=>[
                                'field'=>$keyName,
                                'size'=>count($ids),//默认值统计10条
                        ],
                    'aggs' => [
                        'max_msg_id'=>[
                            'max'=>['field'=>'msg_id']
                        ]
                      ]
                    ],
                ]
            ]
        ];
        try {
            $response = $this->es->search($params);
            $new = [];
            array_map(function($item) use ( &$new){
                $new[$item['key']] = $item['max_msg_id']['value'];
                return $item;
                }, $response->asArray()['aggregations']['channelID']['buckets']);
            return $new;
        }catch (ClientResponseException $e){
            Log::error("ES返回错误:{$e->getMessage()}");
        }
    }
    public function updateOrCreate_bulk($type,$data,$index='search-message'){
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
//        // 更新uuid
//        if ($type === 'channel'){
//            Log::debug("当前消费正在上传:{$data[0]['channel_id']}");
//            $newData = array_map(function($item) {
//                // 过滤掉不要的数据，节省内存
//            $item = Arr::except($item, ['hyperlinks','text','name','info',
//            'invite_link','deleted_at','updated_at',
//            'created_at','send_time','id',
//            'is_forward','views','parent_status','head_url',
//            'entity_id','subscribers','parent_created_at',
//            'parent_updated_at','parent_deleted_at']);
//            return $item;
//            }, $data);
//            $scyllaDomain = env('SCYLLA_DB_GO');
//            $result = Http::post("{$scyllaDomain}/api/v1/msg/channel/multi_update_uuid",['channel_msg_list'=>$newData]);
//        }else {
//            Log::debug("当前消费正在上传:{$data[0]['group_id']}");
//            $newData = array_map(function($item) {
//                // 过滤掉不要的数据，节省内存
//            $item = Arr::except($item, ['hyperlinks','text','name','info',
//            'invite_link','deleted_at','updated_at',
//            'created_at','send_time','id',
//            'is_forward','views','parent_status','head_url',
//            'entity_id','subscribers','parent_created_at',
//            'parent_updated_at','parent_deleted_at','count','type']);
//            return $item;
//            }, $data);
//            $scyllaDomain = env('SCYLLA_DB_GO');
//            $result = Http::post("{$scyllaDomain}/api/v1/msg/group/multi_update_uuid",['group_msg_list'=>$newData]);
//        }
        return $bulk_result;
    }



    function bulk($params){
        $response = $this->es->bulk($params)->asArray();
        if ($response['errors']){
            Log::error('ES返回错误',$response['items']);
            exit();
        }
        return $response;
    }

}
