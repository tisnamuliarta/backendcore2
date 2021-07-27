<?php

namespace App\Jobs;

use App\Http\Traits\Approval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGoodsIssueRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Approval;

    protected $data_header;
    protected $data_details;

    /**
     * Create a new job instance.
     *
     * @param $data_header
     * @param $data_details
     */
    public function __construct($data_header, $data_details)
    {
        $this->data_header = $data_header;
        $this->data_details = $data_details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // create goods issue request
        $this->createGoodsIssueRequest($this->data_header, $this->data_details);
    }
}
