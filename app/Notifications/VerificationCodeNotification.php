<?php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification
{
    public $code;
    public $type;

    public function __construct($code, $type = 'email_verification')
    {
        $this->code = $code;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $subject = $this->type === 'email_verification' ? 'Email Verification Code' : 'Password Reset Code';
        return (new MailMessage)
            ->subject($subject)
            ->line('Your code is: **' . $this->code . '**')
            ->line('This code expires in 10 minutes.');
    }
}
