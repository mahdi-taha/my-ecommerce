<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('customer.password.reset', [
            'locale' => in_array($this->locale, ['en', 'ar'], true) ? $this->locale : config('app.locale'),
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('shop.auth.password.email.subject'))
            ->line(__('shop.auth.password.email.intro'))
            ->action(__('shop.auth.password.email.action'), $url)
            ->line(__('shop.auth.password.email.expire', [
                'minutes' => config('auth.passwords.customers.expire'),
            ]))
            ->line(__('shop.auth.password.email.ignore'));
    }
}
