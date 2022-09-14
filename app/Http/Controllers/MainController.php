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

class MainController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    //保存私聊发送记录
    public function test(Request $request)
    {
//        dd(1111);
        return response()->json(['mes'=>'测试']);
    }
    // 初始化es的数据
    public function es_install_data(Request $request)
    {
        $type = $request->input('type');
        if ($type == 0){
            $engine_name = 'channels';
            Channel::chunk(100, function ($models) use ($engine_name) {
                installESJob::dispatch($engine_name,$models);
            });
        }else if ($type == 1) {
            $engine_name = 'groups';
            Group::chunk(100, function ($models) use ($engine_name) {
                installESJob::dispatch($engine_name,$models);
            });
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

    // 更新信息
    public function updateInfo(Request $request)
    {
        $data = $request->all()['data'];
        $model = null;
        if ($request->all()['code'] == 0) {

            if ($data['type'] == 1) {
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
            if ($data['type'] == 2) {
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
                Log::error("入库失败{$request->all()}");
            }
            Log::info('入库成功',$model->toArray());
            return response()->json($model);
        }
    }
    // 获取一个账号
    public function get_account(Request $request)
    {
        $status = $request->input('status');
        $model = Account::firstWhere('status',$status);
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
}
