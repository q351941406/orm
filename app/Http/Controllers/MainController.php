<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ElasticSearchApi;
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
use App\Jobs\installESJob;
use DateTime;
use DateTimeZone;
use App\Models\ChannelMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
// 0 = 频道，1 = 群组
class MainController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    //保存私聊发送记录
    public function test(Request $request)
    {
//        $MemFree = Tools::getMemFree();
//        dd($MemFree);

        Artisan::call('es:syncMessage 0 10');
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
////        $a = ElasticSearchApi::es_install_data($engine_name,$a->toArray());
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
                    installESJob::dispatch($engine_name,$value);
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
                    installESJob::dispatch($engine_name,$value);
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
            installESJob::dispatch($engine_name,$data);
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

    /**
     * 同步消息,注意：每次要先消耗完redis里的再调
     * @param Request $request
     * @return void
     */
    public function syncMessage(Request $request,$start,$end){

        $numbers = range($start,$end);
        Channel::whereIn('id',$numbers)
            ->whereIn('status',[0,100])
            ->chunk(20, function ($models) {
                $MemFree = Tools::getMemFree();
                Log::debug("当前剩余内存:{$MemFree}G");
                if ($MemFree <= 0.5){
                    Log::error('内存太少了停止，不要继续塞数据到redis了');
                    exit();
                }
                foreach ($models as $value) {
                    Log::debug("正在查看ID={$value->id}");
                    $channel = $value;
                    // 查es该频道最大msg_id
                    $scyllaDomain = env('SCYLLA_DB_GO');
                    $urlSuffix = "/api/as/v1/engines/message/elasticsearch/_search";
                    $data = [
                        'query'=>[
                            'term'=>[
                                'channel_id'=>$channel->id
                            ]
                        ],
                        'runtime_mappings' => [// 动态修改字段类型，不然下面无法进行聚合计算，app search的类型和es的类型没关联
                            'msg_id'=>[
                                'type'=>'long'
                            ]
                        ],
                        'size' => 0,
                        'aggs' => [
                            'max_msg_id'=>[
                                'max'=>[
                                    'field'=>'msg_id'
                                ],
                            ]
                        ]
                    ];
                    $response = ElasticSearchApi::post($urlSuffix,$data);
//                    dd($response);
                    $msg_id = Arr::get($response, 'aggregations.max_msg_id.value') ?: 0;
                    $data = Http::get("{$scyllaDomain}/api/v1/msg/channel/backward?channel_id={$channel->id}&msg_id={$msg_id}")->json();
                    if ($data['data'] != null){
                        $lessData = array_chunk($data['data']['channel_msg_list'], 100, false);
                        foreach ($lessData as $key => $value) {
                            $newValue = array_map(function($item) use ($channel) {
                                $item['name'] = $channel->name;
                                $item['info'] = $channel->info;
                                $item['invite_link'] = $channel->invite_link;
                                $item['is_forward'] = (int)$item['is_forward'];//es那边不接受true、false
                                $item['id'] = (string) Str::uuid();
                                $item['uuid'] = $item['id'];
                                return $item;
                            }, $value);
//                            ElasticSearchApi::es_install_data('message',$newValue,true);
                            installESJob::dispatch('message',$newValue,true);
                        }
                    }
                }
        });
    }

}
