<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallES extends Command
{
    /**
     * The name and signature of the console command.
     * 案例 php artisan es:install 0
     * @var string
     */
    protected $signature = 'es:install {type=0}';

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
        $type = $this->argument('type');// 获取命令行上的参数
        $controller = app()->make('App\Http\Controllers\MainController');//获取控制器
        $result = app()->call([$controller, 'es_install_data'], ['type'=>(int)$type]);//动态调用控制器，注意这里还是通过路由，记得注意路由规则
        $this->info($result);//打印在控制台
    }
}
