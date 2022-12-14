<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AppSearchApi;
use App\Http\Controllers\ElasticSearchApi;
use App\Jobs\installESJob;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Enemy;
use App\Models\Keyword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use App\Models\Channel;
use App\Models\Account;
use App\Models\SearchLog;
use App\Jobs\installAppSearchJob;
use DateTime;
use DateTimeZone;
use App\Models\ChannelMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Client\Pool;

// 0 = 频道，1 = 群组
class MainController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;



    public function test(Request $request)
    {

//        dd(111);
//        $es = new ElasticSearchApi();
//        $es_result = $es->getMaxMessageID('channel',[150,126]);
//        dd($es_result);
//        $responses = Http::pool(fn (Pool $pool) => [
//            $pool->async()->get('http://www.baidu.com')->then(function ($response) {
//                echo 2;
//                echo $response->status();
//                $a = HTTP::get('http://www.baidu.com');
//                echo $a->status();
//
//            }),
//            $pool->async()->get('http://www.baidu.com')->then(function ($response) {
//                echo 3;
//                echo $response->status();
//                $a = HTTP::get('http://www.baidu.com');
//                echo $a->status();
//            }),
//            $pool->async()->get('http://www.baidu.com')->then(function ($response) {
//                echo 4;
//                echo $response->status();
//                $a = HTTP::get('http://www.baidu.com');
//                echo $a->status();
//            })
//        ]);
//        $responses[0]->ok();
//        foreach ($responses as $x){
//            $x->body();
//        }
//        这里需要对调close方法关闭系统资源，否则并发太大会报错
//        $promise =
//        $promise->wait();
//        echo 1;


//    Artisan::call('es:syncMessage 0 20');
        dd(111);
////        $aa = ChannelMessage::groupBy('channel_id')
////            ->having('channel_id', '<', 100)
//////            ->limit(1)
////            ->get();
////        $aa = ChannelMessage::where('msg_id','>',50000)->limit(1)->get();

//        $engine_name = 'groups';
//        $a = Group::
////        where([
////            ['last_msg_date','!=',null],
////            ['last_msg_date_normalize','!=',null],
////            ['average','!=',null],
////        ])
//        where('msg_average_interval','!=',null)
//            ->where('last_msg_date_normalize','!=',null)
//            ->where('msg_average_interval','!=',null)
//            ->where('ad_dirty','!=',null)
//            ->where('sender_count','!=',null)
////            ->limit(10000)
//            ->count();
////        $a = AppSearchApi::es_install_data($engine_name,$a->toArray());
//        return response()->json($a);
    }

    // 初始化es的数据
    public function es_install_data(Request $request,$type)
    {
//        $type = $request->input('type');
        if ($type == 0){
            $engine_name = 'channels';
            Channel::where([
                ['last_msg_date','!=',null],
                ['last_msg_date_normalize','!=',null],
                ['average','!=',null]
            ])
            ->chunk(10000, function ($models) use ($engine_name) {
                $lessModels = array_chunk($models->toArray(), 100, false);
                foreach ($lessModels as $key => $value) {
                    installAppSearchJob::dispatch($engine_name,$value);
                }
            });
        }else if ($type == 1) {
            $engine_name = 'groups';
            Group::where([
                ['last_msg_date','!=',null],
                ['last_msg_date_normalize','!=',null],
                ['msg_average_interval','!=',null],
                ['ad_dirty','!=',null],
                ['sender_count','!=',null]
            ])
            ->chunk(10000, function ($models) use ($engine_name) {
                $lessModels = array_chunk($models->toArray(), 100, false);
                foreach ($lessModels as $key => $value) {
                    installAppSearchJob::dispatch($engine_name,$value);
                }
            });
        }
        return response()->json(['mes'=>'添加到队列完毕']);
    }
    // 批量更新mysql再添加修改es到队列,暂时没用上
    public function batch_update(Request $request)
    {
        $type = $request->input('type');
        $data = $request->input('data');
        if ($type == 0){
            $engine_name = 'channels';
            foreach ($data as &$x){
                if ($x['last_msg_date']){//es只接收DATE_RFC3339格式的data字段
                    $timeStamp = strtotime($x['last_msg_date']);
                    $x['last_msg_date'] = date(DATE_RFC3339,$timeStamp);
                }
                // 移除调某些key
                $x = array_diff_key($x, ['updated_at' => "", "created_at" => "",'deleted_at'=>'']);
            }
            $a = Channel::upsert($data,[]);
            installAppSearchJob::dispatch($engine_name,$data);
        }else if ($type == 1) {
            $engine_name = 'groups';

        }
        return response()->json(['mes'=>'添加到队列完毕']);
    }

    //保存私聊发送记录
    public function save_private_keyword(Request $request)
    {
        $link = $request->input('link');
        $keyword = Keyword::find($request->input('Keyword_id'));
        if ($keyword->enemys()->where('link',$link)->first())
        {
            return response()->json(['mes'=>'已经有重复记录']);
        }else{
            $enemy = Enemy::where('link',$link)->first();
            $a = $keyword->enemys()->save($enemy);
            return response()->json(['mes'=>'关键词保存完毕']);
        }
    }
    // 获取一个没查询过关键词的机器人
    public function get_not_keyword_enemy_for_enemyBot(Request $request)
    {
        $text = $request->input('text');
        $model = Enemy::whereDoesntHave('keywords',function (Builder $query) use ($text) {
            $query->where('text',$text);
        })->get();
        $data = $model->toArray()[array_rand($model->toArray())];//随机取一个
        return response()->json($data);
    }
    // 获取这个机器人没有发送过的关键字
    public function get_unsend_for_text(Request $request)
    {
        $link = $request->input('link');
        $model = Keyword::whereDoesntHave('enemys',function (Builder $query) use ($link) {
            $query->where('link',$link);
        })
            ->first();
        return response()->json($model);
    }
    // 返回没有在数据库中存在的链接
    public function get_uninsertLink(Request $request)
    {
        $links = $request->input('links');
        $groups = Group::
            whereIn('invite_link', $links)
            ->addSelect('invite_link')
            ->get();
        $channels = Channel::
        whereIn('invite_link', $links)
            ->addSelect('invite_link')
            ->get();
        $new = array_merge($channels->toArray(),$groups->toArray());
        $new = array_column($new,'invite_link');
        $result =array_diff($links,$new);
        $result = array_values($result);// 去掉key
        return response()->json($result);
    }

    // 数据入库
    public function updateInfo(Request $request)
    {
        $data = $request->all()['data'];
        $model = null;
        if ($request->all()['code'] == 0) {
            try {
                if ($data['type'] == 0) {
                    $model = Channel::withoutEvents(function () use ($data) {
                        return Channel::updateOrCreate(
                            ['invite_link' => $data['link']],
                            [
                                'name' => $data['title'],
                                'info' => $data['description'],
                                'subscribers' => $data['number'],
                                'status' => $data['status']
                            ]
                        );
                    });
                }
                if ($data['type'] == 1) {
                    $model = Group::withoutEvents(function () use ($data) {
                        return Group::updateOrCreate(
                            ['invite_link' => $data['link']],
                            [
                                'name' => $data['title'],
                                'info' => $data['description'],
                                'count' => $data['number'],
                                'online' => $data['online_number'],
                                'status' => $data['status']
                            ]
                        );
                    });
                }
                Log::info('入库成功',$model->toArray());
                return response()->json($model);
            }catch (\Exception $e){
                Log::error($e->getMessage(),$request->all());
                return response()->json(['msg'=>$e->getMessage()]);
            }
        }
    }
    // 也是更新消息,根据传递进来的参数更新
    public function update(Request $request)
    {
        $data = $request->input('data');
        $type = $request->input('type');
        $model = null;

        try {
            if ($type == 0){
                $model = Channel::withoutEvents(function () use ($data) {
                    return Channel::updateOrCreate(
                        ['id' => $data['id']],
                        $data
                    );
                });
            }
            if ($type == 1){
                $model = Group::withoutEvents(function () use ($data) {
                    return Group::updateOrCreate(
                        ['id' => $data['id']],
                        $data
                    );
                });
            }
            return response()->json($model);
        }catch (\Exception $e){
            Log::error($e->getMessage(),$request->all());
            return response()->json(['msg'=>$e->getMessage()]);
        }
    }
    // 也是更新消息,根据传递进来的参数更新
    public function updateMessage(Request $request){
        $data = $request->input('data');
        $type = $request->input('type');
        $model = null;
//        [$keys, $values] = Arr::divide($data[0]);

        try {
            if ($type == 0){
                $model = ChannelMessage::withoutEvents(function () use ($data) {
                    [$keys, $values] = Arr::divide($data[0]);
                    return ChannelMessage::upsert(
                        $data,
                        ['channel_id','msg_id'],
                        $keys,
                    );
                });
            }
//            if ($type == 1){
//                $model = Group::withoutEvents(function () use ($data) {
//                    return Group::updateOrCreate(
//                        ['id' => $data['id']],
//                        $data
//                    );
//                });
//            }
//            dd($model);
            return response()->json($model);
        }catch (\Exception $e){
            Log::error($e->getMessage(),$request->all());
            return response()->json(['msg'=>$e->getMessage()]);
        }
    }
    // 获取一个账号
    public function get_account(Request $request)
    {
        $status = $request->input('status');
        $model = Account::firstWhere('status',$status);
        return response()->json($model);
    }
    // 保存一个账号
    public function save_account(Request $request)
    {
        $phone = $request->all()['phone'];
        $model = Account::updateOrCreate(
            ['phone' => $phone],
            $request->all()
        );
        return response()->json($model);
    }
    // 更改账号状态
    public function update_account_status(Request $request)
    {
        $phone = $request->input('phone');
        $status = $request->input('status');
        $model = Account::firstWhere('phone',$phone);
        $model->status = $status;
        $model->save();
        return response()->json($model);
    }
    // 删除链接
    public function link_delete(Request $request){
        $type = $request->input('type');
        $link = $request->input('link');
        if ($type == 0){
            $channel = Channel::firstWhere('invite_link',$link);
            if ($channel){
                $channel->delete();
            }else {
                return response()->json(['msg'=>'数据库查不到']);
            }
            $channel->delete();
        }
        if ($type == 1){
            $group = Group::firstWhere('invite_link',$link);
            if ($group){
                $group->delete();
            }else {
                return response()->json(['msg'=>'数据库查不到']);
            }
        }
        return response()->json(['msg'=>'删除完毕']);
    }
    // 获取需要更新的群的列表
    public function get_need_update_groupList(Request $request){
        $isDirty = $request->input('isDirty');
        $page = $request->input('page');
        $limit = $request->input('limit');
        // 过滤掉15天都没消息的、广告污染成都很严重的群，这种垃圾群留到某个特地时间更新
        $days = date("y-m-d H:i:s",strtotime('-15 day'));//x天前
        $ad_dirtyWhere = null;
        $last_msg_date = null;
        if ($isDirty == 0){
            $ad_dirtyWhere = ['ad_dirty', '>', 0.5];
            $last_msg_date = ['last_msg_date', '<', $days];
            $models = Group::
            where(function($query) use ($ad_dirtyWhere){
                $query
                    ->where('ad_dirty',null)
                    ->orWhere([$ad_dirtyWhere]);
            })
                ->orWhere(function($query)use ($last_msg_date) {
                    $query
                        ->where('last_msg_date', null)
                        ->orWhere([$last_msg_date]);
                })
                ->paginate($limit,'*','page',$page);
            return response()->json($models);
        }else {
            $ad_dirtyWhere = ['ad_dirty', '<', 0.5];
            $last_msg_date = ['last_msg_date', '>', $days];
            $models = Group::
            where(function($query) use ($ad_dirtyWhere){
                $query
                    ->where('ad_dirty',null)
                    ->orWhere([$ad_dirtyWhere]);
            })
                ->where(function($query)use ($last_msg_date) {
                    $query
                        ->where('last_msg_date', null)
                        ->orWhere([$last_msg_date]);
                })
                ->paginate($limit,'*','page',$page);
            return response()->json($models);
        }
    }

    /**
     * 删除那些垃圾消息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function deleteMessage(Request $request){
        $channel_id = $request->input('channel_id');
        $scyllaDomain = env('SCYLLA_DB_GO');
        $channel = Channel::find($channel_id);
        $channel->status = 1;
        $channel->save();
        Http::post("{$scyllaDomain}/api/v1/msg/channel/delete",['channel_id'=>$channel_id])->json();
        return response()->json($channel);
    }
    public function syncMessage(Request $request,$start,$end){

        $indexName = 'search-test';

//        $numbers = range($start,$end);
//        $sql = Channel::whereIn('status',[0,100]);
//        if (count($numbers) > 1){
//            $sql->whereIn('id',$numbers);
//        }
//        $sql->chunk(100, function ($models) use($indexName) {
//            $MemFree = Tools::getMemFree();
//            Log::debug("当前剩余内存:{$MemFree}G");
//            if ($MemFree <= 1){
//                Log::error('内存太少了停止，不要继续塞数据到redis了');
//                exit();
//            }
//            $ids = Arr::pluck($models->toArray(), 'id');
//            $es = new ElasticSearchApi();
//            $es_result = $es->getMaxMessageID('channel',$ids,$indexName);
//            // 定一个函数，里面拼接请求返回个Pool请求数组
//            $fn2 = function (Pool $pool) use ($models,$es_result,$indexName) {
//                foreach ($models as $value) {
//                    $channel = $value;
//                    $maxMessageID = Arr::get($es_result, (string)$channel->id,0);
//                    $scyllaDomain = env('SCYLLA_DB_GO');
//                    Log::debug("开始请求Go获取ID:{$channel->id}频道的消息");
//                    // 这里发一个请求，再请求结果里再进行一次请求，相当于一次串行请求
//                    $arrayPools[] = $pool->async()->get("{$scyllaDomain}/api/v1/msg/channel/backward?channel_id={$channel->id}&msg_id={$maxMessageID}")
//                        ->then(function ($data) use($channel,$indexName) {
//                                        $data = $data->json();
//                                        if ($data['data'] != null){
//                                            $lessData = array_chunk($data['data']['channel_msg_list'], 2000, false);
//                                            foreach ($lessData as $key => $value) {
//                                                $newValue = array_map(function($item) use ($channel) {
//                                                    $item['name'] = $channel->name;
//                                                    $item['info'] = $channel->info;
//                                                    $item['invite_link'] = $channel->invite_link;
//                                                    $item['is_forward'] = (int)$item['is_forward'];//es那边不接受true、false
//                                                    $item['id'] = (string) Str::uuid();
//                                                    $item['uuid'] = $item['id'];
//                                                    $item['parent_status'] = $channel->status;
//                                                    $item['head_url'] = $channel->head_url;
//                                                    $item['type'] = 'channel';
//                                                    $item['entity_id'] = $channel->entity_id;
//                                                    $item['subscribers'] = $channel->subscribers;
//                                                    $item['parent_created_at'] = $channel->toArray()['created_at'];
//                                                    $item['parent_updated_at'] = $channel->toArray()['updated_at'];
//                                                    $item['parent_deleted_at'] = $channel->toArray()['deleted_at'];
//                                                    return $item;
//                                                }, $value);
//                                                //                                    Log::debug("频道ID:{$channel->id}请求Go完毕");
//                                            Log::debug("将ID:{$channel->id}频道的消息提交到Redis");
//                                              $es = new ElasticSearchApi();
//                                              $es->updateOrCreate_bulk('channel',$newValue,$indexName);
////                                              installESJob::dispatch($newValue,'search-message','channel');
//                                            }
//                                        }
//                        });
//                }
//                return $arrayPools;
//            };
//            $responses = \Illuminate\Support\Facades\Http::pool($fn2);
//            Log::debug("并发请求结束");
//        });








        // 这是同步群的
        $numbers = range($start,$end);
        $sql = Group::whereIn('status',[0,100]);
        if (count($numbers) > 1){
            $sql->whereIn('id',$numbers);
        }
        $sql->chunk(100, function ($models) use($indexName) {
            $MemFree = Tools::getMemFree();
            Log::debug("当前剩余内存:{$MemFree}G");
            if ($MemFree <= 1){
                Log::error('内存太少了停止，不要继续塞数据到redis了');
                exit();
            }
            $ids = Arr::pluck($models->toArray(), 'id');
            $es = new ElasticSearchApi();
            $es_result = $es->getMaxMessageID('group',$ids,$indexName);

                // 定一个函数，里面拼接请求返回个Pool请求数组
            $fn2 = function (Pool $pool) use ($models,$es_result,$indexName) {
                foreach ($models as $value) {
                    $group = $value;
                    $maxMessageID = Arr::get($es_result, (string)$group->id,0);
                    $scyllaDomain = env('SCYLLA_DB_GO');
                    Log::debug("开始请求Go获取ID:{$group->id}群的消息");
    //                dd($maxMessageID);
                    // 这里发一个请求，再请求结果里再进行一次请求，相当于一次串行请求
                    $arrayPools[] = $pool->async()->get("{$scyllaDomain}/api/v1/msg/group/backward?group_id={$group->id}&msg_id={$maxMessageID}")
                    ->then(function ($data) use($group,$indexName) {
                        $data = $data->json();
                        if ($data['data'] != null){
                            $lessData = array_chunk($data['data']['group_msg_list'], 2000, false);
                            foreach ($lessData as $key => $value) {
                                $newValue = array_map(function($item) use ($group) {
                                    $item['name'] = $group->name;
                                    $item['info'] = $group->info;
                                    $item['invite_link'] = $group->invite_link;
                                    $item['is_forward'] = (int)$item['is_forward'];//es那边不接受true、false
                                    $item['id'] = (string) Str::uuid();
                                    $item['uuid'] = $item['id'];
                                    $item['type'] = 'group';
                                    $item['parent_status'] = $group->status;
                                    $item['head_url'] = $group->head_url;
                                    $item['entity_id'] = $group->entity_id;
                                    $item['count'] = $group->count;
                                    $item['parent_created_at'] = $group->toArray()['created_at'];
                                    $item['parent_updated_at'] = $group->toArray()['updated_at'];
                                    $item['parent_deleted_at'] = $group->toArray()['deleted_at'];
                                    return $item;
                                }, $value);
                                //                                    Log::debug("频道ID:{$channel->id}请求Go完毕");
                            Log::debug("将ID:{$group->id}群的消息提交到Redis");
                            //////                                    $es = new ElasticSearchApi();
                            //////                                    $es->updateOrCreate_bulk($newValue);

                              $es = new ElasticSearchApi();
                              $es->updateOrCreate_bulk('group',$newValue,$indexName);
//                            installESJob::dispatch($newValue,$indexName,'group');
                            }
                        }
                    });
                }
                return $arrayPools;
            };
            $responses = \Illuminate\Support\Facades\Http::pool($fn2);
            Log::debug("并发请求结束");
        });



    }
}
