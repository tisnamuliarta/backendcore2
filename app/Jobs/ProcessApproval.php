<?php

namespace App\Jobs;

use App\Mail\ProcessApprovalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $content;
    protected $receiver = [];
    protected $cc = [];

    /**
     * Create a new job instance.
     *
     * @param $content
     * @param $receiver
     * @param $cc
     */
    public function __construct($content, $receiver, $cc)
    {
        $this->content = $content;
        $this->receiver = $receiver;
        $this->cc = $cc;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = new ProcessApprovalMail($this->content);
        Mail::to($this->receiver)
            ->cc($this->cc)
            ->send($email);
    }
}
