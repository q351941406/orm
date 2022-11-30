<?php

namespace App\Jobs;

use App\Http\Controllers\AppSearchApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;



class installAppSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $engine_name;
    protected $data;
    protected $updateUUID;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($engine_name,$data,$updateUUID)
    {
//        $this->type = $type;
        $this->engine_name = $engine_name;
        $this->data = $data;
        $this->updateUUID = $updateUUID;
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
        AppSearchApi::es_install_data($this->engine_name,$this->data,$this->updateUUID);
    }
}
