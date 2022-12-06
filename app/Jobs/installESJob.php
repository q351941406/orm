<?php

namespace App\Jobs;

use App\Http\Controllers\ElasticSearchApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;



class installESJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected $data;
    protected $index;
    protected $type;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data,$index,$type)
    {
        $this->data = $data;
        $this->index = $index;
        $this->type = $type;
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
        $es = new ElasticSearchApi();
        $es->updateOrCreate_bulk($this->type,$this->data,$this->index);
    }
}
