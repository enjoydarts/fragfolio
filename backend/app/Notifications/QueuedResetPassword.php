<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->onQueue('emails');
    }
}
