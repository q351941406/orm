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
    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($engine_name,$data)
    {
//        $this->type = $type;
        $this->engine_name = $engine_name;
        $this->data = $data;
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
        ElasticSearchApi::es_install_data($this->engine_name,$this->data);
    }
}
