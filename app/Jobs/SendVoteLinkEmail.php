<?php

namespace App\Jobs;

use App\Mail\VoteLinkMail;
use App\Models\ElectionVoter;
use Illuminate\Support\Facades\Mail;

/**
 * Sent synchronously (not queued): shared hosting rarely runs a persistent queue
 * worker, so a queued mail job would silently never be processed.
 */
class SendVoteLinkEmail
{
    public function __construct(public ElectionVoter $voter) {}

    public static function dispatch(ElectionVoter $voter): void
    {
        (new self($voter))->handle();
    }

    public function handle(): void
    {
        Mail::to($this->voter->email)->send(new VoteLinkMail($this->voter));

        $this->voter->update(['email_sent_at' => now()]);
    }
}
