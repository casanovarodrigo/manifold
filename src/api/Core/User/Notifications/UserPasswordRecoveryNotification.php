<?php

namespace Casanova\Manifold\Api\Core\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPasswordRecoveryNotification extends Notification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = url('/panel/passwordrecovery/'.$notifiable->recovery_token);
        $naorespondaAdress = 'naoresponda@' . env('MAIL_SUFFIX');
        $naorespondaName = 'Não Responda - ' . env('APP_NAME');
        $mailMessage = (new MailMessage)
                    ->from($naorespondaAdress, $naorespondaName)
                    ->subject('Recuperação de senha')
                    ->greeting('Não tema')
                    ->line('Um token de recuperação de conta foi gerado.')
                    ->action('Trocar senha', $url)
                    ->line('Boa sorte.');
                    
        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
