<?php

namespace App\Http\Controllers;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Arr;
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
        return $this->bulk($params);
    }



    function bulk($params){
        $response = $this->es->bulk($params)->asArray();
        if ($response['errors']){
            Log::error('ES返回错误',$response['items']);
        }
        return $response;
    }

}
