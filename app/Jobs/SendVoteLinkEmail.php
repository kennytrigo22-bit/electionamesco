<?php

namespace App\Jobs;

use App\Mail\VoteLinkMail;
use App\Models\ElectionVoter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendVoteLinkEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public ElectionVoter $voter) {}

    public function handle(): void
    {
        Mail::to($this->voter->email)->send(new VoteLinkMail($this->voter));

        $this->voter->update(['email_sent_at' => now()]);
    }
}
