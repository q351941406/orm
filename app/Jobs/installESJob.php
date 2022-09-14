<?php

namespace App\Jobs;

use App\Http\Controllers\ElasticSearchApi;
use App\Models\Channel;
use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class installESJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $engine_name;
    protected $models;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($engine_name,$models)
    {
//        $this->type = $type;
        $this->engine_name = $engine_name;
        $this->models = $models;
    }

    /**
     * cd app
     * php artisan queue:listen --timeout=500000
     * Execute the job.
     * php artisan queue:work --timeout=60
     * @return void
     */
    public function handle()
    {
        ElasticSearchApi::es_install_data($this->engine_name,$this->models);
//        if ($this->type == 0){
//            $engine_name = 'channels';
//            Channel::chunk(100, function ($models) use ($engine_name) {
//                ElasticSearchApi::es_install_data($engine_name,$models);
//            });
//        }else if ($this->type == 1) {
//            $engine_name = 'groups';
//            Group::chunk(100, function ($models) use ($engine_name) {
//                ElasticSearchApi::es_install_data($engine_name,$models);
//            });
//        }

    }
}
