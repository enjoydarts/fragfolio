<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

class QueuedResetPasswordWithLocale extends ResetPassword implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->onQueue('emails');
    }

    public function toMail($notifiable)
    {
        $this->locale = $notifiable->preferred_language ?? config('app.locale');

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($this->resetUrl($notifiable));
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject(__('auth.reset_password_subject'))
            ->view('emails.reset-password', [
                'url' => $url,
                'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
