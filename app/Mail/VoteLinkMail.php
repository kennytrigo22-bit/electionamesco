<?php

namespace App\Mail;

use App\Models\ElectionVoter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VoteLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ElectionVoter $voter)
    {
    }

    public function build(): self
    {
        return $this->subject('Votre lien de vote — '.$this->voter->election->title)
            ->view('emails.vote-link');
    }
}
