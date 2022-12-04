<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ESSyncMessage extends Command
{
    /**
     * The name and signature of the console command.
     * 案例 php artisan es:syncMessage 1 11 传0 0的话代表查找全部
     * @var string
     */
    protected $signature = 'es:syncMessage {start=0} {end=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start = $this->argument('start');// 获取命令行上的参数
        $end = $this->argument('end');
        $controller = app()->make('App\Http\Controllers\MainController');//获取控制器
        $result = app()->call([$controller, 'syncMessage'], ['start'=>(int)$start,'end'=>(int)$end]);//动态调用控制器，注意这里还是通过路由，记得注意路由规则
        $this->info($result);//打印在控制台
    }
}
