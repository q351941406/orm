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
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use App\Models\Channel;
use App\Models\Account;
use App\Jobs\installESJob;
use DateTime;
use DateTimeZone;
// 0 = 频道，1 = 群组
class MainController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    //保存私聊发送记录
    public function test(Request $request)
    {

//        $d = Group::get()->count();
//        dd($d);
        $engine_name = 'groups';
////        $a = Group::limit(10)->get();
////        installESJob::dispatch($engine_name,$a->toArray());
////        dd($a);
        $a = Group::
//        where([
//            ['last_msg_date','!=',null],
//            ['last_msg_date_normalize','!=',null],
//            ['average','!=',null],
//        ])
        where('msg_average_interval','!=',null)
            ->where('last_msg_date_normalize','!=',null)
            ->where('msg_average_interval','!=',null)
            ->where('ad_dirty','!=',null)
            ->where('sender_count','!=',null)
//            ->limit(10000)
            ->count();
//        $a = ElasticSearchApi::es_install_data($engine_name,$a->toArray());
        return response()->json($a);
//        ->chunk(10000, function ($models) use ($engine_name) {
////            Log::debug(1);
////            $lessModels = array_chunk($models->toArray(), 100, false);
////            foreach ($lessModels as $key => $value) {
////                installESJob::dispatch($engine_name,$value);
////            }
//////            dd($lessModels);
//        });

//        $model = Group::updateOrCreate(
//            ['id' => 21],
//            [
//                'msg_average_interval' => 9,
//            ]
//        );

//        $model = Channel::where('id',10)->update(['subscribers'=>7158]);
//        $model->save();



//        $channels = Channel::
//        whereIn('id', [19])
//            ->get();
//
//        $d = $channels->toArray();
//        foreach ($d as &$x){
//            if ($x['last_msg_date']){//es只接收DATE_RFC3339格式的data字段
//                $timeStamp = strtotime($x['last_msg_date']);
//                $x['last_msg_date'] = date(DATE_RFC3339,$timeStamp);
//            }
//            // 移除调某些key
//            $x = array_diff_key($x, ['updated_at' => "", "created_at" => "",'deleted_at'=>'']);
//        }
//        ElasticSearchApi::es_install_data('channels',$d);
////        $a = Channel::upsert($d,[]);
//        dd($d);
//        $model = Channel::find(43908);
////        $model->last_msg_date = "2016-10-15T08:00:10+00:00";
//        $model->last_msg_date_normalize = 0.42764284;
//
//        $model->save();



//        return response()->json($model);
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
    // 过滤没有在数据库中存在的链接
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

            if ($data['type'] == 0) {
                $model = Channel::updateOrCreate(
                    ['invite_link' => $data['link']],
                    [
                        'name' => $data['title'],
                        'info' => $data['description'],
                        'subscribers' => $data['number'],
                        'status' => $data['status']
                    ]
                );
            }
            if ($data['type'] == 1) {
                $model = Group::updateOrCreate(
                    ['invite_link' => $data['link']],
                    [
                        'name' => $data['title'],
                        'info' => $data['description'],
                        'count' => $data['number'],
                        'online' => $data['online_number'],
                        'status' => $data['status']
                    ]
                );
            }
            if (!$model){
                Log::error("入库失败",$request->all());
            }
            Log::info('入库成功',$model->toArray());
            return response()->json($model);
        }
    }
    // 也是更新消息,根据传递进来的参数更新
    public function update(Request $request)
    {
        $data = $request->input('data');
        $type = $request->input('type');
        $model = null;
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
            $channel->delete();
        }
        if ($type == 1){
            $group = Group::firstWhere('invite_link',$link);
            $group->delete();
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
}
