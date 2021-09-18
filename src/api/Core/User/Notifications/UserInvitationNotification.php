<?php

namespace Casanova\Manifold\Api\Core\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
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
        $url = url('/panel/signup/'.$notifiable->token);
        $naorespondaAdress = 'naoresponda@' . env('MAIL_SUFFIX');
        $naorespondaName = 'Não Responda - ' . env('APP_NAME');
        $mailMessage = (new MailMessage)
                    ->from($naorespondaAdress, $naorespondaName)
                    ->subject('Convite')
                    ->greeting('Olá!')
                    ->line('Você foi convidado para se registar na nossa aplicação')
                    ->action('Registrar conta', $url)
                    ->line('Obrigado por usar nossa aplicação!');
                    
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
