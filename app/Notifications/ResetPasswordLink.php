<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The reset link, in NexABI's own envelope.
 *
 * Laravel's default is a fine email and the wrong one: it arrives unbranded
 * from a product that otherwise looks like itself everywhere, which is exactly
 * the inconsistency that makes people wonder whether the mail is genuine.
 */
class ResetPasswordLink extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->view('mail.reset-password', [
                'user' => $notifiable,
                'url' => url($url),
                'minutes' => $minutes,
            ]);
    }
}
